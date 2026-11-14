<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\CategorySeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class ListCategoriesTest extends TestCase
{
    use DatabaseTransactions;

    public function test_operator_can_list_the_initial_categories_in_alphabetical_order(): void
    {
        config(['auth.jwt_secret' => bin2hex(random_bytes(32))]);

        $password = 'ClaveDePrueba123';

        $operator = User::query()->create([
            'username' => 'categorias-'.Str::uuid(),
            'password_hash' => Hash::make($password),
            'role' => 'operator',
        ]);

        $token = $this->postJson('/api/v1/auth/login', [
            'username' => $operator->username,
            'password' => $password,
        ])->assertOk()->json('access_token');

        $this->seed(CategorySeeder::class);

        $names = ['cartas', 'estrategia', 'familiares', 'infantiles', 'rol'];

        $idsByName = DB::table('categories')
            ->whereIn('name', $names)
            ->pluck('id', 'name');

        $response = $this->withToken($token)
            ->getJson('/api/v1/categories')
            ->assertOk()
            ->assertJsonStructure(['*' => ['id', 'name']]);

        $initialCategories = collect($response->json())
            ->whereInStrict('name', $names)
            ->values()
            ->all();

        $expected = array_map(
            static fn (string $name): array => ['id' => $idsByName[$name], 'name' => $name],
            $names,
        );

        self::assertSame($expected, $initialCategories);
    }

    public function test_listing_categories_requires_a_token(): void
    {
        $this->getJson('/api/v1/categories')
            ->assertUnauthorized()
            ->assertJsonPath('code', 'UNAUTHENTICATED');
    }
}