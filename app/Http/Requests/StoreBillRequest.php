<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreBillRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'customer_name'            => 'nullable|string|max:150',
            'payment_method'           => 'required|in:Cash,UPI,Card',
            'amount_received'          => 'nullable|numeric|min:0',
            'notes'                    => 'nullable|string|max:500',

            'items'                    => 'required|array|min:1',
            'items.*.product_id'       => 'nullable|exists:products,id',
            'items.*.product_name'     => 'required|string|max:200',
            'items.*.unit'             => 'required|string|max:50',
            'items.*.unit_price'       => 'required|numeric|min:0',
            'items.*.quantity'         => 'required|integer|min:1',
            'items.*.discount'         => 'nullable|numeric|min:0',
            'items.*.sgst_percent'     => 'nullable|numeric|min:0|max:100',
            'items.*.cgst_percent'     => 'nullable|numeric|min:0|max:100',
        ];
    }

    public function messages(): array
    {
        return [
            'items.required'           => 'At least one item is required.',
            'items.*.product_name.required' => 'Each item must have a product name.',
            'items.*.quantity.min'     => 'Quantity must be at least 1.',
        ];
    }
}
