<?php

namespace App\Services;

use App\Models\EggDailyEntry;
use App\Models\EggStockIntake;
use Illuminate\Support\Collection;

class EggStockCalculator
{
    /**
     * Calculate opening stock and stock layers for a given date.
     * Opening stock = Previous closing stock + All new intakes (FIFO layers)
     * Stock layers are returned for FIFO-based profit calculation
     *
     * @param string $date The date to calculate opening stock for (Y-m-d format)
     * @param int|null $ignoreEntryId Entry to ignore (for updates)
     * @return array Opening stock details with stock layers
     */
    public function openingStockForDate(string $date, ?int $ignoreEntryId = null): array
    {
        // Get the most recent entry before or on this date
        $previousQuery = EggDailyEntry::whereDate('entry_date', '<', $date)
            ->orderByDesc('entry_date');

        if ($ignoreEntryId) {
            $previousQuery->whereKeyNot($ignoreEntryId);
        }

        $previousEntry = $previousQuery->first();
        $previousClosing = $previousEntry ? (int) $previousEntry->closing_stock : 0;

        // Get remaining stock layers (FIFO) after all previous sales
        $layers = $this->remainingLayersForDate($date, $ignoreEntryId);
        $openingStock = (int) collect($layers)->sum('quantity');

        // Get intakes between last entry and today
        $intakeSinceLastEntryQuery = EggStockIntake::whereDate('intake_date', '<=', $date);

        if ($previousEntry) {
            $intakeSinceLastEntryQuery->whereDate('intake_date', '>', $previousEntry->entry_date->toDateString());
        }

        $intakesSinceLastEntry = $intakeSinceLastEntryQuery->orderBy('intake_date')->orderBy('id')->get();

        // Get intakes for today specifically
        $todayIntakes = EggStockIntake::whereDate('intake_date', $date)
            ->orderBy('id')
            ->get();

        $intakeSinceLastEntryQty = (int) $intakesSinceLastEntry->sum('total_eggs');
        $todayIntakeQty = (int) $todayIntakes->sum('total_eggs');

        return [
            'date' => $date,
            'previous_closing' => $previousClosing,
            'new_intake' => max(0, $openingStock - $previousClosing), // Intakes since last entry
            'today_intake' => $todayIntakeQty,
            'opening_stock' => $openingStock, // Previous closing + all new intakes
            'avg_cost_per_egg' => $this->weightedAverageCost($layers),
            'intakes' => $intakesSinceLastEntry,
            'today_intakes' => $todayIntakes,
            'stock_layers' => $layers, // FIFO layers for profit calculation
        ];
    }

    /**
     * Calculate all values for an egg entry (stock, cost, profit)
     * Uses FIFO method for cost calculation
     *
     * @param string $date Entry date (Y-m-d format)
     * @param iterable $saleLines Sale line items with quantity and price
     * @param int $damagedEggs Number of damaged eggs
     * @param int|null $ignoreEntryId Entry to ignore (for updates)
     * @return array Entry values including cost using FIFO
     */
    public function calculateEntryValues(
        string $date,
        iterable $saleLines,
        int $damagedEggs,
        ?int $ignoreEntryId = null
    ): array {
        $opening = $this->openingStockForDate($date, $ignoreEntryId);
        $sales = $this->saleTotals($saleLines);
        $closingStock = $opening['opening_stock'] - $sales['total_eggs_sold'] - $damagedEggs;

        // Use FIFO to get actual cost (includes both sold and damaged eggs)
        $costLayers = $opening['stock_layers'];
        $totalConsumed = $sales['total_eggs_sold'] + $damagedEggs;
        $totalCost = $this->calculateFifoCost($costLayers, $totalConsumed);

        // Calculate effective cost per egg (for reporting - only based on sold eggs)
        $effectiveCostPerEgg = $sales['total_eggs_sold'] > 0
            ? $totalCost / ($sales['total_eggs_sold'] + $damagedEggs)
            : $opening['avg_cost_per_egg'];

        $grossProfit = $sales['total_revenue'] - $totalCost;

        return [
            'opening_stock' => $opening['opening_stock'],
            'new_stock_today' => $opening['today_intake'],
            'total_eggs_sold' => $sales['total_eggs_sold'],
            'damaged_eggs' => $damagedEggs,
            'closing_stock_raw' => $closingStock,
            'closing_stock' => max(0, $closingStock),
            'avg_cost_per_egg' => round($effectiveCostPerEgg, 4),
            'total_cost' => round($totalCost, 2),
            'total_revenue' => round($sales['total_revenue'], 2),
            'gross_profit' => round($grossProfit, 2),
        ];
    }

