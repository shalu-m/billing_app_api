<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Purchase extends Model
{
    use HasFactory;

    protected $table = 'purchases';

    protected $fillable = [
        'product_id',
        'received_unit',
        'received_qty',
        'converted_qty',
        'cost_per_unit',
        'total_cost',
        'purchase_date',
        'supplier_name',
        'invoice_number',
        'notes',
    ];

    protected $casts = [
        'received_qty'  => 'float',
        'converted_qty' => 'float',
        'cost_per_unit' => 'float',
        'total_cost'    => 'float',
        'purchase_date' => 'date',
        'created_at'    => 'datetime',
        'updated_at'    => 'datetime',
    ];

    // ── Relationships ────────────────────────────────────────

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    // ── Booted - Auto-update product costs when purchase is saved/deleted ────

    protected static function booted(): void
    {
        static::created(function (Purchase $purchase) {
            $purchase->updateProductCosts();
        });

        static::deleted(function (Purchase $purchase) {
            $purchase->reverseStock();
        });
    }

    // ── Methods ──────────────────────────────────────────────

    /**
     * Update product stock and costs after purchase is saved
     */
    public function updateProductCosts(): void
    {
        $product = $this->product;
        if (!$product) return;

        $product->increment('stock', $this->converted_qty);

        $updates = [];

        if ($this->received_unit === $product->unit) {
            $updates['cost_price'] = $this->cost_per_unit;

            if ($product->purchase_qty) {
                $updates['wholesale_cost'] = $this->cost_per_unit * $product->purchase_qty;
            }
        } elseif ($this->received_unit === $product->purchase_unit && $product->purchase_qty) {
            $updates['cost_price'] = $this->cost_per_unit / $product->purchase_qty;
            $updates['wholesale_cost'] = $this->cost_per_unit;
        }

        if (!empty($updates)) {
            $product->update($updates);
        }
    }

    /**
     * Reverse stock when purchase is deleted
     */
    public function reverseStock(): void
    {
        $product = $this->product;
        if ($product) {
            $product->decrement('stock', $this->converted_qty);
        }
    }

    // ── Scopes ───────────────────────────────────────────────

    public function scopeForProduct($query, int $productId)
    {
        return $query->where('product_id', $productId);
    }

    public function scopeBetweenDates($query, string $from, string $to)
    {
        return $query->whereBetween('purchase_date', [$from, $to]);
    }
}
