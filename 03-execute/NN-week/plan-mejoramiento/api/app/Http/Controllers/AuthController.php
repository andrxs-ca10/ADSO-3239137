<?php

namespace App\Http\Controllers;

use App\Http\Requests\LoginRequest;
use App\Models\User;
use Firebase\JWT\JWT;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use RuntimeException;

class AuthController extends Controller
{
    public function login(LoginRequest $request): JsonResponse
    {
        $data = $request->validated();
        $username = Str::lower(trim($data['username']));
        $password = $data['password'];

        $user = User::query()->where('username', $username)->first();

        if (
            $user === null
            || strlen($password) > 72
            || str_contains($password, "\0")
            || ! Hash::check($password, $user->getAuthPassword())
        ) {
            return response()->json([
                'code' => 'INVALID_CREDENTIALS',
                'message' => 'Usuario o contraseña incorrectos.',
                'details' => [],
            ], 401);
        }

        $key = config('auth.jwt_secret');

        if (! is_string($key) || strlen($key) < 32) {
            throw new RuntimeException('Falta configurar la clave de firma JWT.');
        }

        $issuedAt = now()->timestamp;
        $expiresIn = 8 * 60 * 60;

        $token = JWT::encode([
            'sub' => (string) $user->getAuthIdentifier(),
            'role' => $user->role,
            'iat' => $issuedAt,
            'exp' => $issuedAt + $expiresIn,
        ], $key, 'HS256');

        return response()->json([
            'access_token' => $token,
            'token_type' => 'Bearer',
            'expires_in' => $expiresIn,
            'role' => $user->role,
        ]);
    }
}