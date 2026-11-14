<?php

namespace Tests\Feature;

use App\Http\Controllers\GameController;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Tests\TestCase;

class DeleteGameTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        Route::delete('/api/testing/games/{id}', [GameController::class, 'destroy']);
    }

    public function test_it_marks_the_game_as_deleted_and_keeps_the_record(): void
    {
        $gameId = $this->createGame();

        $this->deleteJson('/api/testing/games/'.$gameId)->assertNoContent();

        $this->assertNotNull(
            DB::table('games')->where('id', $gameId)->value('deleted_at')
        );
    }

    public function test_it_keeps_a_game_active_when_a_loan_is_open(): void
    {
        $gameId = $this->createGame(availableUnits: 0);
        $memberId = (string) Str::uuid();
        $userId = (string) Str::uuid();
        $loanId = (string) Str::uuid();
        $loanedAt = now();

        DB::table('members')->insert([
            'id' => $memberId,
            'full_name' => 'Socio de prueba',
            'document_number' => $memberId,
        ]);

        DB::table('users')->insert([
            'id' => $userId,
            'username' => 'operator-'.$userId,
            'password_hash' => Hash::make('test-password'),
            'role' => 'operator',
        ]);

        DB::table('loans')->insert([
            'id' => $loanId,
            'member_id' => $memberId,
            'loaned_by_user_id' => $userId,
            'loaned_at' => $loanedAt,
        ]);

        DB::table('loan_items')->insert([
            'id' => (string) Str::uuid(),
            'loan_id' => $loanId,
            'game_id' => $gameId,
            'game_title_snapshot' => 'Juego de prueba',
            'ean_snapshot' => '4006381333931',
            'category_name_snapshot' => 'Prueba '.$gameId,
            'due_at' => $loanedAt->copy()->addDays(10),
            'returned_at' => null,
        ]);

        $this->deleteJson('/api/testing/games/'.$gameId)
            ->assertConflict()
            ->assertJsonPath('code', 'GAME_HAS_OPEN_LOANS');

        $this->assertDatabaseHas('games', [
            'id' => $gameId,
            'deleted_at' => null,
        ]);
    }

    public function test_it_returns_not_found_for_an_already_deleted_game(): void
    {
        $gameId = $this->createGame();

        $this->deleteJson('/api/testing/games/'.$gameId)->assertNoContent();

        $this->deleteJson('/api/testing/games/'.$gameId)
            ->assertNotFound()
            ->assertJsonPath('code', 'NOT_FOUND');
    }

    private function createGame(int $availableUnits = 1): string
    {
        $gameId = (string) Str::uuid();
        $categoryId = (string) Str::uuid();

        DB::table('categories')->insert([
            'id' => $categoryId,
            'name' => 'Prueba '.$gameId,
        ]);

        DB::table('games')->insert([
            'id' => $gameId,
            'title' => 'Juego de prueba',
            'designer' => 'Diseñador de prueba',
            'ean' => '4006381333931',
            'category_id' => $categoryId,
            'total_units' => 1,
            'available_units' => $availableUnits,
        ]);

        return $gameId;
    }
}