<?php

namespace App\Http\Controllers;

use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReportController extends Controller
{
    public function mostRequested(Request $request): JsonResponse
    {
        $dates = $request->validate([
            'from' => ['required', 'date_format:Y-m-d'],
            'to' => ['required', 'date_format:Y-m-d'],
        ]);

        if ($dates['to'] < $dates['from']) {
            $message = 'La fecha final no puede ser anterior a la fecha inicial.';

            return response()->json([
                'code' => 'INVALID_DATE_RANGE',
                'message' => $message,
                'details' => [['field' => 'to', 'issue' => $message]],
            ], 422);
        }

        $start = CarbonImmutable::parse($dates['from'], 'UTC')->startOfDay();
        $end = CarbonImmutable::parse($dates['to'], 'UTC')->startOfDay()->addDay();

        $rows = DB::table('loan_items')
            ->join('loans', 'loans.id', '=', 'loan_items.loan_id')
            ->where('loans.loaned_at', '>=', $start)
            ->where('loans.loaned_at', '<', $end)
            ->groupBy(
                'loan_items.game_id',
                'loan_items.game_title_snapshot',
                'loan_items.category_name_snapshot',
            )
            ->orderByDesc('times_requested')
            ->orderBy('game_title')
            ->orderBy('loan_items.game_id')
            ->orderBy('category_name')
            ->select([
                'loan_items.game_id',
                'loan_items.game_title_snapshot as game_title',
                'loan_items.category_name_snapshot as category_name',
            ])
            ->selectRaw('COUNT(*) AS times_requested')
            ->get()
            ->map(static fn (object $row): array => [
                'game_id' => $row->game_id,
                'game_title' => $row->game_title,
                'category_name' => $row->category_name,
                'times_requested' => (int) $row->times_requested,
            ]);

        return response()->json([
            'from' => $dates['from'],
            'to' => $dates['to'],
            'rows' => $rows,
        ]);
    }
}