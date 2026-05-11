<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreProductRequest;
use App\Http\Requests\UpdateProductRequest;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Log;

class ProductController extends Controller
{
    /**
     * GET /api/products
     * List all active products with optional search & sorting.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Product::active();

        if ($request->filled('search')) {
            $query->search($request->search);
        }

        if ($request->filled('low_stock')) {
            $query->lowStock((int) $request->get('threshold', 20));
        }

        $sortBy  = $request->get('sort_by', 'name');
        $sortDir = $request->get('sort_dir', 'asc');

        $allowedSorts = ['name', 'selling_price', 'cost_price', 'stock', 'created_at'];
        if (in_array($sortBy, $allowedSorts)) {
            $query->orderBy($sortBy, $sortDir === 'desc' ? 'desc' : 'asc');
        }

        $products = $request->filled('per_page')
            ? $query->paginate((int) $request->per_page)
            : $query->get();

        return ProductResource::collection($products);
    }

    /**
     * POST /api/products
     * Create a new product.
     */
    public function store(StoreProductRequest $request): JsonResponse
    {
        $product = Product::create([
            'name'            => $request->name,
            'unit'            => $request->unit,
            'cost_price'      => $request->cost_price,
            'selling_price'   => $request->selling_price,
            'wholesale_price' => $request->filled('purchase_unit') ? $request->wholesale_price : null,
            'wholesale_cost'  => $request->filled('purchase_unit') ? $request->wholesale_cost : null,
            'sgst'            => $request->sgst ?? 0,
            'cgst'            => $request->cgst ?? 0,
            'stock'           => $request->stock,
            'purchase_unit'   => $request->purchase_unit,
            'purchase_qty'    => $request->purchase_qty,
            'barcode'         => $request->barcode,
            'is_active'       => $request->is_active ?? true,
        ]);

        return response()->json([
            'message' => 'Product created successfully.',
            'data'    => new ProductResource($product),
        ], 201);
    }

    /**
     * GET /api/products/{id}
     * Show single product.
     */
    public function show(Product $product): ProductResource
    {
        return new ProductResource($product);
    }

    /**
     * PUT /api/products/{id}
     * Update a product.
     */
    public function update(UpdateProductRequest $request, Product $product): JsonResponse
    {
        $product->update($request->validated());

        return response()->json([
            'message' => 'Product updated successfully.',
            'data'    => new ProductResource($product->fresh()),
        ]);
    }

    /**
     * DELETE /api/products/{id}
     * Soft delete a product.
     */
    public function destroy(Product $product): JsonResponse
    {
        $product->delete();

        return response()->json([
            'message' => 'Product deleted successfully.',
        ]);
    }

    /**
     * PATCH /api/products/{id}/stock
     * Adjust stock (add or subtract).
     */
    public function adjustStock(Request $request, Product $product): JsonResponse
    {
        $request->validate([
            'adjustment' => 'required|numeric',
            'reason'     => 'nullable|string|max:200',
        ]);

        $newStock = $product->stock + (float) $request->adjustment;

        if ($newStock < 0) {
            return response()->json([
                'message' => 'Stock cannot go below zero.',
            ], 422);
        }

        $product->update(['stock' => $newStock]);

        return response()->json([
            'message'   => 'Stock updated successfully.',
            'old_stock' => $product->stock - $request->adjustment,
            'new_stock' => $newStock,
            'data'      => new ProductResource($product->fresh()),
        ]);
    }

    /**
     * GET /api/products/low-stock
     * Get all products with low stock.
     */
    public function lowStock(Request $request): AnonymousResourceCollection
    {
        $threshold = (int) $request->get('threshold', 20);
        $products  = Product::active()->lowStock($threshold)->orderBy('stock')->get();

        return ProductResource::collection($products);
    }
}
