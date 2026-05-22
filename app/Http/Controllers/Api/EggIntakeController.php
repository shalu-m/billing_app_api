<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\EggStockIntakeResource;
use App\Models\EggStockIntake;
use App\Services\EggStockCalculator;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;

class EggIntakeController extends Controller
{
    public function __construct(private readonly EggStockCalculator $calculator)
    {
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        $query = EggStockIntake::latest('intake_date')->latest('id');

        if ($request->filled('from')) {
            $query->whereDate('intake_date', '>=', $request->from);
        }

        if ($request->filled('to')) {
            $query->whereDate('intake_date', '<=', $request->to);
        }

        $totalsQuery = clone $query;
        $perPage = (int) $request->get('per_page', 20);
        $intakes = $query->paginate($perPage);

        return EggStockIntakeResource::collection($intakes)->additional([
            'totals' => [
                'total_trays_bought' => round((float) $totalsQuery->sum('trays_received'), 2),
                'total_eggs_bought' => (int) (clone $totalsQuery)->sum('total_eggs'),
                'total_investment' => round((float) (clone $totalsQuery)->sum('total_cost'), 2),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'intake_date' => 'required|date',
            'trays_received' => 'required|numeric|min:0',
            'loose_eggs_received' => 'nullable|integer|min:0',
            'eggs_per_tray' => 'required|integer|min:1',
            'cost_per_tray' => 'required|numeric|gt:0',
            'supplier_name' => 'nullable|string|max:150',
            'notes' => 'nullable|string|max:1000',
        ]);

        if ((float) $validated['trays_received'] <= 0 && (int) ($validated['loose_eggs_received'] ?? 0) <= 0) {
            return response()->json([
                'message' => 'Enter trays received or loose eggs received.',
                'errors' => [
                    'trays_received' => ['Enter trays or loose eggs.'],
                    'loose_eggs_received' => ['Enter trays or loose eggs.'],
                ],
            ], 422);
        }

        $costPerEgg = (float) $validated['cost_per_tray'] / (int) $validated['eggs_per_tray'];
        $looseEggs = (int) ($validated['loose_eggs_received'] ?? 0);
        $totalEggs = (int) round(((float) $validated['trays_received'] * (int) $validated['eggs_per_tray']) + $looseEggs);
        $totalCost = ((float) $validated['trays_received'] * (float) $validated['cost_per_tray']) + ($looseEggs * $costPerEgg);

        $intake = DB::transaction(function () use ($validated, $totalEggs, $totalCost, $costPerEgg, $looseEggs) {
            $intake = EggStockIntake::create([
                ...$validated,
                'loose_eggs_received' => $looseEggs,
                'total_eggs' => $totalEggs,
                'total_cost' => round($totalCost, 2),
                'cost_per_egg' => round($costPerEgg, 4),
            ]);

            $this->calculator->recalculateEntriesFrom($intake->intake_date->toDateString());

            return $intake;
        });

        return response()->json([
            'message' => 'Egg stock intake recorded successfully.',
            'data' => new EggStockIntakeResource($intake),
        ], 201);
    }

    public function destroy(EggStockIntake $eggIntake): JsonResponse
    {
        if (! $this->isWithinLastDays($eggIntake->intake_date)) {
            return response()->json([
                'message' => 'Egg stock intakes can be deleted only for the last 5 days.',
            ], 403);
        }

        $date = $eggIntake->intake_date->toDateString();

        DB::transaction(function () use ($eggIntake, $date) {
            $eggIntake->delete();
            $this->calculator->recalculateEntriesFrom($date);
        });

        return response()->json([
            'message' => 'Egg stock intake deleted successfully.',
        ]);
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
}
