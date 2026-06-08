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
 
        $todayIntakes   = EggStockIntake::whereDate('intake_date', $date)->orderBy('id')->get();
        $todayIntakeQty = (int) $todayIntakes->sum('total_eggs');
 
        // Total free eggs currently sitting in the remaining layers
        $totalFreeInLayers = (int) collect($layers)->sum('free_qty');
 
        return [
            'date'             => $date,
            'previous_closing' => $previousClosing,
            'new_intake'       => max(0, $openingStock - $previousClosing),
            'today_intake'     => $todayIntakeQty,
            'opening_stock'    => $openingStock,
            'free_stock'       => $totalFreeInLayers,
            'avg_cost_per_egg' => $this->weightedAverageCost($layers),
            'intakes'          => $intakesSinceLastEntry,
            'today_intakes'    => $todayIntakes,
            'stock_layers'     => $layers,
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
        $opening      = $this->openingStockForDate($date, $ignoreEntryId);
        $sales        = $this->saleTotals($saleLines);
        $openingStock = $opening['opening_stock'];
        $closingStock = $openingStock - $sales['total_eggs_sold'] - $damagedEggs;
 
        // Work on a mutable copy of the opening layers.
        // After all consumption below, $layers will hold the CLOSING state —
        // exactly what the next day needs as its cache starting point.
        $layers = $opening['stock_layers'];
 
        // --- Step 1: absorb damaged eggs (free first, then purchased) ---
        $freeAvailable        = (int) collect($layers)->sum('free_qty');
        $damagedFromFree      = min($damagedEggs, $freeAvailable);
        $damagedFromPurchased = max(0, $damagedEggs - $damagedFromFree);
 
        if ($damagedFromFree > 0) {
            $this->consumeFreeFromLayers($layers, $damagedFromFree, $date);
        }
 
        $damageCost = 0;
        if ($damagedFromPurchased > 0) {
            $damageCost = $this->consumePurchasedFromLayers($layers, $damagedFromPurchased, $date);
        }
 
        // --- Step 2: cost for sold eggs (purchased first, then free) ---
        $saleCost = $this->consumePurchasedFromLayers($layers, $sales['total_eggs_sold'], $date);
 
        $totalCost = $saleCost + $damageCost;
 
        // Average effective cost (for reporting)
        $totalConsumedForCost = $sales['total_eggs_sold'] + $damagedFromPurchased;
        $effectiveCostPerEgg  = $totalConsumedForCost > 0
            ? $totalCost / $totalConsumedForCost
            : $opening['avg_cost_per_egg'];
 
        $grossProfit = $sales['total_revenue'] - $totalCost;
 
        // $layers now reflects the state AFTER this entry's full consumption.
        // Strip exhausted layers before storing.
        $closingLayers = array_values(array_filter(
            $layers,
            fn ($l) => $l['purchased_qty'] > 0 || $l['free_qty'] > 0
        ));
 
        return [
            'opening_stock'     => $openingStock,
            'new_stock_today'   => $opening['today_intake'],
            'total_eggs_sold'   => $sales['total_eggs_sold'],
            'damaged_eggs'      => $damagedEggs,
            'closing_stock_raw' => $closingStock,
            'closing_stock'     => max(0, $closingStock),
            'avg_cost_per_egg'  => round($effectiveCostPerEgg, 4),
            'total_cost'        => round($totalCost, 2),
            'total_revenue'     => round($sales['total_revenue'], 2),
            'gross_profit'      => round($grossProfit, 2),
            // ← CLOSING layers: saved on the entry row and used as the cache
            //   starting point for the next day's remainingLayersForDate().
            'stock_layers'      => $closingLayers,
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
 
            // closing_stock_raw is only used for the oversell guard in the
            // controller; don't persist it.
            unset($values['closing_stock_raw']);
 
            // stock_layers is now already included in $values (closing layers),
            // so no second call to remainingLayersForDate() is needed here.
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
     * OPTIMIZATION: Instead of processing ALL entries from the beginning,
     * we find the most recent entry before the target date and use its
     * cached layers as a starting point. This reduces processing from O(n) 
     * to O(m) where m = entries between cache and target date.
     *
     * IMPORTANT: Each entry only consumes from layers with intake_date <= entry_date.
     * This prevents future intake dates from being affected by past entries.
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
        // Most recent entry strictly before $date — our cache anchor.
        $cacheEntry = EggDailyEntry::whereDate('entry_date', '<', $date)
            ->orderByDesc('entry_date')
            ->orderByDesc('id')
            ->first();
 
        $layers = [];
 
        if ($cacheEntry && $cacheEntry->stock_layers) {
            // ----------------------------------------------------------------
            // Fast path: start from the CLOSING layers of the last entry.
            // These already reflect everything up to and including that entry,
            // so we only need to replay the (usually zero or one) entries that
            // sit strictly between the cache entry and $date.
            // ----------------------------------------------------------------
            $layers = is_array($cacheEntry->stock_layers)
                ? $cacheEntry->stock_layers
                : json_decode($cacheEntry->stock_layers, true) ?? [];
 
            $startDate = $cacheEntry->entry_date->toDateString();
 
            // Add intakes that arrived AFTER the cache entry and ON OR BEFORE $date.
            // (Intakes up to the cache entry's date are already baked into its layers.)
            $newIntakes = EggStockIntake::whereDate('intake_date', '>', $startDate)
                ->whereDate('intake_date', '<=', $date)
                ->orderBy('intake_date')
                ->orderBy('id')
                ->get();
 
            foreach ($newIntakes as $intake) {
                $layers[] = [
                    'intake_id'     => $intake->id,
                    'intake_date'   => $intake->intake_date->toDateString(),
                    'purchased_qty' => (int) $intake->purchased_eggs,
                    'free_qty'      => (int) $intake->free_eggs,
                    'cost_per_egg'  => (float) $intake->cost_per_egg,
                ];
            }
 
            // Replay only the entries that are strictly between the cache
            // entry and $date.  The cache entry itself is excluded because its
            // consumption is already reflected in the stored closing layers.
            $gapEntriesQuery = EggDailyEntry::whereDate('entry_date', '>', $startDate)
                ->whereDate('entry_date', '<', $date)
                ->orderBy('entry_date')
                ->orderBy('id');
 
            if ($ignoreEntryId) {
                $gapEntriesQuery->whereKeyNot($ignoreEntryId);
            }
 
            $gapEntries = $gapEntriesQuery->get();
        } else {
            // ----------------------------------------------------------------
            // Cold path: no usable cache — rebuild from all intakes.
            // ----------------------------------------------------------------
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
 
            // Replay ALL entries before $date.
            $gapEntriesQuery = EggDailyEntry::whereDate('entry_date', '<', $date)
                ->orderBy('entry_date')
                ->orderBy('id');
 
            if ($ignoreEntryId) {
                $gapEntriesQuery->whereKeyNot($ignoreEntryId);
            }
 
            $gapEntries = $gapEntriesQuery->get();
        }
 
        // Replay each gap entry in chronological order.
        foreach ($gapEntries as $entry) {
            $entryDate = $entry->entry_date->toDateString();
            $damaged   = (int) $entry->damaged_eggs;
            $totalSold = (int) $entry->total_eggs_sold;
 
            // Damage: free eggs first, then purchased
            $layersForEntry       = array_filter($layers, fn ($l) => $l['intake_date'] <= $entryDate);
            $freeAvailable        = (int) collect($layersForEntry)->sum('free_qty');
            $damagedFromFree      = min($damaged, $freeAvailable);
            $damagedFromPurchased = max(0, $damaged - $damagedFromFree);
 
            if ($damagedFromFree > 0) {
                $this->consumeFreeFromLayers($layers, $damagedFromFree, $entryDate);
            }
            if ($damagedFromPurchased > 0) {
                $this->consumePurchasedFromLayers($layers, $damagedFromPurchased, $entryDate);
            }
 
            // Sales: purchased first, then free
            $this->consumePurchasedFromLayers($layers, $totalSold, $entryDate);
        }
 
        // Strip exhausted layers.
        return array_values(array_filter(
            $layers,
            fn ($l) => $l['purchased_qty'] > 0 || $l['free_qty'] > 0
        ));
    }
    /**
     * Consume only FREE eggs from layers (FIFO order).
     * No cost is charged. Modifies $layers in place.
     * Only modifies layers with intake_date <= $entryDate (if provided).
     */
    private function consumeFreeFromLayers(array &$layers, int $quantity, ?string $entryDate = null): void
    {
        $remaining = $quantity;

        foreach ($layers as &$layer) {
            if ($remaining <= 0) {
                break;
            }

            // Skip layers that didn't exist before this entry
            if ($entryDate && $layer['intake_date'] > $entryDate) {
                continue;
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
     * Only modifies layers with intake_date <= $entryDate (if provided).
     */
    private function consumePurchasedFromLayers(array &$layers, int $quantity, ?string $entryDate = null): float
    {
        $cost      = 0.0;
        $remaining = $quantity;
 
        foreach ($layers as &$layer) {
            if ($remaining <= 0) break;
            if ($entryDate && $layer['intake_date'] > $entryDate) continue;
 
            if ($layer['purchased_qty'] > 0) {
                $taken                   = min($layer['purchased_qty'], $remaining);
                $cost                   += $taken * $layer['cost_per_egg'];
                $layer['purchased_qty'] -= $taken;
                $remaining              -= $taken;
            }
 
            if ($remaining > 0 && $layer['free_qty'] > 0) {
                $taken             = min($layer['free_qty'], $remaining);
                $layer['free_qty'] -= $taken;
                $remaining         -= $taken;
            }
        }
 
        unset($layer);
 
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
