<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EggSaleLine extends Model
{
    use HasFactory;

    protected $fillable = [
        'daily_entry_id',
        'trays_sold',
        'loose_eggs_sold',
        'eggs_per_tray',
        'price_per_egg',
        'quantity',
        'total_amount',
    ];

    protected $casts = [
        'daily_entry_id' => 'integer',
        'trays_sold' => 'float',
        'loose_eggs_sold' => 'integer',
        'eggs_per_tray' => 'integer',
        'price_per_egg' => 'float',
        'quantity' => 'integer',
        'total_amount' => 'float',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function dailyEntry(): BelongsTo
    {
        return $this->belongsTo(EggDailyEntry::class, 'daily_entry_id');
    }
}
