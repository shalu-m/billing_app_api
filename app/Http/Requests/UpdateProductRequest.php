<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Log;

class UpdateProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $productId = $this->route('product')?->id;

        return [
            'name'          => 'sometimes|required|string|max:200',
            'unit'          => 'sometimes|required|string|max:50',
            'cost_price'    => 'sometimes|required|numeric|min:0',
            'selling_price' => 'sometimes|required|numeric|min:0',
            'sgst'          => 'nullable|numeric|min:0|max:100',
            'cgst'          => 'nullable|numeric|min:0|max:100',
            'stock'         => 'sometimes|required|integer|min:0',
            'barcode'       => "nullable|string|max:100|unique:products,barcode,{$productId}",
            'is_active'     => 'nullable|boolean',
        ];
    }
}
