<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EggStockIntakeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'intake_date' => $this->intake_date->toDateString(),
            'trays_received' => $this->trays_received,
            'loose_eggs_received' => $this->loose_eggs_received,
            'eggs_per_tray' => $this->eggs_per_tray,
            'total_eggs' => $this->total_eggs,
            'cost_per_tray' => $this->cost_per_tray,
            'total_cost' => $this->total_cost,
            'cost_per_egg' => $this->cost_per_egg,
            'supplier_name' => $this->supplier_name,
            'notes' => $this->notes,
            'created_at' => $this->created_at?->toDateTimeString(),
            'updated_at' => $this->updated_at?->toDateTimeString(),
        ];
    }
}
