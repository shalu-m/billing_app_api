<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

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
            'name'            => 'sometimes|required|string|max:200',
            'unit'            => 'sometimes|required|string|max:50',
            'cost_price'      => 'sometimes|required|numeric|min:0',
            'selling_price'   => 'sometimes|required|numeric|min:0',
            'wholesale_price' => 'nullable|numeric|min:0',
            'wholesale_cost'  => 'nullable|numeric|min:0',
            'sgst'            => 'nullable|numeric|min:0|max:100',
            'cgst'            => 'nullable|numeric|min:0|max:100',
            'purchase_unit'   => 'nullable|string|max:50',
            'purchase_qty'    => 'required_with:purchase_unit|nullable|numeric|min:0.01',
            'barcode'         => "nullable|string|max:100|unique:products,barcode,{$productId}",
            'is_active'       => 'nullable|boolean',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $product = $this->route('product');
            $cost = $this->has('cost_price') ? (float) $this->input('cost_price') : (float) ($product?->cost_price ?? 0);
            $selling = $this->has('selling_price') ? (float) $this->input('selling_price') : (float) ($product?->selling_price ?? 0);

            if ($selling < $cost) {
                $validator->errors()->add('selling_price', 'Selling price must be greater than or equal to cost price.');
            }
        });
    }
}
