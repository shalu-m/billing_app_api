<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'unit',
        'cost_price',
        'selling_price',
        'wholesale_price',
        'wholesale_cost',
        'sgst',
        'cgst',
        'stock',
        'purchase_unit',
        'purchase_qty',
        'barcode',
        'is_active',
    ];

    protected $casts = [
        'cost_price'      => 'float',
        'selling_price'   => 'float',
        'wholesale_price' => 'float',
        'wholesale_cost'  => 'float',
        'sgst'            => 'float',
        'cgst'            => 'float',
        'stock'           => 'float',
        'purchase_qty'    => 'float',
        'is_active'       => 'boolean',
    ];

    protected $appends = ['margin_percent', 'total_gst'];

    // ── Relationships ────────────────────────────────────────

    public function billItems()
    {
        return $this->hasMany(BillItem::class);
    }

    public function purchases()
    {
        return $this->hasMany(Purchase::class);
    }

    // ── Accessors ────────────────────────────────────────────

    public function getMarginPercentAttribute(): float
    {
        if ($this->selling_price <= 0) return 0;
        return round((($this->selling_price - $this->cost_price) / $this->selling_price) * 100, 2);
    }

    public function getTotalGstAttribute(): float
    {
        return round($this->sgst + $this->cgst, 2);
    }

    // ── Helper Methods ───────────────────────────────────────

    /**
     * Check if product has wholesale (bulk) support
     */
    public function isBulkProduct(): bool
    {
        return !is_null($this->purchase_unit) && $this->purchase_qty > 0;
    }

    /**
     * Get effective wholesale price (auto-calculated if null)
     */
    public function getEffectiveWholesalePrice(): float
    {
        return $this->wholesale_price ?? ($this->selling_price * $this->purchase_qty);
    }

    /**
     * Get effective wholesale cost (auto-calculated if null)
     */
    public function getEffectiveWholesaleCost(): float
    {
        return $this->wholesale_cost ?? ($this->cost_price * $this->purchase_qty);
    }

    /**
     * Calculate how much to deduct from base stock based on sale unit
     */
    public function calculateBaseUnitDeduction(string $saleUnit, float $qty): float
    {
        if ($saleUnit === $this->unit || $saleUnit === 'piece') {
            return $qty;
        }

        if ($saleUnit === $this->purchase_unit && $this->purchase_qty > 0) {
            return $qty * $this->purchase_qty;
        }

        return $qty;
    }



    // ── Scopes ───────────────────────────────────────────────

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeSearch($query, string $term)
    {
        return $query->where(function ($q) use ($term) {
            $q->where('name', 'like', "%{$term}%")
              ->orWhere('barcode', $term);
        });
    }

    public function scopeLowStock($query, int $threshold = 20)
    {
        return $query->where('stock', '<=', $threshold);
    }
}
