<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateEggEntryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $entryId = $this->route('eggEntry')->id ?? null;

        return [
            'entry_date'    => "sometimes|required|date|unique:egg_entries,entry_date,{$entryId}",
            'opening_stock' => 'sometimes|required|integer|min:0',
            'fresh_arrivals'=> 'sometimes|required|integer|min:0',
            'eggs_sold'     => 'sometimes|required|integer|min:0',
            'damaged_eggs'  => 'sometimes|required|integer|min:0',
            'cost_per_egg'  => 'sometimes|required|numeric|min:0',
            'selling_price' => 'sometimes|required|numeric|min:0',
            'notes'         => 'nullable|string|max:500',
        ];
    }
}
