<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class GameWriteAuthorizationTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        config(['auth.jwt_secret' => bin2hex(random_bytes(32))]);
    }

    public function test_admin_can_create_update_and_soft_delete_a_game(): void
    {
        $this->withToken($this->loginAs('admin'));

        $data = $this->gameData();
        $created = $this->postJson('/api/v1/games', $data)
            ->assertCreated()
            ->assertJsonPath('available_units', 3);

        $gameId = $created->json('id');
        $gameUrl = '/api/v1/games/'.$gameId;

        unset($data['ean']);
        $data['title'] = 'Juego actualizado';
        $data['total_units'] = 4;

        $this->putJson($gameUrl, $data)
            ->assertOk()
            ->assertJsonPath('title', 'Juego actualizado')
            ->assertJsonPath('available_units', 4);

        $this->deleteJson($gameUrl)->assertNoContent();

        self::assertNotNull(
            DB::table('games')->where('id', $gameId)->value('deleted_at')
        );
    }

    public function test_operator_cannot_write_to_the_catalog(): void
    {
        $data = $this->gameData();
        $gameId = (string) Str::uuid();

        DB::table('games')->insert([
            'id' => $gameId,
            'available_units' => 3,
        ] + $data);

        $gameUrl = '/api/v1/games/'.$gameId;
        $this->withToken($this->loginAs('operator'));

        $this->postJson('/api/v1/games', array_replace($data, ['ean' => '96385074']))
            ->assertForbidden()
            ->assertJsonPath('code', 'FORBIDDEN_ROLE');

        unset($data['ean']);
        $this->putJson($gameUrl, array_replace($data, ['title' => 'Cambio no permitido']))
            ->assertForbidden()
            ->assertJsonPath('code', 'FORBIDDEN_ROLE');

        $this->deleteJson($gameUrl)
            ->assertForbidden()
            ->assertJsonPath('code', 'FORBIDDEN_ROLE');

        $this->assertDatabaseMissing('games', ['ean' => '96385074']);
        $this->assertDatabaseHas('games', [
            'id' => $gameId,
            'title' => $data['title'],
            'deleted_at' => null,
        ]);
    }

    private function loginAs(string $role): string
    {
        $username = 'catalogo-'.Str::uuid();
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

    private function gameData(): array
    {
        $categoryId = (string) Str::uuid();

        DB::table('categories')->insert([
            'id' => $categoryId,
            'name' => 'Prueba '.$categoryId,
        ]);

        return [
            'title' => 'Juego de prueba',
            'designer' => 'Diseñador de prueba',
            'ean' => '4006381333931',
            'category_id' => $categoryId,
            'total_units' => 3,
        ];
    }
}