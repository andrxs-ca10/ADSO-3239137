<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class MemberReadTest extends TestCase
{
    use DatabaseTransactions;

    public function test_operator_can_search_by_name_or_document_and_paginate(): void
    {
        $this->authenticateOperator();

        $search = (string) Str::uuid();
        $first = $this->createMember('Ana '.$search, '001-'.$search);
        $second = $this->createMember('Bruno', '002-'.$search);

        $url = '/api/v1/members?'.http_build_query([
            'q' => strtoupper($search),
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
            ->assertJsonPath('items.0.id', $second)
            ->assertJsonPath('items.0.document_number', '002-'.$search);
    }

    public function test_detail_counts_pending_games_including_overdue_ones(): void
    {
        $this->authenticateOperator();

        $document = '003-'.Str::uuid();
        $memberId = $this->createMember('Carolina', $document);
        $categoryId = (string) Str::uuid();

        DB::table('categories')->insert([
            'id' => $categoryId,
            'name' => 'Prueba '.$categoryId,
        ]);

        $gameIds = [];

        foreach (['4006381333931', '0123456789012', '96385074'] as $ean) {
            $gameId = (string) Str::uuid();

            DB::table('games')->insert([
                'id' => $gameId,
                'title' => 'Juego de prueba',
                'designer' => 'Diseñador de prueba',
                'ean' => $ean,
                'category_id' => $categoryId,
                'total_units' => 1,
                'available_units' => 1,
            ]);

            $gameIds[] = $gameId;
        }

        $loan = $this->postJson('/api/v1/loans', [
            'member_id' => $memberId,
            'items' => array_map(
                static fn (string $id): array => ['game_id' => $id],
                $gameIds,
            ),
        ])->assertCreated();

        $loanId = $loan->json('id');
        $returnedItemId = $loan->json('items.2.id');

        $this->postJson("/api/v1/loans/{$loanId}/items/{$returnedItemId}/return")
            ->assertOk();

        $loanedAt = now('UTC')->subDays(11);

        DB::table('loans')->where('id', $loanId)
            ->update(['loaned_at' => $loanedAt]);

        DB::table('loan_items')->where('loan_id', $loanId)
            ->update(['due_at' => $loanedAt->copy()->addDays(10)]);

        $this->getJson('/api/v1/members/'.$memberId)
            ->assertOk()
            ->assertJsonPath('id', $memberId)
            ->assertJsonPath('full_name', 'Carolina')
            ->assertJsonPath('document_number', $document)
            ->assertJsonPath('active_loans', 2);
    }

    public function test_detail_returns_404_for_an_unknown_member(): void
    {
        $this->authenticateOperator();

        $this->getJson('/api/v1/members/'.Str::uuid())
            ->assertNotFound()
            ->assertJsonPath('code', 'NOT_FOUND');
    }

    private function createMember(string $name, string $document): string
    {
        $id = (string) Str::uuid();

        DB::table('members')->insert([
            'id' => $id,
            'full_name' => $name,
            'document_number' => $document,
        ]);

        return $id;
    }

    private function authenticateOperator(): void
    {
        config(['auth.jwt_secret' => bin2hex(random_bytes(32))]);

        $password = 'ClaveDePrueba123';
        $operator = User::query()->create([
            'username' => 'socios-'.Str::uuid(),
            'password_hash' => Hash::make($password),
            'role' => 'operator',
        ]);

        $token = $this->postJson('/api/v1/auth/login', [
            'username' => $operator->username,
            'password' => $password,
        ])->assertOk()->json('access_token');

        $this->withToken($token);
    }
}