<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EggDailyEntry extends Model
{
    use HasFactory;

    protected $fillable = [
        'entry_date',
        'opening_stock',
        'new_stock_today',
        'total_eggs_sold',
        'damaged_eggs',
        'closing_stock',
        'avg_cost_per_egg',
        'total_cost',
        'total_revenue',
        'gross_profit',
        'notes',
    ];

    protected $casts = [
        'entry_date' => 'date:Y-m-d',
        'opening_stock' => 'integer',
        'new_stock_today' => 'integer',
        'total_eggs_sold' => 'integer',
        'damaged_eggs' => 'integer',
        'closing_stock' => 'integer',
        'avg_cost_per_egg' => 'float',
        'total_cost' => 'float',
        'total_revenue' => 'float',
        'gross_profit' => 'float',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function saleLines(): HasMany
    {
        return $this->hasMany(EggSaleLine::class, 'daily_entry_id');
    }

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
