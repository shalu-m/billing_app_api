<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BillResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'              => $this->id,
            'bill_number'     => $this->bill_number,
            'customer_name'   => $this->customer_name,
            'payment_method'  => $this->payment_method,
            'subtotal'        => $this->subtotal,
            'total_discount'  => $this->total_discount,
            'total_sgst'      => $this->total_sgst,
            'total_cgst'      => $this->total_cgst,
            'grand_total'     => $this->grand_total,
            'amount_received' => $this->amount_received,
            'change_returned' => $this->change_returned,
            'notes'           => $this->notes,
            'items'           => BillItemResource::collection($this->whenLoaded('items')),
            'items_count'     => $this->whenCounted('items'),
            'created_at'      => $this->created_at?->toDateTimeString(),
            'updated_at'      => $this->updated_at?->toDateTimeString(),
        ];
    }
}
