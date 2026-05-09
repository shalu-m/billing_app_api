<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EggEntryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'             => $this->id,
            'entry_date'     => $this->entry_date->toDateString(),
            'opening_stock'  => $this->opening_stock,
            'fresh_arrivals' => $this->fresh_arrivals,
            'eggs_sold'      => $this->eggs_sold,
            'damaged_eggs'   => $this->damaged_eggs,
            'closing_stock'  => $this->closing_stock,   // computed accessor
            'total_stock'    => $this->total_stock,    // computed accessor
            'cost_per_egg'   => $this->cost_per_egg,
            'selling_price'  => $this->selling_price,
            'revenue'        => $this->revenue,          // computed accessor
            'profit'         => $this->profit,           // computed accessor
            'notes'          => $this->notes,
            'created_at'     => $this->created_at?->toDateTimeString(),
        ];
    }
}
