<?php

namespace Tests\Feature;

use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class DatabaseConstraintsTest extends TestCase
{
    use DatabaseTransactions;

    public function test_a_game_requires_an_existing_category(): void
    {
        $game = $this->gameData((string) Str::uuid());

        $this->assertInsertRejected($game, '23503');

        $this->assertDatabaseMissing('games', ['id' => $game['id']]);
    }

    public function test_two_active_games_cannot_share_an_ean(): void
    {
        $game = $this->gameData($this->createCategory());
        DB::table('games')->insert($game);

        $duplicate = array_replace($game, [
            'id' => (string) Str::uuid(),
            'title' => 'Juego duplicado de prueba',
        ]);

        $this->assertInsertRejected($duplicate, '23505');

        $this->assertDatabaseMissing('games', ['id' => $duplicate['id']]);
    }

    public function test_available_units_must_stay_between_zero_and_total_units(): void
    {
        $game = $this->gameData($this->createCategory());

        foreach ([-1, 3] as $availableUnits) {
            $invalid = array_replace($game, ['available_units' => $availableUnits]);

            $this->assertInsertRejected($invalid, '23514');
            $this->assertDatabaseMissing('games', ['id' => $invalid['id']]);
        }

        DB::table('games')->insert(array_replace($game, ['available_units' => 0]));
        $this->assertDatabaseHas('games', ['id' => $game['id'], 'available_units' => 0]);

        DB::table('games')->where('id', $game['id'])->update(['available_units' => 2]);
        $this->assertDatabaseHas('games', ['id' => $game['id'], 'available_units' => 2]);
    }

    private function assertInsertRejected(array $game, string $sqlState): void
    {
        try {
            DB::transaction(function () use ($game): void {
                DB::table('games')->insert($game);
            });
        } catch (QueryException $exception) {
            self::assertSame($sqlState, $exception->errorInfo[0] ?? null);

            return;
        }

        self::fail("PostgreSQL aceptó un registro que debía rechazar con SQLSTATE {$sqlState}.");
    }

    private function createCategory(): string
    {
        $id = (string) Str::uuid();

        DB::table('categories')->insert([
            'id' => $id,
            'name' => 'Restricciones '.$id,
        ]);

        return $id;
    }

    private function gameData(string $categoryId): array
    {
        return [
            'id' => (string) Str::uuid(),
            'title' => 'Juego de prueba T-18',
            'designer' => 'Prueba local',
            'ean' => '4006381333931',
            'category_id' => $categoryId,
            'total_units' => 2,
            'available_units' => 2,
        ];
    }
}