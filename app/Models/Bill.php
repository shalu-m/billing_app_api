<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Bill extends Model
{
    use HasFactory;

    protected $fillable = [
        'bill_number',
        'customer_name',
        'payment_method',
        'subtotal',
        'total_discount',
        'total_sgst',
        'total_cgst',
        'grand_total',
        'amount_received',
        'change_returned',
        'notes',
    ];

    protected $casts = [
        'subtotal'        => 'float',
        'total_discount'  => 'float',
        'total_sgst'      => 'float',
        'total_cgst'      => 'float',
        'grand_total'     => 'float',
        'amount_received' => 'float',
        'change_returned' => 'float',
        'created_at'      => 'datetime',
        'updated_at'      => 'datetime',
    ];

    // ── Relationships ────────────────────────────────────────

    public function items()
    {
        return $this->hasMany(BillItem::class);
    }

    // ── Static Helpers ───────────────────────────────────────

    /**
     * Generate a unique sequential bill number: B-YYYY-001
     */
    public static function generateBillNumber(): string
    {
        $year      = now()->year;
        $lastBill  = static::whereYear('created_at', $year)
                           ->orderByDesc('id')
                           ->first();

        $sequence = $lastBill
            ? ((int) substr($lastBill->bill_number, -3)) + 1
            : 1;

        return sprintf('B-%d-%03d', $year, $sequence);
    }

    // ── Scopes ───────────────────────────────────────────────

    public function scopeForDate($query, string $date)
    {
        return $query->whereDate('created_at', $date);
    }

    public function scopeBetweenDates($query, string $from, string $to)
    {
        return $query->whereBetween('created_at', [
            $from . ' 00:00:00',
            $to   . ' 23:59:59',
        ]);
    }

    public function scopeSearch($query, string $term)
    {
        return $query->where(function ($q) use ($term) {
            $q->where('bill_number', 'like', "%{$term}%")
              ->orWhere('customer_name', 'like', "%{$term}%");
        });
    }
}
