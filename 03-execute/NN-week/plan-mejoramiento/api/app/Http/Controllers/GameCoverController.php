<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreGameCoverRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class GameCoverController extends Controller
{
    public function store(StoreGameCoverRequest $request, string $id): JsonResponse
    {
        if (! Str::isUuid($id)) {
            return $this->gameNotFound();
        }

        $file = $request->file('file');
        $disk = Storage::disk('local');
        $newCoverKey = null;

        try {
            $result = DB::transaction(function () use ($id, $file, &$newCoverKey): ?array {
                $game = DB::table('games')
                    ->where('id', $id)
                    ->whereNull('deleted_at')
                    ->lockForUpdate()
                    ->first();

                if ($game === null) {
                    return null;
                }

                $storedPath = $file->store('game-covers', 'local');

                if ($storedPath === false) {
                    throw new RuntimeException('No se pudo guardar la portada.');
                }

                $newCoverKey = $storedPath;

                DB::table('games')->where('id', $game->id)
                    ->update(['cover_key' => $newCoverKey]);

                return [
                    'previous_cover_key' => $game->cover_key,
                    'game' => [
                        'id' => $game->id,
                        'title' => $game->title,
                        'designer' => $game->designer,
                        'ean' => $game->ean,
                        'category' => DB::table('categories')
                            ->where('id', $game->category_id)
                            ->first(['id', 'name']),
                        'total_units' => (int) $game->total_units,
                        'available_units' => (int) $game->available_units,
                        'cover_key' => $newCoverKey,
                    ],
                ];
            });
        } catch (Throwable $exception) {
            if ($newCoverKey !== null) {
                $disk->delete($newCoverKey);
            }

            throw $exception;
        }

        if ($result === null) {
            return $this->gameNotFound();
        }

        if ($result['previous_cover_key'] !== null) {
            $disk->delete($result['previous_cover_key']);
        }

        return response()->json($result['game']);
    }

    public function show(string $id): StreamedResponse|JsonResponse
    {
        $game = Str::isUuid($id)
            ? DB::table('games')->where('id', $id)->whereNull('deleted_at')->first(['cover_key'])
            : null;

        if ($game === null) {
            return $this->gameNotFound();
        }

        $disk = Storage::disk('local');

        if ($game->cover_key === null || ! $disk->exists($game->cover_key)) {
            return response()->json([
                'code' => 'NOT_FOUND',
                'message' => 'El juego no tiene una portada disponible.',
                'details' => [],
            ], 404);
        }

        return $disk->response($game->cover_key);
    }

    public function destroy(string $id): Response|JsonResponse
    {
        if (! Str::isUuid($id)) {
            return $this->gameNotFound();
        }

        $game = DB::transaction(function () use ($id): ?object {
            $game = DB::table('games')
                ->where('id', $id)
                ->whereNull('deleted_at')
                ->lockForUpdate()
                ->first(['id', 'cover_key']);

            if ($game !== null && $game->cover_key !== null) {
                DB::table('games')->where('id', $game->id)
                    ->update(['cover_key' => null]);
            }

            return $game;
        });

        if ($game === null) {
            return $this->gameNotFound();
        }

        if ($game->cover_key !== null) {
            Storage::disk('local')->delete($game->cover_key);
        }

        return response()->noContent();
    }

    private function gameNotFound(): JsonResponse
    {
        return response()->json([
            'code' => 'NOT_FOUND',
            'message' => 'El juego no existe o está dado de baja.',
            'details' => [],
        ], 404);
    }
}