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
            'sell_mode'    => $this->sell_mode,
            'unit_price'   => $this->unit_price,
            'cost_price'   => $this->cost_price,
            'quantity'     => $this->quantity,
            'stock_qty'    => $this->stock_qty,
            'discount'     => $this->discount,
            'sgst_percent' => $this->sgst_percent,
            'cgst_percent' => $this->cgst_percent,
            'sgst_amount'  => $this->sgst_amount,
            'cgst_amount'  => $this->cgst_amount,
            'line_total'   => $this->line_total,
            'line_profit'  => $this->line_profit,
        ];
    }
}
