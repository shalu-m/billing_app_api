<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreEggEntryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'entry_date'    => 'required|date|unique:egg_entries,entry_date',
            'opening_stock' => 'required|integer|min:0',
            'fresh_arrivals'=> 'required|integer|min:0',
            'eggs_sold'     => 'required|integer|min:0',
            'damaged_eggs'  => 'required|integer|min:0',
            'cost_per_egg'  => 'required|numeric|min:0',
            'selling_price' => 'required|numeric|min:0|gte:cost_per_egg',
            'notes'         => 'nullable|string|max:500',
        ];
    }

    public function messages(): array
    {
        return [
            'entry_date.unique'      => 'An entry for this date already exists.',
            'selling_price.gte'      => 'Selling price must be greater than or equal to cost per egg.',
        ];
    }
}
