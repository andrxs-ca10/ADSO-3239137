<?php

namespace Tests\Feature;

use App\Models\User;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use DatabaseTransactions;

    private string $jwtSecret;

    protected function setUp(): void
    {
        parent::setUp();

        $this->jwtSecret = bin2hex(random_bytes(32));
        config(['auth.jwt_secret' => $this->jwtSecret]);
    }

    public function test_valid_credentials_return_a_signed_token_for_eight_hours(): void
    {
        $user = $this->createOperator();

        $response = $this->postJson('/api/v1/auth/login', [
            'username' => '  OperadorPrueba  ',
            'password' => 'ClaveDePrueba123',
        ])
            ->assertOk()
            ->assertJsonPath('token_type', 'Bearer')
            ->assertJsonPath('expires_in', 28800)
            ->assertJsonPath('role', 'operator');

        $claims = JWT::decode(
            $response->json('access_token'),
            new Key($this->jwtSecret, 'HS256'),
        );

        self::assertSame((string) $user->getKey(), $claims->sub);
        self::assertSame('operator', $claims->role);
        self::assertSame(28800, $claims->exp - $claims->iat);
    }

    public function test_unknown_user_and_wrong_password_return_the_same_error(): void
    {
        $this->createOperator();

        $wrongPassword = $this->postJson('/api/v1/auth/login', [
            'username' => 'operadorprueba',
            'password' => 'ClaveIncorrecta123',
        ])
            ->assertUnauthorized()
            ->assertJsonPath('code', 'INVALID_CREDENTIALS');

        $unknownUser = $this->postJson('/api/v1/auth/login', [
            'username' => 'usuarioinexistente',
            'password' => 'ClaveDePrueba123',
        ])
            ->assertUnauthorized()
            ->assertJsonPath('code', 'INVALID_CREDENTIALS');

        self::assertSame($wrongPassword->json(), $unknownUser->json());
    }

    private function createOperator(): User
    {
        return User::query()->create([
            'username' => 'operadorprueba',
            'password_hash' => Hash::make('ClaveDePrueba123'),
            'role' => 'operator',
        ]);
    }
}