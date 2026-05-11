<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StockIntakeResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'                     => $this->id,
            'product_id'             => $this->product_id,
            'product'                => new ProductResource($this->whenLoaded('product')),
            'received_qty'           => $this->received_qty,
            'received_unit'          => $this->received_unit,
            'cost_per_unit'          => $this->cost_per_unit,
            'qty_added_in_base_unit' => $this->qty_added_in_base_unit,
            'notes'                  => $this->notes,
            'created_at'             => $this->created_at,
            'updated_at'             => $this->updated_at,
        ];
    }
}
