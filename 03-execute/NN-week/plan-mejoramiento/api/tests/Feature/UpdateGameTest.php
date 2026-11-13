<?php

namespace Tests\Feature;

use App\Http\Controllers\GameController;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Tests\TestCase;

class UpdateGameTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        Route::put('/api/testing/games/{id}', [GameController::class, 'update']);
    }

    public function test_it_adjusts_available_units_without_changing_loaned_units(): void
    {
        $game = $this->createGame();

        $this->putJson('/api/testing/games/'.$game['id'], $this->updateData($game, 4))
            ->assertOk()
            ->assertJsonPath('total_units', 4)
            ->assertJsonPath('available_units', 1);

        $this->assertDatabaseHas('games', [
            'id' => $game['id'],
            'total_units' => 4,
            'available_units' => 1,
        ]);
    }

    public function test_it_rejects_a_total_below_the_number_of_loaned_units(): void
    {
        $game = $this->createGame();

        $this->putJson('/api/testing/games/'.$game['id'], $this->updateData($game, 2))
            ->assertUnprocessable()
            ->assertJsonPath('code', 'VALIDATION_ERROR')
            ->assertJsonPath('details.0.field', 'total_units');

        $this->assertDatabaseHas('games', [
            'id' => $game['id'],
            'title' => $game['title'],
            'total_units' => 5,
            'available_units' => 2,
        ]);
    }

    private function createGame(): array
    {
        $categoryId = (string) Str::uuid();

        DB::table('categories')->insert([
            'id' => $categoryId,
            'name' => 'Categoría de prueba '.Str::uuid(),
        ]);

        $game = [
            'id' => (string) Str::uuid(),
            'title' => 'Juego de prueba',
            'designer' => 'Diseñador de prueba',
            'ean' => '4006381333931',
            'category_id' => $categoryId,
            'total_units' => 5,
            'available_units' => 2,
        ];

        DB::table('games')->insert($game);

        return $game;
    }

    private function updateData(array $game, int $totalUnits): array
    {
        return [
            'title' => 'Título actualizado',
            'designer' => $game['designer'],
            'category_id' => $game['category_id'],
            'total_units' => $totalUnits,
        ];
    }
}