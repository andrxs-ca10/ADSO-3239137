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
        public function test_it_returns_a_loan_item_only_once(): void
    {
        $gameId = $this->createGame('4006381333931');
        $loan = $this->borrow([$gameId])->assertCreated();

        $loanId = $loan->json('id');
        $itemId = $loan->json('items.0.id');
        $returnUrl = "/api/v1/loans/{$loanId}/items/{$itemId}/return";

        $this->assertDatabaseHas('games', [
            'id' => $gameId,
            'available_units' => 0,
        ]);

        $this->postJson($returnUrl)
            ->assertOk()
            ->assertJsonPath('id', $itemId)
            ->assertJsonPath('game_id', $gameId);

        $returnedAt = DB::table('loan_items')->where('id', $itemId)->value('returned_at');
        self::assertNotNull($returnedAt);

        $this->assertDatabaseHas('games', [
            'id' => $gameId,
            'available_units' => 1,
        ]);

        $this->postJson($returnUrl)
            ->assertConflict()
            ->assertJsonPath('code', 'ITEM_ALREADY_RETURNED');

        $this->assertDatabaseHas('games', [
            'id' => $gameId,
            'available_units' => 1,
        ]);

        $this->assertDatabaseHas('loan_items', [
            'id' => $itemId,
            'returned_at' => $returnedAt,
        ]);
    }

        public function test_it_lists_only_unreturned_overdue_items_in_order(): void
    {
        $this->travelTo(now('UTC')->startOfSecond());

        $olderLoan = $this->borrow([$this->createGame('4006381333931')])->assertCreated();
        $newerLoan = $this->borrow([$this->createGame('0123456789012')])->assertCreated();
        $returnedLoan = $this->borrow([$this->createGame('96385074')])->assertCreated();
        $currentLoan = $this->borrow([$this->createGame('5901234123457')])->assertCreated();

        foreach ([[$olderLoan, 13], [$newerLoan, 11], [$returnedLoan, 12]] as [$loan, $daysAgo]) {
            $loanedAt = now('UTC')->subDays($daysAgo);

            DB::table('loans')->where('id', $loan->json('id'))
                ->update(['loaned_at' => $loanedAt]);

            DB::table('loan_items')->where('id', $loan->json('items.0.id'))
                ->update(['due_at' => $loanedAt->copy()->addDays(10)]);
        }

        $returnedLoanId = $returnedLoan->json('id');
        $returnedItemId = $returnedLoan->json('items.0.id');

        $this->postJson("/api/v1/loans/{$returnedLoanId}/items/{$returnedItemId}/return")
            ->assertOk();

        $response = $this->getJson('/api/v1/loans/overdue')->assertOk();

        $loanIds = [
            $olderLoan->json('id'),
            $newerLoan->json('id'),
            $returnedLoanId,
            $currentLoan->json('id'),
        ];

        $items = collect($response->json('items'))
            ->whereInStrict('loan_id', $loanIds)
            ->values();

        self::assertSame(
            [$olderLoan->json('items.0.id'), $newerLoan->json('items.0.id')],
            $items->pluck('item_id')->all(),
        );
        self::assertSame([3, 1], $items->pluck('days_late')->all());
    }

        public function test_operator_can_view_a_loan_with_its_items(): void
    {
        $gameId = $this->createGame('4006381333931');
        $loan = $this->borrow([$gameId])->assertCreated();

        $this->getJson('/api/v1/loans/'.$loan->json('id'))
            ->assertOk()
            ->assertJsonPath('id', $loan->json('id'))
            ->assertJsonPath('member_id', $this->memberId)
            ->assertJsonPath('loaned_at', $loan->json('loaned_at'))
            ->assertJsonPath('status', 'open')
            ->assertJsonCount(1, 'items')
            ->assertJsonPath('items.0.id', $loan->json('items.0.id'))
            ->assertJsonPath('items.0.game_id', $gameId)
            ->assertJsonPath('items.0.game_title_snapshot', 'Juego de prueba')
            ->assertJsonPath('items.0.ean_snapshot', '4006381333931')
            ->assertJsonPath('items.0.due_at', $loan->json('items.0.due_at'))
            ->assertJsonPath('items.0.returned_at', null);
    }

    public function test_loan_detail_returns_404_for_an_unknown_loan(): void
    {
        $this->getJson('/api/v1/loans/'.Str::uuid())
            ->assertNotFound()
            ->assertJsonPath('code', 'NOT_FOUND');
    }

        public function test_operator_can_filter_and_paginate_loans(): void
    {
        $older = $this->borrow([$this->createGame('4006381333931')])->assertCreated();
        $newer = $this->borrow([$this->createGame('0123456789012')])->assertCreated();

        $this->travelTo(now('UTC')->startOfSecond());
        $day = now('UTC')->subDays(11)->startOfDay();

        DB::table('loans')->where('id', $older->json('id'))
            ->update(['loaned_at' => $day]);

        DB::table('loans')->where('id', $newer->json('id'))
            ->update(['loaned_at' => $day->copy()->endOfDay()]);

        DB::table('loan_items')->where('loan_id', $older->json('id'))
            ->update(['due_at' => $day->copy()->addDays(10)]);

        $url = '/api/v1/loans?'.http_build_query([
            'member_id' => $this->memberId,
            'from' => $day->toDateString(),
            'to' => $day->toDateString(),
            'size' => 1,
        ]);

        $this->getJson($url.'&page=1')
            ->assertOk()
            ->assertJsonPath('page', 1)
            ->assertJsonPath('size', 1)
            ->assertJsonPath('total', 2)
            ->assertJsonCount(1, 'items')
            ->assertJsonPath('items.0.id', $newer->json('id'))
            ->assertJsonPath('items.0.status', 'open');

        $this->getJson($url.'&page=2')
            ->assertOk()
            ->assertJsonPath('page', 2)
            ->assertJsonPath('total', 2)
            ->assertJsonCount(1, 'items')
            ->assertJsonPath('items.0.id', $older->json('id'));

        $this->getJson($url.'&status=overdue')
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('items.0.id', $older->json('id'))
            ->assertJsonPath('items.0.status', 'overdue');
    }

    public function test_loan_list_rejects_an_inverted_date_range(): void
    {
        $this->getJson('/api/v1/loans?from=2026-11-14&to=2026-11-13')
            ->assertUnprocessable()
            ->assertJsonPath('code', 'INVALID_DATE_RANGE');
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