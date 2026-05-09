<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class BillItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'bill_id',
        'product_id',
        'product_name',
        'unit',
        'unit_price',
        'cost_price',
        'quantity',
        'discount',
        'sgst_percent',
        'cgst_percent',
        'sgst_amount',
        'cgst_amount',
        'line_total',
    ];

    protected $casts = [
        'unit_price'   => 'float',
        'cost_price'   => 'float',
        'quantity'     => 'integer',
        'discount'     => 'float',
        'sgst_percent' => 'float',
        'cgst_percent' => 'float',
        'sgst_amount'  => 'float',
        'cgst_amount'  => 'float',
        'line_total'   => 'float',
    ];

    // ── Relationships ────────────────────────────────────────

    public function bill()
    {
        return $this->belongsTo(Bill::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class)->withTrashed();
    }

    // ── Helpers ──────────────────────────────────────────────

    /**
     * Calculate all amounts for a given item payload.
     * Returns array ready to insert into bill_items.
     */
    public static function calculateAmounts(array $item): array
    {
        $base        = ($item['unit_price'] * $item['quantity']) - ($item['discount'] ?? 0);
        $sgstAmount  = round(($item['sgst_percent'] / 100) * $base, 2);
        $cgstAmount  = round(($item['cgst_percent'] / 100) * $base, 2);
        $lineTotal   = round($base + $sgstAmount + $cgstAmount, 2);

        return array_merge($item, [
            'sgst_amount' => $sgstAmount,
            'cgst_amount' => $cgstAmount,
            'line_total'  => $lineTotal,
        ]);
    }
}
