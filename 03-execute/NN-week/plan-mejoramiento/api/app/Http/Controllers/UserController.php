<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreUserRequest;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
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

        try {
            DB::table('users')->insert($user + [
                'password_hash' => Hash::make($data['password']),
            ]);
        } catch (UniqueConstraintViolationException $exception) {
            if (! str_contains($exception->errorInfo[2] ?? '', '"users_username_unique"')) {
                throw $exception;
            }

            return response()->json([
                'code' => 'USERNAME_TAKEN',
                'message' => 'Ese nombre de usuario ya está en uso.',
                'details' => [],
            ], 409);
        }

        return response()->json($user, 201);
    }
}