<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class EggEntry extends Model
{
    use HasFactory;

    protected $fillable = [
        'entry_date',
        'opening_stock',
        'fresh_arrivals',
        'eggs_sold',
        'damaged_eggs',
        'cost_per_egg',
        'selling_price',
        'notes',
    ];

    protected $casts = [
        'entry_date'    => 'date:Y-m-d',
        'opening_stock' => 'integer',
        'fresh_arrivals'=> 'integer',
        'eggs_sold'     => 'integer',
        'damaged_eggs'  => 'integer',
        'cost_per_egg'  => 'float',
        'selling_price' => 'float',
    ];

    // Computed fields appended to JSON
    protected $appends = [
        'closing_stock',
        'revenue',
        'profit',
        'total_stock'
    ];

    // ── Accessors (computed) ─────────────────────────────────

    public function getClosingStockAttribute(): int
    {
        return max(0,
            $this->opening_stock
            + $this->fresh_arrivals
            - $this->eggs_sold
            - $this->damaged_eggs
        );
    }

    public function getTotalStockAttribute(): int
    {
        return $this->opening_stock + $this->fresh_arrivals;
    }

    public function getRevenueAttribute(): float
    {
        return round($this->eggs_sold * $this->selling_price, 2);
    }

    public function getProfitAttribute(): float
    {
        $revenue   = $this->eggs_sold * $this->selling_price;
        $totalCost = ($this->eggs_sold + $this->damaged_eggs) * $this->cost_per_egg;
        return round($revenue - $totalCost, 2);
    }

    // ── Scopes ───────────────────────────────────────────────

    public function scopeBetweenDates($query, string $from, string $to)
    {
        return $query->whereBetween('entry_date', [$from, $to]);
    }

    public function scopeForMonth($query, int $year, int $month)
    {
        return $query->whereYear('entry_date', $year)
                     ->whereMonth('entry_date', $month);
    }
}
