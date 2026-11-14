<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreUserRequest;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class UserController extends Controller
{
    public function store(StoreUserRequest $request): JsonResponse
    {
        $data = $request->validated();

        $user = [
            'id' => (string) Str::uuid(),
            'username' => $data['username'],
            'role' => 'operator',
        ];

        $passwordHash = Hash::make($data['password']);

        try {
            DB::transaction(function () use ($user, $passwordHash): void {
                DB::table('users')->insert($user + [
                    'password_hash' => $passwordHash,
                ]);
            });
        } catch (QueryException $exception) {
            if (
                $exception instanceof UniqueConstraintViolationException
                && str_contains($exception->errorInfo[2] ?? '', '"users_username_unique"')
            ) {
                return response()->json([
                    'code' => 'USERNAME_TAKEN',
                    'message' => 'Ese nombre de usuario ya está en uso.',
                    'details' => [],
                ], 409);
            }

            Log::error('No se pudo guardar el operador.', [
                'sqlstate' => $exception->errorInfo[0] ?? null,
            ]);

            return response()->json([
                'code' => 'INTERNAL_ERROR',
                'message' => 'No se pudo crear el operador.',
                'details' => [],
            ], 500);
        }

        return response()->json($user, 201);
    }
}