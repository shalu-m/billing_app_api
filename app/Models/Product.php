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
        'sgst',
        'cgst',
        'stock',
        'barcode',
        'is_active',
    ];

    protected $casts = [
        'cost_price'    => 'float',
        'selling_price' => 'float',
        'sgst'          => 'float',
        'cgst'          => 'float',
        'stock'         => 'integer',
        'is_active'     => 'boolean',
    ];

    protected $appends = ['margin_percent', 'total_gst'];

    // ── Relationships ────────────────────────────────────────

    public function billItems()
    {
        return $this->hasMany(BillItem::class);
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
