<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreBillRequest;
use App\Http\Resources\BillResource;
use App\Models\Bill;
use App\Models\BillItem;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class BillController extends Controller
{
    /**
     * GET /api/bills
     * List bills with optional filters and pagination.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Bill::with('items')->withCount('items')->latest();

        if ($request->filled('search')) {
            $query->search($request->search);
        }

        if ($request->filled('payment_method')) {
            $query->where('payment_method', $request->payment_method);
        }

        if ($request->filled('date')) {
            $query->forDate($request->date);
        }

        if ($request->filled('from') && $request->filled('to')) {
            $query->betweenDates($request->from, $request->to);
        }

        $perPage = (int) $request->get('per_page', 20);
        $bills   = $query->paginate($perPage);

        return BillResource::collection($bills);
    }

    /**
     * POST /api/bills
     * Create a new bill with items. Reduces product stock automatically.
     * 
     * Item format:
     * {
     *   "product_id": 1,
     *   "product_name": "Rice",
     *   "unit": "kg",
     *   "sell_mode": "loose" or "wholesale",
     *   "quantity": 2,
     *   "unit_price": 48,
     *   "discount": 0,
     *   "sgst_percent": 5,
     *   "cgst_percent": 5
     * }
     */
    public function store(StoreBillRequest $request): JsonResponse
    {
        DB::beginTransaction();

        try {
            $subtotal        = 0;
            $totalDiscount   = 0;
            $totalSgst       = 0;
            $totalCgst       = 0;
            $totalProfit     = 0;
            $calculatedItems = [];
            $stockNeeds      = [];

            $productIds = collect($request->items)
                ->pluck('product_id')
                ->filter()
                ->unique()
                ->values();

            $products = Product::whereIn('id', $productIds)
                ->where('is_active', true)
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            // ── 1. Calculate item totals and profit ──────────────
            foreach ($request->items as $index => $item) {
                $productId = $item['product_id'] ?? null;
                $sellMode  = $item['sell_mode'] ?? 'loose';
                $quantity  = (float) $item['quantity'];

                $item = [
                    'product_id'   => $productId,
                    'product_name' => $item['product_name'],
                    'unit'         => $item['unit'],
                    'sell_mode'    => $sellMode,
                    'unit_price'   => (float) $item['unit_price'],
                    'cost_price'   => 0,
                    'quantity'     => $quantity,
                    'stock_qty'    => 0,
                    'discount'     => (float) ($item['discount'] ?? 0),
                    'sgst_percent' => (float) ($item['sgst_percent'] ?? 0),
                    'cgst_percent' => (float) ($item['cgst_percent'] ?? 0),
                ];

                if ($productId) {
                    if (!isset($products[$productId])) {
                        throw ValidationException::withMessages([
                            "items.{$index}.product_id" => 'Product is inactive or unavailable.',
                        ]);
                    }

                    $product = $products[$productId];
                    $item['product_name'] = $product->name;
                    $item['sgst_percent'] = (float) $product->sgst;
                    $item['cgst_percent'] = (float) $product->cgst;

                    if ($item['sell_mode'] === 'wholesale') {
                        if (!$product->isBulkProduct()) {
                            throw ValidationException::withMessages([
                                "items.{$index}.sell_mode" => "{$product->name} cannot be sold in wholesale mode.",
                            ]);
                        }

                        $item['unit']       = $product->purchase_unit;
                        $item['unit_price'] = $product->getEffectiveWholesalePrice();
                        $item['cost_price'] = $product->getEffectiveWholesaleCost();
                        $item['stock_qty']  = $quantity * $product->purchase_qty;
                    } else {
                        $item['unit']       = $product->unit;
                        $item['unit_price'] = $product->selling_price;
                        $item['cost_price'] = $product->cost_price;
                        $item['stock_qty']  = $quantity;
                    }

                    $stockNeeds[$productId] = ($stockNeeds[$productId] ?? 0) + $item['stock_qty'];
                }

                $lineBase = round($item['unit_price'] * $item['quantity'], 2);
                if ($item['discount'] > $lineBase) {
                    throw ValidationException::withMessages([
                        "items.{$index}.discount" => 'Discount cannot be greater than the item amount.',
                    ]);
                }

                $calculated = BillItem::calculateAmounts($item);

                $subtotal      += $lineBase;
                $totalDiscount += $item['discount'];
                $totalSgst     += $calculated['sgst_amount'];
                $totalCgst     += $calculated['cgst_amount'];
                $totalProfit    += $calculated['line_profit'];

                $calculatedItems[] = $calculated;
            }

            foreach ($stockNeeds as $productId => $stockQty) {
                $product = $products[$productId];

                if (((float) $product->stock + 0.0005) < (float) $stockQty) {
                    throw ValidationException::withMessages([
                        'items' => "{$product->name} has only {$product->stock} {$product->unit} in stock.",
                    ]);
                }
            }

            $grandTotal     = round($subtotal - $totalDiscount + $totalSgst + $totalCgst, 2);
            $totalProfit    = round($totalProfit, 2);
            $amountReceived = (float) ($request->amount_received ?? 0);
            $changeReturned = max(0, round($amountReceived - $grandTotal, 2));

            // ── 2. Create bill ───────────────────────────────
            $bill = Bill::create([
                'bill_number'     => Bill::generateBillNumber(),
                'customer_name'   => $request->customer_name ?: 'Walk-in Customer',
                'payment_method'  => $request->payment_method,
                'subtotal'        => round($subtotal, 2),
                'total_discount'  => round($totalDiscount, 2),
                'total_sgst'      => round($totalSgst, 2),
                'total_cgst'      => round($totalCgst, 2),
                'grand_total'     => $grandTotal,
                'total_profit'    => $totalProfit,
                'amount_received' => $amountReceived,
                'change_returned' => $changeReturned,
                'notes'           => $request->notes,
            ]);

            // ── 3. Optimize: fetch all products in one query ───────
            // ── 4. Create bill items + update stock ───────────────
            foreach ($calculatedItems as $itemData) {

                $productId = $itemData['product_id'] ?? null;
                $sellMode = $itemData['sell_mode'] ?? 'loose';

                $bill->items()->create([
                    'product_id'     => $productId,
                    'product_name'   => $itemData['product_name'],
                    'unit'           => $itemData['unit'],
                    'sell_mode'      => $sellMode,
                    'unit_price'     => $itemData['unit_price'],
                    'cost_price'     => $itemData['cost_price'],
                    'quantity'       => $itemData['quantity'],
                    'stock_qty'      => $itemData['stock_qty'],
                    'discount'       => $itemData['discount'],
                    'sgst_percent'   => $itemData['sgst_percent'],
                    'cgst_percent'   => $itemData['cgst_percent'],
                    'sgst_amount'    => $itemData['sgst_amount'],
                    'cgst_amount'    => $itemData['cgst_amount'],
                    'line_total'     => $itemData['line_total'],
                    'line_profit'    => $itemData['line_profit'],
                ]);
            }

            // Deduct stock once per product in base units.
            foreach ($stockNeeds as $productId => $stockQty) {
                Product::where('id', $productId)->decrement('stock', $stockQty);
            }

            DB::commit();

            return response()->json([
                'message' => 'Bill created successfully.',
                'data'    => new BillResource($bill->load('items')),
            ], 201);

        } catch (ValidationException $e) {
            DB::rollBack();
            throw $e;
        } catch (\Throwable $e) {
            DB::rollBack();
            \Log::error('Bill creation error: ' . $e->getMessage());

            return response()->json([
                'message' => 'Failed to create bill.',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * GET /api/bills/{id}
     * Show a single bill with all items.
     */
    public function show(Bill $bill): BillResource
    {
        return new BillResource($bill->load('items'));
    }

    /**
     * DELETE /api/bills/{id}
     * Delete a bill and restore product stock.
     */
    public function destroy(Bill $bill): JsonResponse
    {
        DB::beginTransaction();

        try {
            // Restore stock for each item (in base units)
            foreach ($bill->items as $item) {
                if ($item->product_id) {
                    $saleQtyInBaseUnit = (float) ($item->stock_qty ?? 0);

                    if ($saleQtyInBaseUnit <= 0) {
                        $saleQtyInBaseUnit = (float) $item->quantity;
                    }

                    $product = Product::withTrashed()
                        ->where('id', $item->product_id)
                        ->lockForUpdate()
                        ->first();

                    if ($product) {
                        $product->increment('stock', $saleQtyInBaseUnit);
                    }
                }
            }

            $bill->delete();
            DB::commit();

            return response()->json(['message' => 'Bill deleted and stock restored.']);

        } catch (\Throwable $e) {
            DB::rollBack();
            \Log::error('Bill deletion error: ' . $e->getMessage());
            return response()->json(['message' => 'Failed to delete bill.', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * GET /api/bills/summary
     * Aggregated report data: overall totals, day-of-week breakdown, and products sorted by margin.
     */
    public function summary(Request $request): JsonResponse
    {
        $from   = $request->get('from', now()->startOfMonth()->toDateString());
        $to     = $request->get('to',   now()->toDateString());
        $fromDt = $from . ' 00:00:00';
        $toDt   = $to   . ' 23:59:59';
 
        // Detect driver once so we pick the right date function below
        $isSQLite = DB::getDriverName() === 'sqlite';
 
        // ── Query 1: totals + total profit ───────────────────────────────────
        $row = DB::table('bills')
            ->whereBetween('bills.created_at', [$fromDt, $toDt])
            ->selectRaw("
                COUNT(bills.id)                         AS total_bills,
                COALESCE(SUM(bills.grand_total),    0)  AS total_sales,
                COALESCE(SUM(bills.total_discount), 0)  AS total_discount,
                COALESCE(SUM(bills.total_sgst),     0)  AS total_sgst,
                COALESCE(SUM(bills.total_cgst),     0)  AS total_cgst,
                COALESCE(SUM(bills.total_profit),   0)  AS total_profit
            ")
            ->first();
 
        // ── Query 2: day-of-week breakdown ───────────────────────────────────
        // SQLite  → strftime('%w', ...) returns 0=Sun … 6=Sat
        // MySQL   → strftime does not exist; use DAYOFWEEK()-1 which is also 0=Sun…6=Sat
        // Either way we get an integer 0-6 that we resolve to a name in PHP below.
        $dowExpr      = $isSQLite
            ? "CAST(strftime('%w', bills.created_at) AS INTEGER)"
            : "(DAYOFWEEK(bills.created_at) - 1)";
 
        $dayRows = DB::table('bills')
            ->whereBetween('bills.created_at', [$fromDt, $toDt])
            ->selectRaw("
                {$dowExpr}                                  AS dow,
                COUNT(bills.id)                             AS txn_count,
                COALESCE(SUM(bills.grand_total), 0)         AS total_sales,
                COALESCE(SUM(bills.total_profit), 0)        AS total_profit
            ")
            ->groupByRaw($dowExpr)
            ->orderByRaw($dowExpr)
            ->get();
 
        // Resolve integer 0-6 → day name in PHP (no DB function needed)
        $dayNames = ['Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'];
 
        $dayRows = $dayRows->map(fn ($d) => [
            'day_name'     => $dayNames[(int) $d->dow] ?? 'Unknown',
            'txn_count'    => (int)   $d->txn_count,
            'total_sales'  => round((float) $d->total_sales,  2),
            'total_profit' => round((float) $d->total_profit, 2),
        ])->values();
 
        // ── Query 3: product ranking by margin (desc) ────────────────────────
        // GROUP BY product_name — one pass, no sub-queries.
        // Margin calculation happens in PHP after the single round-trip.
        $products = DB::table('bill_items')
            ->join('bills', 'bill_items.bill_id', '=', 'bills.id')
            ->whereBetween('bills.created_at', [$fromDt, $toDt])
            ->selectRaw("
                bill_items.product_name,
                SUM(CASE WHEN bill_items.sell_mode = 'loose'     THEN bill_items.quantity ELSE 0 END) AS loose_qty,
                SUM(CASE WHEN bill_items.sell_mode = 'wholesale' THEN bill_items.quantity ELSE 0 END) AS wholesale_qty,
                SUM(bill_items.stock_qty)        AS base_qty_sold,
                SUM(bill_items.line_total)       AS total_sales,
                SUM(bill_items.line_total - bill_items.sgst_amount - bill_items.cgst_amount) AS total_revenue,
                SUM(bill_items.line_profit)      AS gross_profit
            ")
            ->groupBy('bill_items.product_name')
            ->get()
            ->map(function ($p) {
                $revenue = (float) $p->total_revenue;
                $profit  = (float) $p->gross_profit;
                return [
                    'product_name'   => $p->product_name,
                    'loose_qty'      => round((float) $p->loose_qty, 3),
                    'wholesale_qty'  => round((float) $p->wholesale_qty, 3),
                    'qty_sold'       => round((float) $p->base_qty_sold, 3),
                    'base_qty_sold'  => round((float) $p->base_qty_sold, 3),
                    'total_sales'    => round((float) $p->total_sales, 2),
                    'total_revenue'  => round($revenue, 2),
                    'gross_profit'   => round($profit,  2),
                    'profit_revenue' => round($profit,  2),
                    'margin_percent' => $revenue > 0
                        ? round(($profit / $revenue) * 100, 2)
                        : 0,
                ];
            })
            ->sortByDesc('margin_percent') // PHP sort — avoids an extra ORDER BY round-trip
            ->values();
 
        return response()->json([
            'period' => ['from' => $from, 'to' => $to],
            'totals' => [
                'total_bills'    => (int)   $row->total_bills,
                'total_sales'    => round((float) $row->total_sales,    2),
                'total_discount' => round((float) $row->total_discount, 2),
                'total_sgst'     => round((float) $row->total_sgst,     2),
                'total_cgst'     => round((float) $row->total_cgst,     2),
                'total_profit'   => round((float) $row->total_profit,   2),
            ],
            'day_of_week_breakdown' => $dayRows,
            'sold_products'         => $products,
        ]);
    }
}
