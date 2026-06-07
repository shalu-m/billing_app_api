<?php

namespace App\Services;

use App\Models\EggDailyEntry;
use App\Models\EggStockIntake;
use Illuminate\Support\Collection;

class EggStockCalculator
{
    /**
     * Calculate opening stock and stock layers for a given date.
     *
     * Each layer now carries TWO quantity buckets:
     *   - purchased_qty : eggs the owner paid for (has a real cost_per_egg)
     *   - free_qty      : bonus / free eggs (cost_per_egg = 0)
     *
     * Consumption order: purchased_qty first, then free_qty — inside each FIFO layer.
     *
     * @param string   $date          Y-m-d
     * @param int|null $ignoreEntryId Entry to skip (used during updates)
     */
    public function openingStockForDate(string $date, ?int $ignoreEntryId = null): array
    {
        $previousQuery = EggDailyEntry::whereDate('entry_date', '<', $date)
            ->orderByDesc('entry_date');

        if ($ignoreEntryId) {
            $previousQuery->whereKeyNot($ignoreEntryId);
        }

        $previousEntry   = $previousQuery->first();
        $previousClosing = $previousEntry ? (int) $previousEntry->closing_stock : 0;

        // FIFO layers remaining after all prior entries
        $layers      = $this->remainingLayersForDate($date, $ignoreEntryId);
        $openingStock = (int) collect($layers)->sum(fn ($l) => $l['purchased_qty'] + $l['free_qty']);

        // Intakes since last entry (for display only)
        $intakeSinceLastEntryQuery = EggStockIntake::whereDate('intake_date', '<=', $date);
        if ($previousEntry) {
            $intakeSinceLastEntryQuery->whereDate('intake_date', '>', $previousEntry->entry_date->toDateString());
        }
        $intakesSinceLastEntry = $intakeSinceLastEntryQuery->orderBy('intake_date')->orderBy('id')->get();

        $todayIntakes    = EggStockIntake::whereDate('intake_date', $date)->orderBy('id')->get();
        $todayIntakeQty  = (int) $todayIntakes->sum('total_eggs');

        // Total free eggs currently sitting in the remaining layers
        $totalFreeInLayers = (int) collect($layers)->sum('free_qty');

        return [
            'date'                  => $date,
            'previous_closing'      => $previousClosing,
            'new_intake'            => max(0, $openingStock - $previousClosing),
            'today_intake'          => $todayIntakeQty,
            'opening_stock'         => $openingStock,
            'free_stock'            => $totalFreeInLayers,
            'avg_cost_per_egg'      => $this->weightedAverageCost($layers),
            'intakes'               => $intakesSinceLastEntry,
            'today_intakes'         => $todayIntakes,
            'stock_layers'          => $layers,
        ];
    }

