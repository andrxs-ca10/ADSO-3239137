<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreLoanRequest;
use App\Support\LoanStatus;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class LoanController extends Controller
{
    public function store(StoreLoanRequest $request): JsonResponse
    {
        $user = $request->user();

        if ($user === null) {
            return $this->error('UNAUTHENTICATED', 'Debes iniciar sesión.', 401);
        }

        $useDemoClock = $request->header('X-Demo-Loan-Clock') === '11-days-ago';

        if ($useDemoClock && (
            ! app()->environment('local')
            || ! config('app.demo_loan_clock')
            || $user->role !== 'admin'
        )) {
            return $this->error('DEMO_CLOCK_DISABLED', 'El reloj de demostración no está disponible.', 403);
        }

        $data = $request->validated();
        $memberId = strtolower($data['member_id']);
        $gameIds = array_map('strtolower', array_column($data['items'], 'game_id'));

        return DB::transaction(function () use ($user, $memberId, $gameIds, $useDemoClock): JsonResponse {
            $member = DB::table('members')
                ->where('id', $memberId)
                ->lockForUpdate()
                ->first();

            if ($member === null) {
                return $this->error('NOT_FOUND', 'El socio no existe.', 404);
            }

            $games = DB::table('games')
                ->whereIn('id', $gameIds)
                ->whereNull('deleted_at')
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            $now = CarbonImmutable::now('UTC')->startOfSecond();
            $loanedAt = $useDemoClock ? $now->subDays(11) : $now;

            $pendingItems = DB::table('loan_items')
                ->join('loans', 'loans.id', '=', 'loan_items.loan_id')
                ->where('loans.member_id', $memberId)
                ->whereNull('loan_items.returned_at');

            if ((clone $pendingItems)->where('loan_items.due_at', '<', $now)->exists()) {
                return $this->error('MEMBER_HAS_OVERDUE', 'El socio tiene juegos vencidos sin devolver.', 409);
            }

            $pendingCount = $pendingItems->count();

            if ($pendingCount + count($gameIds) > 4) {
                return $this->error(
                    'MEMBER_LIMIT_EXCEEDED',
                    'El socio puede tener hasta cuatro juegos sin devolver.',
                    422,
                    [['field' => 'items', 'issue' => "Juegos sin devolver actualmente: {$pendingCount}."]],
                );
            }

            foreach ($gameIds as $gameId) {
                $game = $games->get($gameId);

                if ($game === null || $game->available_units < 1) {
                    return $this->error('GAME_NOT_AVAILABLE', 'Uno de los juegos solicitados no está disponible.', 409);
                }
            }

            $categoryNames = DB::table('categories')
                ->whereIn('id', $games->pluck('category_id')->unique())
                ->pluck('name', 'id');

            $loanId = (string) Str::uuid();
            $dueAt = $loanedAt->addDays(10);

            DB::table('loans')->insert([
                'id' => $loanId,
                'member_id' => $memberId,
                'loaned_by_user_id' => $user->getAuthIdentifier(),
                'loaned_at' => $loanedAt,
            ]);

            $items = [];

            foreach ($gameIds as $gameId) {
                $game = $games->get($gameId);

                $items[] = [
                    'id' => (string) Str::uuid(),
                    'game_id' => $gameId,
                    'game_title_snapshot' => $game->title,
                    'ean_snapshot' => $game->ean,
                    'category_name_snapshot' => $categoryNames[$game->category_id],
                    'due_at' => $dueAt,
                    'returned_at' => null,
                ];

                DB::table('games')->where('id', $gameId)->decrement('available_units');
            }

            DB::table('loan_items')->insert(array_map(
                static fn (array $item): array => ['loan_id' => $loanId] + $item,
                $items,
            ));

            return response()->json([
                'id' => $loanId,
                'member_id' => $memberId,
                'loaned_at' => $loanedAt->toIso8601ZuluString(),
                'status' => LoanStatus::fromItems($items, $now)->value,
                'items' => array_map(
                    static fn (array $item): array => array_replace(
                        $item,
                        ['due_at' => $item['due_at']->toIso8601ZuluString()],
                    ),
                    $items,
                ),
            ], 201);
        });
    }

    public function returnItem(string $loan_id, string $item_id): JsonResponse
    {
        if (! Str::isUuid($loan_id) || ! Str::isUuid($item_id)) {
            return $this->error('NOT_FOUND', 'La línea de préstamo no existe.', 404);
        }

        return DB::transaction(function () use ($loan_id, $item_id): JsonResponse {
            $item = DB::table('loan_items')
                ->where('loan_id', $loan_id)
                ->where('id', $item_id)
                ->lockForUpdate()
                ->first();

            if ($item === null) {
                return $this->error('NOT_FOUND', 'La línea de préstamo no existe.', 404);
            }

            if ($item->returned_at !== null) {
                return $this->error(
                    'ITEM_ALREADY_RETURNED',
                    'Este juego ya fue devuelto.',
                    409,
                );
            }

            $returnedAt = CarbonImmutable::now('UTC')->startOfSecond();

            DB::table('loan_items')
                ->where('id', $item->id)
                ->update(['returned_at' => $returnedAt]);

            DB::table('games')
                ->where('id', $item->game_id)
                ->increment('available_units');

            return response()->json([
                'id' => $item->id,
                'game_id' => $item->game_id,
                'game_title_snapshot' => $item->game_title_snapshot,
                'ean_snapshot' => $item->ean_snapshot,
                'category_name_snapshot' => $item->category_name_snapshot,
                'due_at' => CarbonImmutable::parse($item->due_at, 'UTC')->toIso8601ZuluString(),
                'returned_at' => $returnedAt->toIso8601ZuluString(),
            ]);
        });
    }

    public function overdue(): JsonResponse
    {
        $now = CarbonImmutable::now('UTC')->startOfSecond();

        $items = DB::table('loan_items')
            ->join('loans', 'loans.id', '=', 'loan_items.loan_id')
            ->join('members', 'members.id', '=', 'loans.member_id')
            ->whereNull('loan_items.returned_at')
            ->where('loan_items.due_at', '<', $now)
            ->orderBy('loan_items.due_at')
            ->orderBy('loan_items.id')
            ->get([
                'loan_items.loan_id',
                'loan_items.id as item_id',
                'loan_items.game_title_snapshot as game_title',
                'members.full_name as member_name',
                'loan_items.due_at',
            ])
            ->map(static function (object $item) use ($now): array {
                $dueAt = CarbonImmutable::parse($item->due_at, 'UTC');

                return [
                    'loan_id' => $item->loan_id,
                    'item_id' => $item->item_id,
                    'game_title' => $item->game_title,
                    'member_name' => $item->member_name,
                    'due_at' => $dueAt->toIso8601ZuluString(),
                    'days_late' => (int) ceil($dueAt->diffInSeconds($now) / 86400),
                ];
            });

        return response()->json(['items' => $items]);
    }

    public function show(string $id): JsonResponse
    {
        $loan = Str::isUuid($id)
            ? DB::table('loans')->where('id', $id)->first()
            : null;

        if ($loan === null) {
            return $this->error('NOT_FOUND', 'El préstamo no existe.', 404);
        }

        $items = DB::table('loan_items')
            ->where('loan_id', $loan->id)
            ->orderBy('id')
            ->get()
            ->map(static fn (object $item): array => [
                'id' => $item->id,
                'game_id' => $item->game_id,
                'game_title_snapshot' => $item->game_title_snapshot,
                'ean_snapshot' => $item->ean_snapshot,
                'category_name_snapshot' => $item->category_name_snapshot,
                'due_at' => CarbonImmutable::parse($item->due_at, 'UTC'),
                'returned_at' => $item->returned_at === null
                    ? null
                    : CarbonImmutable::parse($item->returned_at, 'UTC'),
            ]);

        $status = LoanStatus::fromItems($items, CarbonImmutable::now('UTC')->startOfSecond());

        return response()->json([
            'id' => $loan->id,
            'member_id' => $loan->member_id,
            'loaned_at' => CarbonImmutable::parse($loan->loaned_at, 'UTC')->toIso8601ZuluString(),
            'status' => $status->value,
            'items' => $items->map(static fn (array $item): array => array_replace($item, [
                'due_at' => $item['due_at']->toIso8601ZuluString(),
                'returned_at' => $item['returned_at']?->toIso8601ZuluString(),
            ]))->all(),
        ]);
    }

    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'from' => ['sometimes', 'date_format:Y-m-d'],
            'to' => ['sometimes', 'date_format:Y-m-d'],
            'status' => ['sometimes', 'in:open,overdue,closed'],
            'member_id' => ['sometimes', 'uuid'],
            'page' => ['sometimes', 'integer', 'min:1'],
            'size' => ['sometimes', 'integer', 'min:1', 'max:50'],
        ]);

        if (isset($filters['from'], $filters['to']) && $filters['to'] < $filters['from']) {
            $message = 'La fecha final no puede ser anterior a la fecha inicial.';

            return $this->error('INVALID_DATE_RANGE', $message, 422, [
                ['field' => 'to', 'issue' => $message],
            ]);
        }

        $now = CarbonImmutable::now('UTC')->startOfSecond();

        $loansWithStatus = DB::table('loans')
            ->select(['loans.id', 'loans.member_id', 'loans.loaned_at'])
            ->selectRaw(
                "CASE
                    WHEN EXISTS (
                        SELECT 1 FROM loan_items
                        WHERE loan_items.loan_id = loans.id
                          AND loan_items.returned_at IS NULL
                          AND loan_items.due_at < ?
                    ) THEN 'overdue'
                    WHEN EXISTS (
                        SELECT 1 FROM loan_items
                        WHERE loan_items.loan_id = loans.id
                          AND loan_items.returned_at IS NULL
                    ) THEN 'open'
                    ELSE 'closed'
                END AS status",
                [$now],
            );

        if (isset($filters['member_id'])) {
            $loansWithStatus->where('loans.member_id', $filters['member_id']);
        }

        if (isset($filters['from'])) {
            $start = CarbonImmutable::parse($filters['from'], 'UTC')->startOfDay();
            $loansWithStatus->where('loans.loaned_at', '>=', $start);
        }

        if (isset($filters['to'])) {
            $end = CarbonImmutable::parse($filters['to'], 'UTC')->startOfDay()->addDay();
            $loansWithStatus->where('loans.loaned_at', '<', $end);
        }

        $query = DB::query()->fromSub($loansWithStatus, 'loan_list');

        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        $loans = $query->orderByDesc('loaned_at')
            ->orderByDesc('id')
            ->paginate(
                (int) ($filters['size'] ?? 20),
                ['*'],
                'page',
                (int) ($filters['page'] ?? 1),
            );

        return response()->json([
            'items' => $loans->getCollection()->map(static fn (object $loan): array => [
                'id' => $loan->id,
                'member_id' => $loan->member_id,
                'loaned_at' => CarbonImmutable::parse($loan->loaned_at, 'UTC')->toIso8601ZuluString(),
                'status' => $loan->status,
            ])->all(),
            'page' => $loans->currentPage(),
            'size' => $loans->perPage(),
            'total' => $loans->total(),
        ]);
    }

    private function error(string $code, string $message, int $status, array $details = []): JsonResponse
    {
        return response()->json([
            'code' => $code,
            'message' => $message,
            'details' => $details,
        ], $status);
    }
}