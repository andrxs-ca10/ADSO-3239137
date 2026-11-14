<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreLoanRequest;
use App\Support\LoanStatus;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
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

        $data = $request->validated();
        $memberId = strtolower($data['member_id']);
        $gameIds = array_map('strtolower', array_column($data['items'], 'game_id'));

        return DB::transaction(function () use ($user, $memberId, $gameIds): JsonResponse {
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

            $loanedAt = CarbonImmutable::now('UTC')->startOfSecond();

            $pendingItems = DB::table('loan_items')
                ->join('loans', 'loans.id', '=', 'loan_items.loan_id')
                ->where('loans.member_id', $memberId)
                ->whereNull('loan_items.returned_at');

            if ((clone $pendingItems)->where('loan_items.due_at', '<', $loanedAt)->exists()) {
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
                'status' => LoanStatus::fromItems($items, $loanedAt)->value,
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

    private function error(string $code, string $message, int $status, array $details = []): JsonResponse
    {
        return response()->json([
            'code' => $code,
            'message' => $message,
            'details' => $details,
        ], $status);
    }
}