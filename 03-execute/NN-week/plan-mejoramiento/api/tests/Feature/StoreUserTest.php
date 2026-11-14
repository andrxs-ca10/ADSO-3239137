<?php

namespace Tests\Feature;

use App\Http\Controllers\UserController;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class StoreUserTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        Route::post('/api/testing/users', [UserController::class, 'store']);
    }

    public function test_it_creates_an_operator_with_a_normalized_username_and_hashed_password(): void
    {
        $data = $this->operatorData();

        $response = $this->postJson('/api/testing/users', $data)
            ->assertCreated()
            ->assertJsonPath('username', 'operador1')
            ->assertJsonPath('role', 'operator')
            ->assertJsonMissingPath('password')
            ->assertJsonMissingPath('password_hash');

        $user = DB::table('users')->where('id', $response->json('id'))->first();

        self::assertNotNull($user);
        self::assertSame('operador1', $user->username);
        self::assertSame('operator', $user->role);
        self::assertTrue(Hash::check($data['password'], $user->password_hash));
    }

    public function test_it_rejects_a_duplicate_normalized_username(): void
    {
        $data = $this->operatorData();

        $this->postJson('/api/testing/users', $data)->assertCreated();

        $data['username'] = 'operador1';

        $this->postJson('/api/testing/users', $data)
            ->assertConflict()
            ->assertJsonPath('code', 'USERNAME_TAKEN');
    }

    public function test_it_rejects_the_admin_role(): void
    {
        $data = $this->operatorData();
        $data['role'] = 'admin';

        $this->postJson('/api/testing/users', $data)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('role');

        $this->assertDatabaseMissing('users', ['username' => 'operador1']);
    }

    private function operatorData(): array
    {
        return [
            'username' => '  Operador1  ',
            'password' => 'ClaveDePrueba123',
            'role' => 'operator',
        ];
    }
}