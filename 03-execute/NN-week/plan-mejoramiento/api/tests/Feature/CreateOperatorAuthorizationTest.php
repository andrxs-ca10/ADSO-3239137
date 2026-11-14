<?php

namespace Tests\Feature;

use App\Models\User;
use Firebase\JWT\JWT;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CreateOperatorAuthorizationTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        config(['auth.jwt_secret' => bin2hex(random_bytes(32))]);
    }

    public function test_admin_can_create_an_operator(): void
    {
        $this->withToken($this->loginAs('admin'))
            ->postJson('/api/v1/users', $this->operatorData())
            ->assertCreated()
            ->assertJsonPath('username', 'operador_nuevo')
            ->assertJsonPath('role', 'operator');

        $this->assertDatabaseHas('users', [
            'username' => 'operador_nuevo',
            'role' => 'operator',
        ]);
    }

    public function test_operator_cannot_create_another_operator(): void
    {
        $this->withToken($this->loginAs('operator'))
            ->postJson('/api/v1/users', $this->operatorData())
            ->assertForbidden()
            ->assertJsonPath('code', 'FORBIDDEN_ROLE');

        $this->assertDatabaseMissing('users', ['username' => 'operador_nuevo']);
    }

    public function test_request_without_a_token_is_rejected(): void
    {
        $this->postJson('/api/v1/users', $this->operatorData())
            ->assertUnauthorized()
            ->assertJsonPath('code', 'UNAUTHENTICATED');

        $this->assertDatabaseMissing('users', ['username' => 'operador_nuevo']);
    }

    public function test_invalid_tokens_cannot_create_an_operator(): void
    {
        $this->loginAs('admin');

        $adminId = User::query()->where('username', 'prueba_admin')->value('id');
        self::assertNotNull($adminId);

        $now = time();
        $claims = [
            'sub' => (string) $adminId,
            'role' => 'admin',
            'iat' => $now - 600,
            'exp' => $now + 300,
        ];

        $tokens = [
            'vencido' => JWT::encode(
                array_replace($claims, ['exp' => $now - 300]),
                config('auth.jwt_secret'),
                'HS256',
            ),
            'firma incorrecta' => JWT::encode(
                $claims,
                bin2hex(random_bytes(32)),
                'HS256',
            ),
            'malformado' => 'token-invalido',
        ];

        foreach ($tokens as $case => $token) {
            $response = $this->withToken($token)
                ->postJson('/api/v1/users', $this->operatorData());

            self::assertSame(401, $response->status(), "Token {$case}.");
            $response->assertJsonPath('code', 'UNAUTHENTICATED');
        }

        $this->assertDatabaseMissing('users', ['username' => 'operador_nuevo']);
    }

    private function loginAs(string $role): string
    {
        $username = "prueba_{$role}";
        $password = 'ClaveDePrueba123';

        User::query()->create([
            'username' => $username,
            'password_hash' => Hash::make($password),
            'role' => $role,
        ]);

        return $this->postJson('/api/v1/auth/login', [
            'username' => $username,
            'password' => $password,
        ])->assertOk()->json('access_token');
    }

    private function operatorData(): array
    {
        return [
            'username' => 'operador_nuevo',
            'password' => 'ClaveDePrueba456',
            'role' => 'operator',
        ];
    }
}