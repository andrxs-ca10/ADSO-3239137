<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class StoreLoanTest extends TestCase
{
    use DatabaseTransactions;

    private string $memberId;
    private string $categoryId;
    private string $operatorId;

    protected function setUp(): void
    {
        parent::setUp();

        config(['auth.jwt_secret' => bin2hex(random_bytes(32))]);

        $this->memberId = (string) Str::uuid();
        $this->categoryId = (string) Str::uuid();
        $this->operatorId = (string) Str::uuid();

        DB::table('members')->insert([
            'id' => $this->memberId,
            'full_name' => 'Socio de prueba',
            'document_number' => $this->memberId,
        ]);

        DB::table('categories')->insert([
            'id' => $this->categoryId,
            'name' => 'Categoría '.$this->categoryId,
        ]);

        $username = 'operator-'.$this->operatorId;
        $password = 'test-password';

        DB::table('users')->insert([
            'id' => $this->operatorId,
            'username' => $username,
            'password_hash' => Hash::make($password),
            'role' => 'operator',
        ]);

        $token = $this->postJson('/api/v1/auth/login', [
            'username' => $username,
            'password' => $password,
        ])->assertOk()->json('access_token');

        $this->withToken($token);
    }

    public function test_it_registers_a_loan_due_in_ten_days(): void
    {
        $this->travelTo(now('UTC')->startOfSecond());
        $gameId = $this->createGame('4006381333931');

        $response = $this->borrow([$gameId])
            ->assertCreated()
            ->assertJsonPath('status', 'open')
            ->assertJsonPath('items.0.game_id', $gameId)
            ->assertJsonPath('items.0.due_at', now('UTC')->addDays(10)->toIso8601ZuluString())
            ->assertJsonPath('items.0.returned_at', null);

        $this->assertDatabaseHas('loans', [
            'id' => $response->json('id'),
            'member_id' => $this->memberId,
            'loaned_by_user_id' => $this->operatorId,
        ]);

        $this->assertDatabaseHas('loan_items', [
            'loan_id' => $response->json('id'),
            'game_id' => $gameId,
            'ean_snapshot' => '4006381333931',
        ]);

        $this->assertDatabaseHas('games', [
            'id' => $gameId,
            'available_units' => 0,
        ]);
    }

    public function test_it_counts_previous_loans_toward_the_member_limit(): void
    {
        $this->borrow([
            $this->createGame('4006381333931'),
            $this->createGame('0123456789012'),
            $this->createGame('96385074'),
        ])->assertCreated();

        $this->borrow([$this->createGame('5901234123457')])->assertCreated();

        $fifthGame = $this->createGame('9780140328721');

        $this->borrow([$fifthGame])
            ->assertUnprocessable()
            ->assertJsonPath('code', 'MEMBER_LIMIT_EXCEEDED');

        $this->assertDatabaseMissing('loan_items', ['game_id' => $fifthGame]);
        $this->assertDatabaseHas('games', ['id' => $fifthGame, 'available_units' => 1]);

        self::assertSame(2, DB::table('loans')->where('member_id', $this->memberId)->count());
    }

    public function test_it_rejects_the_whole_loan_when_a_game_is_unavailable(): void
    {
        $availableGame = $this->createGame('4006381333931');
        $unavailableGame = $this->createGame('0123456789012', availableUnits: 0);

        $this->borrow([$availableGame, $unavailableGame])
            ->assertConflict()
            ->assertJsonPath('code', 'GAME_NOT_AVAILABLE');

        $this->assertDatabaseMissing('loans', ['member_id' => $this->memberId]);
        $this->assertDatabaseHas('games', ['id' => $availableGame, 'available_units' => 1]);
    }

    public function test_it_rejects_a_new_loan_when_the_member_has_an_overdue_item(): void
    {
        $firstGame = $this->createGame('4006381333931');
        $nextGame = $this->createGame('0123456789012');

        $loanId = $this->borrow([$firstGame])->assertCreated()->json('id');
        $loanedAt = now('UTC')->subDays(11);

        DB::table('loans')->where('id', $loanId)
            ->update(['loaned_at' => $loanedAt]);

        DB::table('loan_items')->where('loan_id', $loanId)
            ->update(['due_at' => $loanedAt->copy()->addDays(10)]);

        $this->borrow([$nextGame])
            ->assertConflict()
            ->assertJsonPath('code', 'MEMBER_HAS_OVERDUE');

        $this->assertDatabaseMissing('loan_items', ['game_id' => $nextGame]);
        $this->assertDatabaseHas('games', ['id' => $nextGame, 'available_units' => 1]);

        self::assertSame(1, DB::table('loans')->where('member_id', $this->memberId)->count());
    }

    public function test_it_rejects_repeated_games_in_the_same_request(): void
    {
        $gameId = $this->createGame('4006381333931');

        $this->borrow([$gameId, $gameId])
            ->assertUnprocessable()
            ->assertJsonPath('code', 'DUPLICATE_GAME_IN_LOAN');

        $this->assertDatabaseMissing('loans', ['member_id' => $this->memberId]);
        $this->assertDatabaseHas('games', ['id' => $gameId, 'available_units' => 1]);
    }

    private function createGame(string $ean, int $availableUnits = 1): string
    {
        $id = (string) Str::uuid();

        DB::table('games')->insert([
            'id' => $id,
            'title' => 'Juego de prueba',
            'designer' => 'Diseñador de prueba',
            'ean' => $ean,
            'category_id' => $this->categoryId,
            'total_units' => 1,
            'available_units' => $availableUnits,
        ]);

        return $id;
    }

    private function borrow(array $gameIds): TestResponse
    {
        return $this->postJson('/api/v1/loans', [
            'member_id' => $this->memberId,
            'items' => array_map(
                static fn (string $gameId): array => ['game_id' => $gameId],
                $gameIds,
            ),
        ]);
    }
}