    /**
     * Calculate all values for an egg entry (stock, cost, profit).
     *
     * Damage absorption rule:
     *   1. Use free eggs first (no cost).
     *   2. Only leftover damaged eggs count against purchased stock (reduce profit).
     *
     * Sale cost rule:
     *   Eggs sold consume purchased_qty first (cost charged), then free_qty (no cost).
     *
     * @param string   $date
     * @param iterable $saleLines
     * @param int      $damagedEggs
     * @param int|null $ignoreEntryId
     */
    public function calculateEntryValues(
        string $date,
        iterable $saleLines,
        int $damagedEggs,
        ?int $ignoreEntryId = null
    ): array {
        $opening     = $this->openingStockForDate($date, $ignoreEntryId);
        $sales       = $this->saleTotals($saleLines);
        $openingStock = $opening['opening_stock'];
        $closingStock = $openingStock - $sales['total_eggs_sold'] - $damagedEggs;

        // Work on a copy of the layers
        $layers = $opening['stock_layers'];

        // --- Step 1: absorb damaged eggs (free first, then purchased) ---
        $freeAvailable        = (int) collect($layers)->sum('free_qty');
        $damagedFromFree      = min($damagedEggs, $freeAvailable);
        $damagedFromPurchased = max(0, $damagedEggs - $damagedFromFree);

        // Consume free eggs for damage (no cost)
        if ($damagedFromFree > 0) {
            $this->consumeFreeFromLayers($layers, $damagedFromFree);
        }

        // Consume purchased eggs for remaining damage (with cost)
        $damageCost = 0;
        if ($damagedFromPurchased > 0) {
            $damageCost = $this->consumePurchasedFromLayers($layers, $damagedFromPurchased);
        }

        // --- Step 2: cost for sold eggs (purchased first, then free) ---
        $saleCost = $this->consumePurchasedFromLayers($layers, $sales['total_eggs_sold']);
        // free eggs sold (if purchased were exhausted) cost nothing — already reflected

        $totalCost = $saleCost + $damageCost;

        // Average effective cost (for reporting)
        $totalConsumedForCost = $sales['total_eggs_sold'] + $damagedFromPurchased;
        $effectiveCostPerEgg  = $totalConsumedForCost > 0
            ? $totalCost / $totalConsumedForCost
            : $opening['avg_cost_per_egg'];

        $grossProfit = $sales['total_revenue'] - $totalCost;

        return [
            'opening_stock'       => $openingStock,
            'new_stock_today'     => $opening['today_intake'],
            'total_eggs_sold'     => $sales['total_eggs_sold'],
            'damaged_eggs'        => $damagedEggs,
            'closing_stock_raw'   => $closingStock,
            'closing_stock'       => max(0, $closingStock),
            'avg_cost_per_egg'    => round($effectiveCostPerEgg, 4),
            'total_cost'          => round($totalCost, 2),
            'total_revenue'       => round($sales['total_revenue'], 2),
            'gross_profit'        => round($grossProfit, 2),
        ];
    }

    /**
     * Recalculate all entries from a given date onwards.
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

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    /**
     * Calculate total sales from line items.
     */
    private function saleTotals(iterable $saleLines): array
    {
        $lines = $saleLines instanceof Collection ? $saleLines : collect($saleLines);

        $totalSold    = (int) $lines->sum(fn ($line) => $this->lineQuantity($line));
        $totalRevenue = (float) $lines->sum(
            fn ($line) => (float) data_get($line, 'price_per_egg', 0) * $this->lineQuantity($line)
        );

        return [
            'total_eggs_sold' => $totalSold,
            'total_revenue'   => $totalRevenue,
        ];
    }

    /**
     * Quantity from a sale line (trays + loose or direct quantity).
     */
    private function lineQuantity(mixed $line): int
    {
        $trays      = (float) data_get($line, 'trays_sold', 0);
        $looseEggs  = (int)   data_get($line, 'loose_eggs_sold', 0);
        $eggsPerTray = (int)  data_get($line, 'eggs_per_tray', 30);

        if ($trays > 0 || $looseEggs > 0) {
            return (int) round(($trays * $eggsPerTray) + $looseEggs);
        }

        return (int) data_get($line, 'quantity', 0);
    }

