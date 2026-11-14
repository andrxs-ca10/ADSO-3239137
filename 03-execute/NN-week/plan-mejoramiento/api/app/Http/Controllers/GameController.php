<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreGameRequest;
use App\Http\Requests\UpdateGameRequest;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Http\Request;

class GameController extends Controller
{
    public function store(StoreGameRequest $request): JsonResponse
    {
        $data = $request->validated();

        $game = [
            'id' => (string) Str::uuid(),
            'title' => $data['title'],
            'designer' => $data['designer'],
            'ean' => $data['ean'],
            'category_id' => $data['category_id'],
            'total_units' => (int) $data['total_units'],
            'available_units' => (int) $data['total_units'],
            'cover_key' => null,
        ];

        try {
            DB::table('games')->insert($game);
        } catch (UniqueConstraintViolationException $exception) {
            if (! str_contains($exception->errorInfo[2] ?? '', '"games_active_ean_unique"')) {
                throw $exception;
            }

            return response()->json([
                'code' => 'EAN_TAKEN',
                'message' => 'El EAN ya está registrado en un juego activo.',
                'details' => [],
            ], 409);
        }

        $game['category'] = DB::table('categories')
            ->select('id', 'name')
            ->where('id', $game['category_id'])
            ->first();

        unset($game['category_id']);

        return response()->json($game, 201);
    }

    public function update(UpdateGameRequest $request, string $id): JsonResponse
    {
        $data = $request->validated();

        return DB::transaction(function () use ($data, $id): JsonResponse {
            $game = Str::isUuid($id)
                ? DB::table('games')
                    ->where('id', $id)
                    ->whereNull('deleted_at')
                    ->lockForUpdate()
                    ->first()
                : null;

            if ($game === null) {
                return response()->json([
                    'code' => 'NOT_FOUND',
                    'message' => 'El juego no existe o está dado de baja.',
                    'details' => [],
                ], 404);
            }

            $totalUnits = (int) $data['total_units'];
            $loanedUnits = $game->total_units - $game->available_units;
            $availableUnits = $totalUnits - $loanedUnits;

            if ($availableUnits < 0) {
                $message = 'El total no puede ser menor que la cantidad de unidades prestadas.';

                return response()->json([
                    'code' => 'VALIDATION_ERROR',
                    'message' => $message,
                    'details' => [['field' => 'total_units', 'issue' => $message]],
                ], 422);
            }

            $changes = [
                'title' => $data['title'],
                'designer' => $data['designer'],
                'category_id' => $data['category_id'],
                'total_units' => $totalUnits,
                'available_units' => $availableUnits,
            ];

            DB::table('games')->where('id', $id)->update($changes);

            return response()->json([
                'id' => $game->id,
                'title' => $changes['title'],
                'designer' => $changes['designer'],
                'ean' => $game->ean,
                'category' => DB::table('categories')
                    ->select('id', 'name')
                    ->where('id', $changes['category_id'])
                    ->first(),
                'total_units' => $totalUnits,
                'available_units' => $availableUnits,
                'cover_key' => $game->cover_key,
            ]);
        });
    }

    public function destroy(string $id): Response|JsonResponse
    {
        return DB::transaction(function () use ($id): Response|JsonResponse {
            $game = Str::isUuid($id)
                ? DB::table('games')
                    ->where('id', $id)
                    ->whereNull('deleted_at')
                    ->lockForUpdate()
                    ->first()
                : null;

            if ($game === null) {
                return response()->json([
                    'code' => 'NOT_FOUND',
                    'message' => 'El juego no existe o está dado de baja.',
                    'details' => [],
                ], 404);
            }

            $hasOpenLoans = DB::table('loan_items')
                ->where('game_id', $game->id)
                ->whereNull('returned_at')
                ->exists();

            if ($hasOpenLoans) {
                return response()->json([
                    'code' => 'GAME_HAS_OPEN_LOANS',
                    'message' => 'El juego tiene préstamos sin devolver.',
                    'details' => [],
                ], 409);
            }

            DB::table('games')
                ->where('id', $game->id)
                ->update(['deleted_at' => now()]);

            return response()->noContent();
        });
    }

        public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:255'],
            'category_id' => ['nullable', 'uuid'],
            'page' => ['sometimes', 'integer', 'min:1'],
            'size' => ['sometimes', 'integer', 'min:1', 'max:50'],
        ]);

        $query = $this->activeGamesQuery();
        $search = trim($filters['q'] ?? '');

        if ($search !== '') {
            $pattern = '%'.strtr($search, ['!' => '!!', '%' => '!%', '_' => '!_']).'%';

            $query->where(function ($query) use ($pattern): void {
                $query->whereRaw("games.title ILIKE ? ESCAPE '!'", [$pattern])
                    ->orWhereRaw("games.designer ILIKE ? ESCAPE '!'", [$pattern]);
            });
        }

        if (isset($filters['category_id'])) {
            $query->where('games.category_id', $filters['category_id']);
        }

        $page = (int) ($filters['page'] ?? 1);
        $size = (int) ($filters['size'] ?? 20);
        $games = $query->orderBy('games.title')
            ->orderBy('games.id')
            ->paginate($size, ['*'], 'page', $page);

        return response()->json([
            'items' => $games->getCollection()->map($this->catalogGame(...))->values(),
            'page' => $games->currentPage(),
            'size' => $games->perPage(),
            'total' => $games->total(),
        ]);
    }

    public function show(string $id): JsonResponse
    {
        $game = Str::isUuid($id)
            ? $this->activeGamesQuery()->where('games.id', $id)->first()
            : null;

        if ($game === null) {
            return response()->json([
                'code' => 'NOT_FOUND',
                'message' => 'El juego no existe o está dado de baja.',
                'details' => [],
            ], 404);
        }

        return response()->json($this->catalogGame($game));
    }

    private function activeGamesQuery(): \Illuminate\Database\Query\Builder
    {
        return DB::table('games')
            ->join('categories', 'categories.id', '=', 'games.category_id')
            ->whereNull('games.deleted_at')
            ->select([
                'games.id',
                'games.title',
                'games.designer',
                'games.ean',
                'games.category_id',
                'categories.name as category_name',
                'games.total_units',
                'games.available_units',
                'games.cover_key',
            ]);
    }

    private function catalogGame(object $game): array
    {
        return [
            'id' => $game->id,
            'title' => $game->title,
            'designer' => $game->designer,
            'ean' => $game->ean,
            'category' => [
                'id' => $game->category_id,
                'name' => $game->category_name,
            ],
            'total_units' => (int) $game->total_units,
            'available_units' => (int) $game->available_units,
            'cover_key' => $game->cover_key,
        ];
    }
}