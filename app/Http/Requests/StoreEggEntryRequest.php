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
            'entry_date' => 'required|date|unique:egg_daily_entries,entry_date',
            'damaged_eggs' => 'nullable|integer|min:0',
            'notes' => 'nullable|string|max:1000',
            'sale_lines' => 'required|array|min:1',
            'sale_lines.*.price_per_egg' => 'required|numeric|gt:0',
            'sale_lines.*.trays_sold' => 'nullable|numeric|min:0',
            'sale_lines.*.loose_eggs_sold' => 'nullable|integer|min:0',
            'sale_lines.*.eggs_per_tray' => 'nullable|integer|min:1',
            'sale_lines.*.quantity' => 'nullable|integer|min:1',
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            foreach ($this->input('sale_lines', []) as $index => $line) {
                $trays = (float) ($line['trays_sold'] ?? 0);
                $looseEggs = (int) ($line['loose_eggs_sold'] ?? 0);
                $quantity = (int) ($line['quantity'] ?? 0);

                if ($trays <= 0 && $looseEggs <= 0 && $quantity <= 0) {
                    $validator->errors()->add("sale_lines.{$index}.quantity", 'Enter trays, loose eggs, or total eggs.');
                }
            }
        });
    }

    public function messages(): array
    {
        return [
            'entry_date.unique' => 'An entry for this date already exists.',
            'sale_lines.required' => 'Add at least one selling rate.',
            'sale_lines.min' => 'Add at least one selling rate.',
        ];
    }
}