    /**
     * Build remaining FIFO layers after all prior consumption up to $date.
     *
     * Each layer:
     * [
     *   'intake_id'    => int,
     *   'intake_date'  => string,
     *   'purchased_qty'=> int,   // eggs with real cost
     *   'free_qty'     => int,   // bonus eggs (zero cost)
     *   'cost_per_egg' => float, // applies only to purchased_qty
     * ]
     */
    private function remainingLayersForDate(string $date, ?int $ignoreEntryId = null): array
    {
        $layers = EggStockIntake::whereDate('intake_date', '<=', $date)
            ->orderBy('intake_date')
            ->orderBy('id')
            ->get()
            ->map(fn (EggStockIntake $intake) => [
                'intake_id'     => $intake->id,
                'intake_date'   => $intake->intake_date->toDateString(),
                'purchased_qty' => (int) $intake->purchased_eggs,
                'free_qty'      => (int) $intake->free_eggs,
                'cost_per_egg'  => (float) $intake->cost_per_egg,
            ])
            ->all();

        $entriesQuery = EggDailyEntry::whereDate('entry_date', '<', $date)
            ->orderBy('entry_date')
            ->orderBy('id');

        if ($ignoreEntryId) {
            $entriesQuery->whereKeyNot($ignoreEntryId);
        }

        $entries = $entriesQuery->get();

        foreach ($entries as $entry) {
            $damaged     = (int) $entry->damaged_eggs;
            $totalSold   = (int) $entry->total_eggs_sold;

            // Replicate the same absorption order used in calculateEntryValues()
            $freeAvailable        = (int) collect($layers)->sum('free_qty');
            $damagedFromFree      = min($damaged, $freeAvailable);
            $damagedFromPurchased = max(0, $damaged - $damagedFromFree);

            if ($damagedFromFree > 0) {
                $this->consumeFreeFromLayers($layers, $damagedFromFree);
            }

            if ($damagedFromPurchased > 0) {
                $this->consumePurchasedFromLayers($layers, $damagedFromPurchased);
            }

            // Consume sold eggs (purchased first, then free)
            $this->consumePurchasedFromLayers($layers, $totalSold);
        }

        // Remove fully exhausted layers
        $layers = array_values(array_filter(
            $layers,
            fn ($l) => $l['purchased_qty'] > 0 || $l['free_qty'] > 0
        ));

        return $layers;
    }

    /**
     * Consume only FREE eggs from layers (FIFO order).
     * No cost is charged. Modifies $layers in place.
     */
    private function consumeFreeFromLayers(array &$layers, int $quantity): void
    {
        $remaining = $quantity;

        foreach ($layers as &$layer) {
            if ($remaining <= 0) {
                break;
            }

            if ($layer['free_qty'] <= 0) {
                continue;
            }

            $taken              = min($layer['free_qty'], $remaining);
            $layer['free_qty'] -= $taken;
            $remaining         -= $taken;
        }

        unset($layer);
    }

    /**
     * Consume PURCHASED eggs from layers (FIFO order) and return the total cost.
     * If purchased eggs in a layer are exhausted, spills into free eggs of the
     * same layer (those sell as pure profit — no additional cost).
     * Modifies $layers in place.
     */
    private function consumePurchasedFromLayers(array &$layers, int $quantity): float
    {
        $cost      = 0.0;
        $remaining = $quantity;

        foreach ($layers as &$layer) {
            if ($remaining <= 0) {
                break;
            }

            // First take from purchased (costs money)
            if ($layer['purchased_qty'] > 0) {
                $taken                  = min($layer['purchased_qty'], $remaining);
                $cost                  += $taken * $layer['cost_per_egg'];
                $layer['purchased_qty'] -= $taken;
                $remaining             -= $taken;
            }

            // If still more needed, spill into free eggs of this layer (no cost)
            if ($remaining > 0 && $layer['free_qty'] > 0) {
                $taken             = min($layer['free_qty'], $remaining);
                $layer['free_qty'] -= $taken;
                $remaining         -= $taken;
            }
        }

        unset($layer);

        // Remove exhausted layers
        $layers = array_values(array_filter(
            $layers,
            fn ($l) => $l['purchased_qty'] > 0 || $l['free_qty'] > 0
        ));

        return $cost;
    }

    /**
     * Weighted average cost per egg (based only on purchased eggs in layers).
     */
    private function weightedAverageCost(array $layers): float
    {
        $purchasedEggs = (int)   collect($layers)->sum('purchased_qty');
        $purchasedCost = (float) collect($layers)->sum(
            fn ($l) => $l['purchased_qty'] * $l['cost_per_egg']
        );

        return $purchasedEggs > 0 ? $purchasedCost / $purchasedEggs : 0;
    }
}
