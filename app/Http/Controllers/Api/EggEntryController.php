<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreEggEntryRequest;
use App\Http\Requests\UpdateEggEntryRequest;
use App\Http\Resources\EggEntryResource;
use App\Http\Resources\EggStockIntakeResource;
use App\Models\EggDailyEntry;
use App\Models\EggStockIntake;
use App\Services\EggStockCalculator;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Throwable;

class EggEntryController extends Controller
{
    public function __construct(private readonly EggStockCalculator $calculator)
    {
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        $query = EggDailyEntry::with('saleLines')->latest('entry_date');

        if ($request->filled('from') && $request->filled('to')) {
            $query->betweenDates($request->from, $request->to);
        } elseif ($request->filled('from')) {
            $query->whereDate('entry_date', '>=', $request->from);
        } elseif ($request->filled('to')) {
            $query->whereDate('entry_date', '<=', $request->to);
        }

        if ($request->filled('month') && $request->filled('year')) {
            $query->forMonth((int) $request->year, (int) $request->month);
        }

        $entries = $request->filled('per_page')
            ? $query->paginate((int) $request->per_page)
            : $query->get();

        return EggEntryResource::collection($entries);
    }

    public function openingStock(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'date' => 'required|date',
        ]);

        $opening = $this->calculator->openingStockForDate($validated['date']);

        return response()->json([
            'date' => $opening['date'],
            'previous_closing' => $opening['previous_closing'],
            'new_intake' => $opening['new_intake'],
            'today_intake' => $opening['today_intake'],
            'opening_stock' => $opening['opening_stock'],
            'avg_cost_per_egg' => round($opening['avg_cost_per_egg'], 4),
            'intake_details' => EggStockIntakeResource::collection($opening['intakes'])->resolve($request),
            'today_intake_details' => EggStockIntakeResource::collection($opening['today_intakes'])->resolve($request),
            'stock_layers' => $opening['stock_layers'],
        ]);
    }

    public function store(StoreEggEntryRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $damagedEggs = (int) ($validated['damaged_eggs'] ?? 0);
        $values = $this->calculator->calculateEntryValues(
            $validated['entry_date'],
            $validated['sale_lines'],
            $damagedEggs
        );

        if ($values['closing_stock_raw'] < 0) {
            return $this->oversoldResponse($values);
        }

        unset($values['closing_stock_raw']);

        DB::beginTransaction();

        try {
            $entry = EggDailyEntry::create([
                'entry_date' => $validated['entry_date'],
                ...$values,
                'notes' => $validated['notes'] ?? null,
            ]);

            $this->replaceSaleLines($entry, $validated['sale_lines']);
            $this->calculator->recalculateEntriesFrom($entry->entry_date->toDateString());

            DB::commit();

            return response()->json([
                'message' => 'Egg entry saved successfully.',
                'data' => new EggEntryResource($entry->fresh()->load('saleLines')),
            ], 201);
        } catch (Throwable $e) {
            DB::rollBack();

            return response()->json([
                'message' => 'Failed to save egg entry.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function show(EggDailyEntry $eggEntry): EggEntryResource
    {
        return new EggEntryResource($eggEntry->load('saleLines'));
    }

    public function update(UpdateEggEntryRequest $request, EggDailyEntry $eggEntry): JsonResponse
    {
        if (! $this->isWithinLastDays($eggEntry->entry_date)) {
            return $this->recentWindowResponse('Egg entries can be edited only for the last 5 days.');
        }

        $validated = $request->validated();

        if (! $this->isWithinLastDays($validated['entry_date'])) {
            return $this->recentWindowResponse('Egg entries can be edited only for dates in the last 5 days.');
        }

        $oldDate = $eggEntry->entry_date->toDateString();
        $damagedEggs = (int) ($validated['damaged_eggs'] ?? 0);
        $values = $this->calculator->calculateEntryValues(
            $validated['entry_date'],
            $validated['sale_lines'],
            $damagedEggs,
            $eggEntry->id
        );

        if ($values['closing_stock_raw'] < 0) {
            return $this->oversoldResponse($values);
        }

        unset($values['closing_stock_raw']);

        DB::beginTransaction();

        try {
            $eggEntry->update([
                'entry_date' => $validated['entry_date'],
                ...$values,
                'notes' => $validated['notes'] ?? null,
            ]);

            $this->replaceSaleLines($eggEntry, $validated['sale_lines']);
            $this->calculator->recalculateEntriesFrom(min($oldDate, $validated['entry_date']));

            DB::commit();

            return response()->json([
                'message' => 'Egg entry updated successfully.',
                'data' => new EggEntryResource($eggEntry->fresh()->load('saleLines')),
            ]);
        } catch (Throwable $e) {
            DB::rollBack();

            return response()->json([
                'message' => 'Failed to update egg entry.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function destroy(EggDailyEntry $eggEntry): JsonResponse
    {
        if (! $this->isWithinLastDays($eggEntry->entry_date)) {
            return $this->recentWindowResponse('Egg entries can be deleted only for the last 5 days.');
        }

        $date = $eggEntry->entry_date->toDateString();

        DB::transaction(function () use ($eggEntry, $date) {
            $eggEntry->delete();
            $this->calculator->recalculateEntriesFrom($date);
        });

        return response()->json([
            'message' => 'Egg entry deleted successfully.',
        ]);
    }

    public function summary(Request $request): JsonResponse
    {
        $from = $request->get('from', now()->startOfMonth()->toDateString());
        $to = $request->get('to', now()->toDateString());

        $entries = EggDailyEntry::with('saleLines')
            ->betweenDates($from, $to)
            ->orderBy('entry_date')
            ->get();

        $intakes = EggStockIntake::betweenDates($from, $to)->get();
        $closingStock = (int) (EggDailyEntry::whereDate('entry_date', '<=', $to)
            ->latest('entry_date')
            ->value('closing_stock') ?? 0);

        $totalSold = (int) $entries->sum('total_eggs_sold');
        $grossProfit = (float) $entries->sum('gross_profit');

        $dailyBreakdown = $entries->map(fn (EggDailyEntry $entry) => [
            'id' => $entry->id,
            'date' => $entry->entry_date->toDateString(),
            'entry_date' => $entry->entry_date->toDateString(),
            'opening_stock' => $entry->opening_stock,
            'new_stock' => $entry->new_stock_today,
            'new_stock_today' => $entry->new_stock_today,
            'eggs_sold' => $entry->total_eggs_sold,
            'total_eggs_sold' => $entry->total_eggs_sold,
            'damaged' => $entry->damaged_eggs,
            'damaged_eggs' => $entry->damaged_eggs,
            'closing_stock' => $entry->closing_stock,
            'revenue' => $entry->total_revenue,
            'total_revenue' => $entry->total_revenue,
            'cost' => $entry->total_cost,
            'total_cost' => $entry->total_cost,
            'profit' => $entry->gross_profit,
            'gross_profit' => $entry->gross_profit,
            'avg_cost_per_egg' => $entry->avg_cost_per_egg,
            'sale_lines' => $entry->saleLines->map(fn ($line) => [
                'id' => $line->id,
                'price' => $line->price_per_egg,
                'price_per_egg' => $line->price_per_egg,
                'trays_sold' => $line->trays_sold,
                'loose_eggs_sold' => $line->loose_eggs_sold,
                'eggs_per_tray' => $line->eggs_per_tray,
                'qty' => $line->quantity,
                'quantity' => $line->quantity,
                'amount' => $line->total_amount,
                'total_amount' => $line->total_amount,
            ])->values(),
        ])->values();

        return response()->json([
            'period' => ['from' => $from, 'to' => $to],
            'stock' => [
                'total_trays_bought' => round((float) $intakes->sum('trays_received'), 2),
                'total_eggs_bought' => (int) $intakes->sum('total_eggs'),
                'total_eggs_sold' => $totalSold,
                'total_damaged' => (int) $entries->sum('damaged_eggs'),
                'closing_stock' => $closingStock,
            ],
            'money' => [
                'total_investment' => round((float) $intakes->sum('total_cost'), 2),
                'total_revenue' => round((float) $entries->sum('total_revenue'), 2),
                'gross_profit' => round($grossProfit, 2),
                'avg_profit_per_egg' => $totalSold > 0 ? round($grossProfit / $totalSold, 2) : 0,
            ],
            'daily_breakdown' => $dailyBreakdown,
            'totals' => [
                'total_eggs_sold' => $totalSold,
                'total_damaged' => (int) $entries->sum('damaged_eggs'),
                'total_arrivals' => (int) $entries->sum('new_stock_today'),
                'total_revenue' => round((float) $entries->sum('total_revenue'), 2),
                'total_profit' => round($grossProfit, 2),
            ],
            'chart_data' => $dailyBreakdown->map(fn ($entry) => [
                'date' => $entry['date'],
                'revenue' => $entry['revenue'],
                'profit' => $entry['profit'],
                'sold' => $entry['eggs_sold'],
            ]),
            'entries' => EggEntryResource::collection($entries->sortByDesc('entry_date')->values())->resolve($request),
        ]);
    }

    private function replaceSaleLines(EggDailyEntry $entry, array $saleLines): void
    {
        $entry->saleLines()->delete();

        foreach ($saleLines as $line) {
            $price = (float) $line['price_per_egg'];
            $trays = (float) ($line['trays_sold'] ?? 0);
            $looseEggs = (int) ($line['loose_eggs_sold'] ?? 0);
            $eggsPerTray = (int) ($line['eggs_per_tray'] ?? 30);
            $quantity = $trays > 0 || $looseEggs > 0
                ? (int) round(($trays * $eggsPerTray) + $looseEggs)
                : (int) ($line['quantity'] ?? 0);

            $entry->saleLines()->create([
                'trays_sold' => $trays,
                'loose_eggs_sold' => $looseEggs,
                'eggs_per_tray' => $eggsPerTray,
                'price_per_egg' => $price,
                'quantity' => $quantity,
                'total_amount' => round($price * $quantity, 2),
            ]);
        }
    }

    private function oversoldResponse(array $values): JsonResponse
    {
        return response()->json([
            'message' => 'Sold and damaged eggs cannot be more than opening stock.',
            'errors' => [
                'sale_lines' => [
                    'Available opening stock is '.$values['opening_stock'].' eggs, but sold plus damaged is '.
                    ($values['total_eggs_sold'] + $values['damaged_eggs']).' eggs.',
                ],
            ],
        ], 422);
    }

    private function isWithinLastDays($date, int $days = 5): bool
    {
        if (! $date || $days <= 0) {
            return false;
        }

        $recordDate = Carbon::parse($date)->startOfDay();
        $today = now()->startOfDay();
        $firstAllowedDate = $today->copy()->subDays($days - 1);

        return $recordDate->greaterThanOrEqualTo($firstAllowedDate)
            && $recordDate->lessThanOrEqualTo($today);
    }

    private function recentWindowResponse(string $message): JsonResponse
    {
        return response()->json([
            'message' => $message,
        ], 403);
    }
}
