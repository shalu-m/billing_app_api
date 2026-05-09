<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class EggEntrySeeder extends Seeder
{
    public function run(): void
    {
        DB::table('egg_entries')->truncate();

        $entries = [
            ['entry_date' => '2025-10-07', 'opening_stock' => 10000, 'fresh_arrivals' => 0,    'eggs_sold' => 9000,  'damaged_eggs' => 500, 'cost_per_egg' => 4.50, 'selling_price' => 6.50, 'notes' => null],
            ['entry_date' => '2025-10-06', 'opening_stock' => 12000, 'fresh_arrivals' => 0,    'eggs_sold' => 11000, 'damaged_eggs' => 500, 'cost_per_egg' => 4.50, 'selling_price' => 6.50, 'notes' => null],
            ['entry_date' => '2025-10-05', 'opening_stock' => 8000,  'fresh_arrivals' => 4000, 'eggs_sold' => 2190,  'damaged_eggs' => 0,   'cost_per_egg' => 4.50, 'selling_price' => 6.50, 'notes' => 'New batch arrived'],
            ['entry_date' => '2025-10-04', 'opening_stock' => 6000,  'fresh_arrivals' => 5000, 'eggs_sold' => 2700,  'damaged_eggs' => 300, 'cost_per_egg' => 4.50, 'selling_price' => 6.50, 'notes' => null],
            ['entry_date' => '2025-10-03', 'opening_stock' => 5000,  'fresh_arrivals' => 3000, 'eggs_sold' => 1800,  'damaged_eggs' => 200, 'cost_per_egg' => 4.50, 'selling_price' => 6.50, 'notes' => null],
            ['entry_date' => '2025-10-02', 'opening_stock' => 4500,  'fresh_arrivals' => 2000, 'eggs_sold' => 1500,  'damaged_eggs' => 0,   'cost_per_egg' => 4.00, 'selling_price' => 6.00, 'notes' => null],
            ['entry_date' => '2025-10-01', 'opening_stock' => 3000,  'fresh_arrivals' => 3000, 'eggs_sold' => 1300,  'damaged_eggs' => 200, 'cost_per_egg' => 4.00, 'selling_price' => 6.00, 'notes' => 'Month start'],
        ];

        foreach ($entries as $entry) {
            DB::table('egg_entries')->insert(array_merge($entry, [
                'created_at' => now(),
                'updated_at' => now(),
            ]));
        }

        $this->command->info('✅ Egg entries seeded: ' . count($entries));
    }
}
