<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PurchaseResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'              => $this->id,
            'product_id'      => $this->product_id,
            'product'         => new ProductResource($this->whenLoaded('product')),
            'received_unit'   => $this->received_unit,
            'received_qty'    => $this->received_qty,
            'converted_qty'   => $this->converted_qty,
            'cost_per_unit'   => $this->cost_per_unit,
            'total_cost'      => $this->total_cost,
            'purchase_date'   => $this->purchase_date?->toDateString(),
            'supplier_name'   => $this->supplier_name,
            'invoice_number'  => $this->invoice_number,
            'notes'           => $this->notes,
            'created_at'      => $this->created_at?->toDateTimeString(),
            'updated_at'      => $this->updated_at?->toDateTimeString(),
        ];
    }
}
