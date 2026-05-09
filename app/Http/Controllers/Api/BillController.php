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
     */
    public function store(StoreBillRequest $request): JsonResponse
    {
        DB::beginTransaction();

        try {
            $subtotal       = 0;
            $totalDiscount  = 0;
            $totalSgst      = 0;
            $totalCgst      = 0;
            $calculatedItems = [];

            // ── 1. Calculate item totals ──────────────────────────
            foreach ($request->items as $item) {
                $item['discount']     = $item['discount'] ?? 0;
                $item['sgst_percent'] = $item['sgst_percent'] ?? 0;
                $item['cgst_percent'] = $item['cgst_percent'] ?? 0;

                $calculated = BillItem::calculateAmounts($item);

                $lineBase = $item['unit_price'] * $item['quantity'];

                $subtotal      += $lineBase;
                $totalDiscount += $item['discount'];
                $totalSgst     += $calculated['sgst_amount'];
                $totalCgst     += $calculated['cgst_amount'];

                $calculatedItems[] = $calculated;
            }

            $grandTotal     = round($subtotal - $totalDiscount + $totalSgst + $totalCgst, 2);
            $amountReceived = $request->amount_received ?? 0;
            $changeReturned = max(0, round($amountReceived - $grandTotal, 2));

            // ── 2. Create bill ───────────────────────────────
            $bill = Bill::create([
                'bill_number'     => Bill::generateBillNumber(),
                'customer_name'   => $request->customer_name,
                'payment_method'  => $request->payment_method,
                'subtotal'        => round($subtotal, 2),
                'total_discount'  => round($totalDiscount, 2),
                'total_sgst'      => round($totalSgst, 2),
                'total_cgst'      => round($totalCgst, 2),
                'grand_total'     => $grandTotal,
                'amount_received' => $amountReceived,
                'change_returned' => $changeReturned,
                'notes'           => $request->notes,
            ]);

            // ── 3. Optimize: fetch all products in one query ───────
            $productIds = collect($calculatedItems)
                ->pluck('product_id')
                ->filter()
                ->unique()
                ->values();

            $products = Product::whereIn('id', $productIds)
                ->get()
                ->keyBy('id');

            // ── 4. Create bill items + update stock ───────────────
            foreach ($calculatedItems as $itemData) {

                $productId = $itemData['product_id'] ?? null;

                $costPrice = null;
                if ($productId && isset($products[$productId])) {
                    $costPrice = $products[$productId]->cost_price;
                }

                $bill->items()->create([
                    'product_id'   => $productId,
                    'product_name' => $itemData['product_name'],
                    'unit'         => $itemData['unit'],
                    'unit_price'   => $itemData['unit_price'],
                    'cost_price'   => $costPrice,
                    'quantity'     => $itemData['quantity'],
                    'discount'     => $itemData['discount'],
                    'sgst_percent' => $itemData['sgst_percent'],
                    'cgst_percent' => $itemData['cgst_percent'],
                    'sgst_amount'  => $itemData['sgst_amount'],
                    'cgst_amount'  => $itemData['cgst_amount'],
                    'line_total'   => $itemData['line_total'],
                ]);

                // atomic stock update (still fine)
                if ($productId) {
                    Product::where('id', $productId)
                        ->decrement('stock', $itemData['quantity']);
                }
            }

            DB::commit();

            return response()->json([
                'message' => 'Bill created successfully.',
                'data'    => new BillResource($bill->load('items')),
            ], 201);

        } catch (\Throwable $e) {
            DB::rollBack();

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
            // Restore stock for each item
            foreach ($bill->items as $item) {
                if ($item->product_id) {
                    Product::where('id', $item->product_id)
                           ->increment('stock', $item->quantity);
                }
            }

            $bill->delete();
            DB::commit();

            return response()->json(['message' => 'Bill deleted and stock restored.']);

        } catch (\Throwable $e) {
            DB::rollBack();
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
 
        // Shared profit expression — identical for both drivers
        $profitExpr = "(bill_items.unit_price * bill_items.quantity - bill_items.discount)
                       - (bill_items.cost_price * bill_items.quantity)";
 
        // Detect driver once so we pick the right date function below
        $isSQLite = DB::getDriverName() === 'sqlite';
 
        // ── Query 1: totals + total profit ───────────────────────────────────
        // LEFT JOIN keeps bills that have zero items in the bill count.
        // SUM(DISTINCT bills.grand_total) avoids double-counting per-item fan-out.
        $row = DB::table('bills')
            ->leftJoin('bill_items', 'bills.id', '=', 'bill_items.bill_id')
            ->whereBetween('bills.created_at', [$fromDt, $toDt])
            ->selectRaw("
                COUNT(DISTINCT bills.id)                AS total_bills,
                COALESCE(SUM(DISTINCT bills.grand_total),    0) AS total_sales,
                COALESCE(SUM(DISTINCT bills.total_discount), 0) AS total_discount,
                COALESCE(SUM(DISTINCT bills.total_sgst),     0) AS total_sgst,
                COALESCE(SUM(DISTINCT bills.total_cgst),     0) AS total_cgst,
                COALESCE(SUM({$profitExpr}), 0)         AS total_profit
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
            ->leftJoin('bill_items', 'bills.id', '=', 'bill_items.bill_id')
            ->whereBetween('bills.created_at', [$fromDt, $toDt])
            ->selectRaw("
                {$dowExpr}                                      AS dow,
                COUNT(DISTINCT bills.id)                        AS txn_count,
                COALESCE(SUM(DISTINCT bills.grand_total), 0)    AS total_sales,
                COALESCE(SUM({$profitExpr}), 0)                 AS total_profit
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
                SUM(bill_items.quantity)        AS qty_sold,
                SUM(bill_items.line_total)       AS total_revenue,
                SUM({$profitExpr})               AS gross_profit
            ")
            ->groupBy('bill_items.product_name')
            ->get()
            ->map(function ($p) {
                $revenue = (float) $p->total_revenue;
                $profit  = (float) $p->gross_profit;
                return [
                    'product_name'   => $p->product_name,
                    'qty_sold'       => (int) $p->qty_sold,
                    'total_revenue'  => round($revenue, 2),
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
                'total_profit'   => round((float) $row->total_profit,   2),
            ],
            'day_of_week_breakdown' => $dayRows,
            'sold_products'         => $products,
        ]);
    }
}
