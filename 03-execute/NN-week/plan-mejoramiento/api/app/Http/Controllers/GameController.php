<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreGameRequest;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
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
}