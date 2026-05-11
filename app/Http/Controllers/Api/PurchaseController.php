<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\PurchaseResource;
use App\Models\Product;
use App\Models\Purchase;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;

class PurchaseController extends Controller
{
    /**
     * GET /api/purchases
     * List all purchases with optional filters
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Purchase::with('product')->latest('purchase_date');

        if ($request->filled('search')) {
            $term = $request->search;
            $query->where(function ($q) use ($term) {
                $q->where('supplier_name', 'like', "%{$term}%")
                  ->orWhere('invoice_number', 'like', "%{$term}%")
                  ->orWhereHas('product', function ($productQuery) use ($term) {
                      $productQuery->where('name', 'like', "%{$term}%")
                                   ->orWhere('barcode', $term);
                  });
            });
        }

        if ($request->filled('product_id')) {
            $query->where('product_id', $request->product_id);
        }

        if ($request->filled('from')) {
            $query->whereDate('purchase_date', '>=', $request->from);
        } 
        if ($request->filled('to')) {
            $query->whereDate('purchase_date', '<=', $request->to);
        }

        $perPage = (int) $request->get('per_page', 20);
        $purchases = $query->paginate($perPage);

        return PurchaseResource::collection($purchases);
    }

    /**
     * POST /api/purchases
     * Create a new purchase (stock intake) entry
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'product_id'     => 'required|exists:products,id',
            'received_unit'  => 'required|string|max:50',
            'received_qty'   => 'required|numeric|min:0.01',
            'cost_per_unit'  => 'required|numeric|min:0',
            'purchase_date'  => 'required|date',
            'supplier_name'  => 'nullable|string|max:150',
            'invoice_number' => 'nullable|string|max:100',
            'notes'          => 'nullable|string|max:1000',
        ]);

        DB::beginTransaction();

        try {
            $product = Product::findOrFail($request->product_id);

            // Calculate converted quantity (always in base unit)
            $convertedQty = $this->calculateConvertedQty(
                $request->received_unit,
                $request->received_qty,
                $product->unit,
                $product->purchase_unit,
                $product->purchase_qty
            );

            $totalCost = $request->received_qty * $request->cost_per_unit;

            // Create purchase record
            $purchase = Purchase::create([
                'product_id'     => $product->id,
                'received_unit'  => $request->received_unit,
                'received_qty'   => $request->received_qty,
                'converted_qty'  => $convertedQty,
                'cost_per_unit'  => $request->cost_per_unit,
                'total_cost'     => $totalCost,
                'purchase_date'  => $request->purchase_date,
                'supplier_name'  => $request->supplier_name,
                'invoice_number' => $request->invoice_number,
                'notes'          => $request->notes,
            ]);

            // Model boot will automatically update product stock and costs
            $product->refresh();

            DB::commit();

            return response()->json([
                'message'        => 'Stock intake recorded successfully.',
                'purchase'       => new PurchaseResource($purchase->load('product')),
                'stock_added'    => $convertedQty,
                'stock_now'      => $product->stock,
                'new_cost_price' => $product->cost_price,
                'new_wholesale_cost' => $product->wholesale_cost,
            ], 201);

        } catch (\Throwable $e) {
            DB::rollBack();
            \Log::error('Purchase creation error: ' . $e->getMessage());

            return response()->json([
                'message' => 'Failed to record purchase.',
                'error'   => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * PUT /api/purchases/{id}
     * Update a purchase and adjust stock by the changed converted quantity.
     */
    public function update(Request $request, Purchase $purchase): JsonResponse
    {
        $request->validate([
            'product_id'     => 'required|exists:products,id',
            'received_unit'  => 'required|string|max:50',
            'received_qty'   => 'required|numeric|min:0.01',
            'cost_per_unit'  => 'required|numeric|min:0',
            'purchase_date'  => 'required|date',
            'supplier_name'  => 'nullable|string|max:150',
            'invoice_number' => 'nullable|string|max:100',
            'notes'          => 'nullable|string|max:1000',
        ]);

        DB::beginTransaction();

        try {
            $oldProduct = $purchase->product;
            $oldConvertedQty = (float) $purchase->converted_qty;
            $newProduct = Product::findOrFail($request->product_id);

            $convertedQty = $this->calculateConvertedQty(
                $request->received_unit,
                (float) $request->received_qty,
                $newProduct->unit,
                $newProduct->purchase_unit,
                $newProduct->purchase_qty
            );

            $totalCost = (float) $request->received_qty * (float) $request->cost_per_unit;

            if ($oldProduct && $oldProduct->id === $newProduct->id) {
                $this->adjustProductStock($newProduct, $convertedQty - $oldConvertedQty);
            } else {
                if ($oldProduct) {
                    $this->adjustProductStock($oldProduct, -$oldConvertedQty);
                }
                $this->adjustProductStock($newProduct, $convertedQty);
            }

            $purchase->update([
                'product_id'     => $newProduct->id,
                'received_unit'  => $request->received_unit,
                'received_qty'   => $request->received_qty,
                'converted_qty'  => $convertedQty,
                'cost_per_unit'  => $request->cost_per_unit,
                'total_cost'     => $totalCost,
                'purchase_date'  => $request->purchase_date,
                'supplier_name'  => $request->supplier_name,
                'invoice_number' => $request->invoice_number,
                'notes'          => $request->notes,
            ]);

            $this->updateProductCosts($newProduct, $request->received_unit, (float) $request->cost_per_unit);
            $newProduct->refresh();

            DB::commit();

            return response()->json([
                'message'        => 'Stock intake updated successfully.',
                'purchase'       => new PurchaseResource($purchase->fresh()->load('product')),
                'stock_adjusted' => round($convertedQty - $oldConvertedQty, 3),
                'stock_now'      => $newProduct->stock,
                'new_cost_price' => $newProduct->cost_price,
                'new_wholesale_cost' => $newProduct->wholesale_cost,
            ]);

        } catch (\Throwable $e) {
            DB::rollBack();
            \Log::error('Purchase update error: ' . $e->getMessage());

            return response()->json([
                'message' => 'Failed to update purchase.',
                'error'   => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * GET /api/purchases/{id}
     * Show a single purchase
     */
    public function show(Purchase $purchase): PurchaseResource
    {
        return new PurchaseResource($purchase->load('product'));
    }

    /**
     * DELETE /api/purchases/{id}
     * Delete a purchase and reverse the stock
     */
    public function destroy(Purchase $purchase): JsonResponse
    {
        DB::beginTransaction();

        try {
            $product = $purchase->product;
            $stockBefore = $product->stock;

            $purchase->delete();

            // Model boot will automatically reverse stock
            $product->refresh();

            DB::commit();

            return response()->json([
                'message'         => 'Purchase deleted and stock reversed successfully.',
                'stock_reversed'  => $purchase->converted_qty,
                'stock_before'    => $stockBefore,
                'stock_after'     => $product->stock,
            ]);

        } catch (\Throwable $e) {
            DB::rollBack();
            \Log::error('Purchase deletion error: ' . $e->getMessage());

            return response()->json([
                'message' => 'Failed to delete purchase.',
                'error'   => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * POST /api/purchases/product/{product_id}/preview
     * Calculate the stock and cost impact before saving.
     */
    public function preview(Request $request, int $product_id): JsonResponse
    {
        $request->validate([
            'received_unit' => 'required|string|max:50',
            'received_qty'  => 'required|numeric|min:0.01',
            'cost_per_unit' => 'required|numeric|min:0',
        ]);

        $product = Product::findOrFail($product_id);
        $convertedQty = $this->calculateConvertedQty(
            $request->received_unit,
            (float) $request->received_qty,
            $product->unit,
            $product->purchase_unit,
            $product->purchase_qty
        );

        $totalCost = (float) $request->received_qty * (float) $request->cost_per_unit;
        $newCostPrice = (float) $request->cost_per_unit;
        $newWholesaleCost = null;

        if ($request->received_unit === $product->purchase_unit && $product->purchase_qty > 0) {
            $newCostPrice = (float) $request->cost_per_unit / $product->purchase_qty;
            $newWholesaleCost = (float) $request->cost_per_unit;
        } elseif ($product->purchase_qty > 0) {
            $newWholesaleCost = (float) $request->cost_per_unit * $product->purchase_qty;
        }

        return response()->json([
            'qty_to_add_in_base_unit' => round($convertedQty, 3),
            'converted_qty'           => round($convertedQty, 3),
            'new_stock'               => round($product->stock + $convertedQty, 3),
            'total_cost'              => round($totalCost, 2),
            'new_cost_price'          => round($newCostPrice, 4),
            'new_wholesale_cost'      => $newWholesaleCost !== null ? round($newWholesaleCost, 2) : null,
        ]);
    }

    /**
     * Helper: Calculate how many base units this purchase adds to stock
     */
    private function calculateConvertedQty(
        string $receivedUnit,
        float $receivedQty,
        ?string $baseUnit,
        ?string $purchaseUnit,
        ?float $purchaseQty
    ): float {
        // If received in base unit, return as-is
        if ($receivedUnit === $baseUnit) {
            return $receivedQty;
        }

        // If received in purchase unit, multiply by conversion
        if ($receivedUnit === $purchaseUnit && $purchaseQty > 0) {
            return $receivedQty * $purchaseQty;
        }

        // If no conversion needed, return as-is
        return $receivedQty;
    }

    private function adjustProductStock(Product $product, float $delta): void
    {
        if ($delta > 0) {
            $product->increment('stock', $delta);
        } elseif ($delta < 0) {
            $product->decrement('stock', abs($delta));
        }
    }

    private function updateProductCosts(Product $product, string $receivedUnit, float $costPerUnit): void
    {
        $updates = [];

        if ($receivedUnit === $product->unit) {
            $updates['cost_price'] = $costPerUnit;

            if ($product->purchase_qty) {
                $updates['wholesale_cost'] = $costPerUnit * $product->purchase_qty;
            }
        } elseif ($receivedUnit === $product->purchase_unit && $product->purchase_qty > 0) {
            $updates['cost_price'] = $costPerUnit / $product->purchase_qty;
            $updates['wholesale_cost'] = $costPerUnit;
        }

        if (!empty($updates)) {
            $product->update($updates);
        }
    }
}