    /**
     * Recalculate all entries from a given date onwards
     * Used after intakes or entries are created/updated/deleted
     *
     * @param string $date Start recalculation from this date (Y-m-d format)
     */
    public function recalculateEntriesFrom(string $date): void
    {
        $entries = EggDailyEntry::with('saleLines')
            ->whereDate('entry_date', '>=', $date)
            ->orderBy('entry_date')
            ->get();

        foreach ($entries as $entry) {
            $values = $this->calculateEntryValues(
                $entry->entry_date->toDateString(),
                $entry->saleLines,
                (int) $entry->damaged_eggs,
                $entry->id
            );

            unset($values['closing_stock_raw']);

            $entry->forceFill($values)->save();
        }
    }

    /**
     * Calculate total sales from line items
     *
     * @param iterable $saleLines Sale line items
     * @return array ['total_eggs_sold' => int, 'total_revenue' => float]
     */
    private function saleTotals(iterable $saleLines): array
    {
        $lines = $saleLines instanceof Collection ? $saleLines : collect($saleLines);

        $totalSold = (int) $lines->sum(fn ($line) => $this->lineQuantity($line));
        $totalRevenue = (float) $lines->sum(
            fn ($line) => (float) data_get($line, 'price_per_egg', 0) * $this->lineQuantity($line)
        );

        return [
            'total_eggs_sold' => $totalSold,
            'total_revenue' => $totalRevenue,
        ];
    }

    /**
     * Calculate quantity from a sale line (can be in trays or loose eggs)
     *
     * @param mixed $line Sale line item
     * @return int Total eggs
     */
    private function lineQuantity(mixed $line): int
    {
        $trays = (float) data_get($line, 'trays_sold', 0);
        $looseEggs = (int) data_get($line, 'loose_eggs_sold', 0);
        $eggsPerTray = (int) data_get($line, 'eggs_per_tray', 30);

        if ($trays > 0 || $looseEggs > 0) {
            return (int) round(($trays * $eggsPerTray) + $looseEggs);
        }

        return (int) data_get($line, 'quantity', 0);
    }

    /**
     * Get remaining stock layers after consumption up to a date
     * Implements FIFO: older intakes are consumed first
     *
     * @param string $date Calculate layers up to this date (Y-m-d format)
     * @param int|null $ignoreEntryId Entry to ignore (for updates)
     * @return array Stock layers [['intake_id', 'intake_date', 'quantity', 'cost_per_egg'], ...]
     */
    private function remainingLayersForDate(string $date, ?int $ignoreEntryId = null): array
    {
        // Initialize layers with all intakes up to this date
        $layers = EggStockIntake::whereDate('intake_date', '<=', $date)
            ->orderBy('intake_date')
            ->orderBy('id')
            ->get()
            ->map(fn (EggStockIntake $intake) => [
                'intake_id' => $intake->id,
                'intake_date' => $intake->intake_date->toDateString(),
                'quantity' => (int) $intake->total_eggs,
                'cost_per_egg' => (float) $intake->cost_per_egg,
            ])
            ->all();

        // Get all entries before this date (in order)
        $entriesQuery = EggDailyEntry::whereDate('entry_date', '<', $date)
            ->orderBy('entry_date')
            ->orderBy('id');

        if ($ignoreEntryId) {
            $entriesQuery->whereKeyNot($ignoreEntryId);
        }

        // Consume layers based on each entry (FIFO)
        $entries = $entriesQuery->get();
        foreach ($entries as $entry) {
            $quantityToConsume = (int) $entry->total_eggs_sold + (int) $entry->damaged_eggs;
            $this->calculateFifoCost($layers, $quantityToConsume);
        }

        return $layers;
    }

    /**
     * Calculate cost using FIFO method and consume from layers array
     * Modifies the $layers array by consuming quantities
     *
     * @param array $layers Mutable reference to layers array
     * @param int $quantity Number of eggs to consume
     * @return float Total cost for the consumed quantity
     */
    private function calculateFifoCost(array &$layers, int $quantity): float
    {
        $cost = 0;
        $remainingToConsume = $quantity;

        foreach ($layers as &$layer) {
            if ($remainingToConsume <= 0) {
                break;
            }

            if ($layer['quantity'] <= 0) {
                continue;
            }

            // Take as much as possible from this layer
            $taken = min($layer['quantity'], $remainingToConsume);
            $cost += $taken * $layer['cost_per_egg'];
            $layer['quantity'] -= $taken;
            $remainingToConsume -= $taken;
        }

        unset($layer);

        // Remove empty layers
        $layers = array_values(array_filter($layers, fn ($layer) => $layer['quantity'] > 0));

        return $cost;
    }

    /**
     * Calculate weighted average cost per egg from layers
     *
     * @param array $layers Stock layers
     * @return float Average cost per egg
     */
    private function weightedAverageCost(array $layers): float
    {
        $eggs = (int) collect($layers)->sum('quantity');
        $cost = (float) collect($layers)->sum(fn ($layer) => $layer['quantity'] * $layer['cost_per_egg']);

        return $eggs > 0 ? $cost / $eggs : 0;
    }
}
