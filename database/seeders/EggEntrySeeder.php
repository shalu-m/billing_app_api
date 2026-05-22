<?php

namespace Database\Seeders;

use App\Models\EggDailyEntry;
use App\Models\EggStockIntake;
use App\Services\EggStockCalculator;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class EggEntrySeeder extends Seeder
{
    public function run(): void
    {
        DB::table('egg_sale_lines')->delete();
        DB::table('egg_daily_entries')->delete();
        DB::table('egg_stock_intakes')->delete();

        $calculator = app(EggStockCalculator::class);

        $intakes = [
            [
                'intake_date' => '2026-05-11',
                'trays_received' => 50,
                'loose_eggs_received' => 20,
                'eggs_per_tray' => 30,
                'cost_per_tray' => 120,
                'supplier_name' => 'Weekly supplier',
                'notes' => 'Monday stock',
            ],
            [
                'intake_date' => '2026-05-14',
                'trays_received' => 50,
                'loose_eggs_received' => 0,
                'eggs_per_tray' => 30,
                'cost_per_tray' => 130,
                'supplier_name' => 'Weekly supplier',
                'notes' => 'Thursday stock',
            ],
        ];

        foreach ($intakes as $intake) {
            $costPerEgg = $intake['cost_per_tray'] / $intake['eggs_per_tray'];
            $totalEggs = (int) round(($intake['trays_received'] * $intake['eggs_per_tray']) + $intake['loose_eggs_received']);
            $totalCost = ((float) $intake['trays_received'] * (float) $intake['cost_per_tray']) + ($intake['loose_eggs_received'] * $costPerEgg);

            EggStockIntake::create([
                ...$intake,
                'total_eggs' => $totalEggs,
                'total_cost' => round($totalCost, 2),
                'cost_per_egg' => round($costPerEgg, 4),
            ]);
        }

        $entries = [
            [
                'entry_date' => '2026-05-11',
                'damaged_eggs' => 5,
                'notes' => 'Monday sales',
                'sale_lines' => [
                    ['price_per_egg' => 4.50, 'trays_sold' => 10, 'loose_eggs_sold' => 0, 'eggs_per_tray' => 30],
                    ['price_per_egg' => 4.25, 'trays_sold' => 5, 'loose_eggs_sold' => 0, 'eggs_per_tray' => 30],
                ],
            ],
            [
                'entry_date' => '2026-05-12',
                'damaged_eggs' => 0,
                'notes' => null,
                'sale_lines' => [
                    ['price_per_egg' => 4.50, 'trays_sold' => 13, 'loose_eggs_sold' => 10, 'eggs_per_tray' => 30],
                ],
            ],
            [
                'entry_date' => '2026-05-13',
                'damaged_eggs' => 0,
                'notes' => null,
                'sale_lines' => [
                    ['price_per_egg' => 4.25, 'trays_sold' => 10, 'loose_eggs_sold' => 0, 'eggs_per_tray' => 30],
                ],
            ],
            [
                'entry_date' => '2026-05-14',
                'damaged_eggs' => 0,
                'notes' => 'Thursday intake added to leftover stock',
                'sale_lines' => [
                    ['price_per_egg' => 4.50, 'trays_sold' => 16, 'loose_eggs_sold' => 20, 'eggs_per_tray' => 30],
                    ['price_per_egg' => 4.25, 'trays_sold' => 6, 'loose_eggs_sold' => 20, 'eggs_per_tray' => 30],
                ],
            ],
        ];

        foreach ($entries as $entryData) {
            $values = $calculator->calculateEntryValues(
                $entryData['entry_date'],
                $entryData['sale_lines'],
                $entryData['damaged_eggs']
            );

            unset($values['closing_stock_raw']);

            $entry = EggDailyEntry::create([
                'entry_date' => $entryData['entry_date'],
                ...$values,
                'notes' => $entryData['notes'],
            ]);

            foreach ($entryData['sale_lines'] as $line) {
                $quantity = isset($line['quantity'])
                    ? (int) $line['quantity']
                    : (int) round(($line['trays_sold'] * $line['eggs_per_tray']) + $line['loose_eggs_sold']);

                $entry->saleLines()->create([
                    'trays_sold' => $line['trays_sold'] ?? 0,
                    'loose_eggs_sold' => $line['loose_eggs_sold'] ?? 0,
                    'eggs_per_tray' => $line['eggs_per_tray'] ?? 30,
                    'price_per_egg' => $line['price_per_egg'],
                    'quantity' => $quantity,
                    'total_amount' => round($line['price_per_egg'] * $quantity, 2),
                ]);
            }
        }

        $this->command->info('Egg intakes seeded: '.count($intakes));
        $this->command->info('Egg daily entries seeded: '.count($entries));
    }
}
