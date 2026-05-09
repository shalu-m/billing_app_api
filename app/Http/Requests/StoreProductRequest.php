<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'          => 'required|string|max:200',
            'unit'          => 'required|string|max:50',
            'cost_price'    => 'required|numeric|min:0',
            'selling_price' => 'required|numeric|min:0|gte:cost_price',
            'sgst'          => 'nullable|numeric|min:0|max:100',
            'cgst'          => 'nullable|numeric|min:0|max:100',
            'stock'         => 'required|integer|min:0',
            'barcode'       => 'nullable|string|max:100|unique:products,barcode',
            'is_active'     => 'nullable|boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'selling_price.gte' => 'Selling price must be greater than or equal to cost price.',
            'barcode.unique'    => 'This barcode is already assigned to another product.',
        ];
    }
}
