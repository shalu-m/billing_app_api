<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EggSaleLineResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'daily_entry_id' => $this->daily_entry_id,
            'trays_sold' => $this->trays_sold,
            'loose_eggs_sold' => $this->loose_eggs_sold,
            'eggs_per_tray' => $this->eggs_per_tray,
            'price_per_egg' => $this->price_per_egg,
            'quantity' => $this->quantity,
            'total_amount' => $this->total_amount,
            'created_at' => $this->created_at?->toDateTimeString(),
            'updated_at' => $this->updated_at?->toDateTimeString(),
        ];
    }
}
