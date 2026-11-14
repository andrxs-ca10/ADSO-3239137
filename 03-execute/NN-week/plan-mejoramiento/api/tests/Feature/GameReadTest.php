<?php

namespace Tests\Feature;

use App\Models\User;
use Firebase\JWT\JWT;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class GameReadTest extends TestCase
{
    use DatabaseTransactions;

    public function test_operator_can_search_filter_and_paginate_active_games(): void
    {
        $this->authenticateOperator();

        $categoryId = $this->createCategory();
        $otherCategoryId = $this->createCategory();

        $first = $this->createGame($categoryId, 'Aventura lunar', 'Ana Pérez', '4006381333931');
        $second = $this->createGame($categoryId, 'Bosque', 'Estudio Lunar', '0123456789012');

        $this->createGame($otherCategoryId, 'Caminata lunar', 'Ana Pérez', '96385074');
        $this->createGame($categoryId, 'Desafío lunar', 'Ana Pérez', '5901234123457', deleted: true);
        $this->createGame($categoryId, 'Estrategia', 'Ana Pérez', '9780140328721');

        $url = '/api/v1/games?'.http_build_query([
            'q' => 'LUNAR',
            'category_id' => $categoryId,
            'size' => 1,
        ]);

        $this->getJson($url.'&page=1')
            ->assertOk()
            ->assertJsonPath('page', 1)
            ->assertJsonPath('size', 1)
            ->assertJsonPath('total', 2)
            ->assertJsonCount(1, 'items')
            ->assertJsonPath('items.0.id', $first);

        $this->getJson($url.'&page=2')
            ->assertOk()
            ->assertJsonPath('page', 2)
            ->assertJsonPath('total', 2)
            ->assertJsonCount(1, 'items')
            ->assertJsonPath('items.0.id', $second);
    }

    public function test_operator_can_view_an_active_game_but_a_deleted_game_returns_404(): void
    {
        $this->authenticateOperator();

        $categoryId = $this->createCategory();
        $active = $this->createGame($categoryId, 'Aventura lunar', 'Ana Pérez', '4006381333931');
        $deleted = $this->createGame($categoryId, 'Bosque', 'Ana Pérez', '0123456789012', deleted: true);

        $this->getJson('/api/v1/games/'.$active)
            ->assertOk()
            ->assertJsonPath('id', $active)
            ->assertJsonPath('category.id', $categoryId)
            ->assertJsonPath('total_units', 3)
            ->assertJsonPath('available_units', 2);

        $this->getJson('/api/v1/games/'.$deleted)
            ->assertNotFound()
            ->assertJsonPath('code', 'NOT_FOUND');
    }

    public function test_catalog_reads_require_a_token(): void
    {
        $this->getJson('/api/v1/games')
            ->assertUnauthorized()
            ->assertJsonPath('code', 'UNAUTHENTICATED');

        $this->getJson('/api/v1/games/'.Str::uuid())
            ->assertUnauthorized()
            ->assertJsonPath('code', 'UNAUTHENTICATED');
    }

    private function authenticateOperator(): void
    {
        $secret = bin2hex(random_bytes(32));
        config(['auth.jwt_secret' => $secret]);

        $operator = User::query()->create([
            'username' => 'catalogo-'.Str::uuid(),
            'password_hash' => Hash::make('ClaveDePrueba123'),
            'role' => 'operator',
        ]);

        $issuedAt = now()->timestamp;

        $this->withToken(JWT::encode([
            'sub' => (string) $operator->getKey(),
            'role' => 'operator',
            'iat' => $issuedAt,
            'exp' => $issuedAt + 300,
        ], $secret, 'HS256'));
    }

    private function createCategory(): string
    {
        $id = (string) Str::uuid();

        DB::table('categories')->insert([
            'id' => $id,
            'name' => 'Prueba '.$id,
        ]);

        return $id;
    }

    private function createGame(
        string $categoryId,
        string $title,
        string $designer,
        string $ean,
        bool $deleted = false,
    ): string {
        $id = (string) Str::uuid();

        DB::table('games')->insert([
            'id' => $id,
            'title' => $title,
            'designer' => $designer,
            'ean' => $ean,
            'category_id' => $categoryId,
            'total_units' => 3,
            'available_units' => 2,
            'deleted_at' => $deleted ? now() : null,
        ]);

        return $id;
    }
}