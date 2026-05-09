<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'             => $this->id,
            'name'           => $this->name,
            'unit'           => $this->unit,
            'cost_price'     => $this->cost_price,
            'selling_price'  => $this->selling_price,
            'sgst'           => $this->sgst,
            'cgst'           => $this->cgst,
            'total_gst'      => $this->total_gst,
            'stock'          => $this->stock,
            'barcode'        => $this->barcode,
            'is_active'      => $this->is_active,
            'margin_percent' => $this->margin_percent,
            'created_at'     => $this->created_at?->toDateTimeString(),
            'updated_at'     => $this->updated_at?->toDateTimeString(),
        ];
    }
}
