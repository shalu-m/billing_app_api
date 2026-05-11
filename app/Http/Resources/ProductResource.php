<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'               => $this->id,
            'name'             => $this->name,
            'unit'             => $this->unit,
            'cost_price'       => $this->cost_price,
            'selling_price'    => $this->selling_price,
            'wholesale_price'  => $this->wholesale_price,
            'wholesale_cost'   => $this->wholesale_cost,
            'purchase_unit'    => $this->purchase_unit,
            'purchase_qty'     => $this->purchase_qty,
            'sgst'             => $this->sgst,
            'cgst'             => $this->cgst,
            'total_gst'        => $this->total_gst,
            'stock'            => $this->stock,
            'barcode'          => $this->barcode,
            'is_active'        => $this->is_active,
            'margin_percent'   => $this->margin_percent,
            'is_bulk_product'  => $this->isBulkProduct(),
            'computed_wholesale_price' => $this->getEffectiveWholesalePrice(),
            'computed_wholesale_cost' => $this->getEffectiveWholesaleCost(),
            'created_at'       => $this->created_at?->toDateTimeString(),
            'updated_at'       => $this->updated_at?->toDateTimeString(),
        ];
    }
}
