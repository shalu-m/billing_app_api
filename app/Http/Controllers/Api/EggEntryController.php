<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreEggEntryRequest;
use App\Http\Requests\UpdateEggEntryRequest;
use App\Http\Resources\EggEntryResource;
use App\Models\EggEntry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Log;

class EggEntryController extends Controller
{
    /**
     * GET /api/egg-entries
     * List all entries, newest first. Supports date range filter.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = EggEntry::latest('entry_date');

        if ($request->filled('from') && $request->filled('to')) {
            $query->betweenDates($request->from, $request->to);
        }

        if ($request->filled('month') && $request->filled('year')) {
            $query->forMonth((int) $request->year, (int) $request->month);
        }

        $entries = $request->filled('per_page')
            ? $query->paginate((int) $request->per_page)
            : $query->get();

        return EggEntryResource::collection($entries);
    }

    /**
     * POST /api/egg-entries
     * Create a daily egg entry.
     */
    public function store(StoreEggEntryRequest $request): JsonResponse
    {
        $entry = EggEntry::create($request->validated());

        return response()->json([
            'message' => 'Egg entry saved successfully.',
            'data'    => new EggEntryResource($entry),
        ], 201);
    }

    /**
     * GET /api/egg-entries/{id}
     * Show a single entry.
     */
    public function show(EggEntry $eggEntry): EggEntryResource
    {
        return new EggEntryResource($eggEntry);
    }

    /**
     * PUT /api/egg-entries/{id}
     * Update an existing entry.
     */
    public function update(UpdateEggEntryRequest $request, EggEntry $eggEntry): JsonResponse
    {
        $eggEntry->update($request->validated());

        return response()->json([
            'message' => 'Egg entry updated successfully.',
            'data'    => new EggEntryResource($eggEntry->fresh()),
        ]);
    }

    /**
     * DELETE /api/egg-entries/{id}
     * Delete an entry.
     */
    public function destroy(EggEntry $eggEntry): JsonResponse
    {
        $eggEntry->delete();

        return response()->json([
            'message' => 'Egg entry deleted successfully.',
        ]);
    }

    /**
     * GET /api/egg-entries/summary
     * Aggregated stats + chart data for the reports page.
     */
    public function summary(Request $request): JsonResponse
    {
        $from = $request->get('from', now()->startOfMonth()->toDateString());
        $to   = $request->get('to',   now()->toDateString());

        $entries = EggEntry::betweenDates($from, $to)
            ->orderBy('entry_date')
            ->get();

        // Overall totals using model accessors
        $totalEggsSold   = $entries->sum('eggs_sold');
        $totalDamaged    = $entries->sum('damaged_eggs');
        $totalArrivals   = $entries->sum('fresh_arrivals');
        $totalRevenue    = $entries->sum('revenue');    // accessor
        $totalProfit     = $entries->sum('profit');     // accessor

        // Chart series: one point per day
        $chartData = $entries->map(fn ($e) => [
            'date'    => $e->entry_date->toDateString(),
            'revenue' => $e->revenue,
            'profit'  => $e->profit,
            'sold'    => $e->eggs_sold,
        ]);

        // Best performing day
        $bestDay = $entries->sortByDesc('profit')->first();

        return response()->json([
            'period' => ['from' => $from, 'to' => $to],
            'totals' => [
                'total_eggs_sold'  => $totalEggsSold,
                'total_damaged'    => $totalDamaged,
                'total_arrivals'   => $totalArrivals,
                'total_revenue'    => round($totalRevenue, 2),
                'total_profit'     => round($totalProfit, 2),
                'avg_daily_profit' => $entries->count() > 0
                    ? round($totalProfit / $entries->count(), 2)
                    : 0,
            ],
            'best_day'   => $bestDay ? new EggEntryResource($bestDay) : null,
            'chart_data' => $chartData->values(),
            'entries'    => EggEntryResource::collection($entries->sortByDesc('entry_date')->values())->resolve($request),
        ]);
    }
}
