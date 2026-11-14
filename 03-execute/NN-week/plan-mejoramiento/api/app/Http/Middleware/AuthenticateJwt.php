<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use DomainException;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;
use UnexpectedValueException;

class AuthenticateJwt
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $token = $request->bearerToken();

        if ($token === null || $token === '') {
            return $this->unauthenticated();
        }

        $secret = config('auth.jwt_secret');

        if (! is_string($secret) || strlen($secret) < 32) {
            throw new RuntimeException('Falta configurar la clave de firma JWT.');
        }

        try {
            $claims = JWT::decode($token, new Key($secret, 'HS256'));
        } catch (UnexpectedValueException | DomainException) {
            return $this->unauthenticated();
        }

        $userId = $claims->sub ?? null;

        if (! is_string($userId) || ! Str::isUuid($userId) || ! is_int($claims->exp ?? null)) {
            return $this->unauthenticated();
        }

        $user = User::query()->find($userId);

        if ($user === null) {
            return $this->unauthenticated();
        }

        if ($roles !== [] && ! in_array($user->role, $roles, true)) {
            return response()->json([
                'code' => 'FORBIDDEN_ROLE',
                'message' => 'Tu rol no tiene permiso para esta operación.',
                'details' => [],
            ], 403);
        }

        $request->setUserResolver(fn () => $user);

        return $next($request);
    }

    private function unauthenticated(): JsonResponse
    {
        return response()->json([
            'code' => 'UNAUTHENTICATED',
            'message' => 'Debes iniciar sesión con un token válido.',
            'details' => [],
        ], 401);
    }
}