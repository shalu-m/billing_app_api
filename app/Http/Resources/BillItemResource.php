<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BillItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'           => $this->id,
            'product_id'   => $this->product_id,
            'product_name' => $this->product_name,
            'unit'         => $this->unit,
            'unit_price'   => $this->unit_price,
            'quantity'     => $this->quantity,
            'discount'     => $this->discount,
            'sgst_percent' => $this->sgst_percent,
            'cgst_percent' => $this->cgst_percent,
            'sgst_amount'  => $this->sgst_amount,
            'cgst_amount'  => $this->cgst_amount,
            'line_total'   => $this->line_total,
        ];
    }
}
