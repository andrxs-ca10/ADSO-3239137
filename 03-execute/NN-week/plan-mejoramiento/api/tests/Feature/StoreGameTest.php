<?php

namespace Tests\Feature;

use App\Http\Controllers\GameController;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Tests\TestCase;

class StoreGameTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        Route::post('/api/testing/games', [GameController::class, 'store']);
    }

    public function test_it_creates_a_game_with_all_units_available(): void
    {
        $response = $this->postJson('/api/testing/games', $this->gameData());

        $response->assertCreated()
            ->assertJsonPath('total_units', 3)
            ->assertJsonPath('available_units', 3)
            ->assertJsonPath('ean', '0123456789012');

        $this->assertDatabaseHas('games', [
            'id' => $response->json('id'),
            'available_units' => 3,
        ]);
    }

    public function test_it_rejects_a_duplicate_active_ean(): void
    {
        $data = $this->gameData();

        $this->postJson('/api/testing/games', $data)->assertCreated();

        $this->postJson('/api/testing/games', $data)
            ->assertStatus(409)
            ->assertJsonPath('code', 'EAN_TAKEN');
    }

    public function test_it_rejects_zero_total_units(): void
    {
        $data = $this->gameData();
        $data['total_units'] = 0;

        $this->postJson('/api/testing/games', $data)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('total_units');
    }

    private function gameData(): array
    {
        $categoryId = (string) Str::uuid();

        DB::table('categories')->insert([
            'id' => $categoryId,
            'name' => 'Prueba '.Str::uuid(),
        ]);

        return [
            'title' => 'Juego de prueba',
            'designer' => 'Diseñador de prueba',
            'ean' => '0123456789012',
            'category_id' => $categoryId,
            'total_units' => 3,
        ];
    }
}