<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EggStockIntake extends Model
{
    use HasFactory;

    protected $fillable = [
        'intake_date',
        'trays_received',
        'loose_eggs_received',
        'free_trays',
        'free_loose_eggs',
        'free_eggs',
        'purchased_eggs',
        'eggs_per_tray',
        'total_eggs',
        'cost_per_tray',
        'total_cost',
        'cost_per_egg',
        'supplier_name',
        'notes',
    ];

    protected $casts = [
        'intake_date'         => 'date:Y-m-d',
        'trays_received'      => 'float',
        'loose_eggs_received' => 'integer',
        'free_trays'          => 'float',
        'free_loose_eggs'     => 'integer',
        'free_eggs'           => 'integer',
        'purchased_eggs'      => 'integer',
        'eggs_per_tray'       => 'integer',
        'total_eggs'          => 'integer',
        'cost_per_tray'       => 'float',
        'total_cost'          => 'float',
        'cost_per_egg'        => 'float',
        'created_at'          => 'datetime',
        'updated_at'          => 'datetime',
    ];

    public function scopeBetweenDates($query, string $from, string $to)
    {
        return $query->whereBetween('intake_date', [$from, $to]);
    }
}
