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
        'sell_mode',
        'unit_price',
        'cost_price',
        'quantity',
        'stock_qty',
        'discount',
        'sgst_percent',
        'cgst_percent',
        'sgst_amount',
        'cgst_amount',
        'line_total',
        'line_profit',
    ];

    protected $casts = [
        'unit_price'     => 'float',
        'cost_price'     => 'float',
        'quantity'       => 'float',
        'stock_qty'      => 'float',
        'discount'       => 'float',
        'sgst_percent'   => 'float',
        'cgst_percent'   => 'float',
        'sgst_amount'    => 'float',
        'cgst_amount'    => 'float',
        'line_total'     => 'float',
        'line_profit'    => 'float',
    ];

    protected $appends = ['margin_percent'];

    // ── Relationships ────────────────────────────────────────

    public function bill()
    {
        return $this->belongsTo(Bill::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class)->withTrashed();
    }

    // ── Accessors ────────────────────────────────────────────

    public function getMarginPercentAttribute(): float
    {
        $lineBase = $this->line_total - $this->sgst_amount - $this->cgst_amount;
        if ($lineBase <= 0) {
            return 0;
        }
        $cost = $this->cost_price * $this->quantity;
        return round((($lineBase - $cost) / $lineBase) * 100, 2);
    }

    // ── Helpers ──────────────────────────────────────────────

    /**
     * Calculate all amounts for a given item payload.
     * Returns array ready to insert into bill_items.
     */
    public static function calculateAmounts(array $item): array
    {
        $gross       = round((float) $item['unit_price'] * (float) $item['quantity'], 2);
        $discount    = min(max((float) ($item['discount'] ?? 0), 0), $gross);
        $base        = max(0, $gross - $discount);
        $sgstAmount  = round(($item['sgst_percent'] / 100) * $base, 2);
        $cgstAmount  = round(($item['cgst_percent'] / 100) * $base, 2);
        $lineTotal   = round($base + $sgstAmount + $cgstAmount, 2);

        // Calculate line profit: (unit_price - cost_price) * quantity - discount
        $costPrice = $item['cost_price'] ?? 0;
        $lineProfit = $costPrice ? round(($item['unit_price'] - $costPrice) * $item['quantity'] - $discount, 2) : 0;

        return array_merge($item, [
            'discount'     => $discount,
            'sgst_amount'  => $sgstAmount,
            'cgst_amount'  => $cgstAmount,
            'line_total'   => $lineTotal,
            'line_profit'  => $lineProfit,
        ]);
    }
}
