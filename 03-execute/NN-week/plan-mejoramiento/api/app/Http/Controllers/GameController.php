<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreGameRequest;
use App\Http\Requests\UpdateGameRequest;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

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
}