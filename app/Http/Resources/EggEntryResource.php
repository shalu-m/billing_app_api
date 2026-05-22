<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EggEntryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'entry_date' => $this->entry_date->toDateString(),
            'opening_stock' => $this->opening_stock,
            'new_stock_today' => $this->new_stock_today,
            'total_eggs_sold' => $this->total_eggs_sold,
            'damaged_eggs' => $this->damaged_eggs,
            'closing_stock' => $this->closing_stock,
            'avg_cost_per_egg' => $this->avg_cost_per_egg,
            'total_cost' => $this->total_cost,
            'total_revenue' => $this->total_revenue,
            'gross_profit' => $this->gross_profit,
            'notes' => $this->notes,
            'sale_lines' => EggSaleLineResource::collection($this->whenLoaded('saleLines')),
            'created_at' => $this->created_at?->toDateTimeString(),
            'updated_at' => $this->updated_at?->toDateTimeString(),

            'fresh_arrivals' => $this->new_stock_today,
            'eggs_sold' => $this->total_eggs_sold,
            'cost_per_egg' => $this->avg_cost_per_egg,
            'revenue' => $this->total_revenue,
            'profit' => $this->gross_profit,
            'total_stock' => $this->opening_stock,
        ];
    }
}
