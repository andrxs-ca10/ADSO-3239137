<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class MostRequestedReportTest extends TestCase
{
    use DatabaseTransactions;

    private const REPORT_URL = '/api/v1/reports/most-requested';

    protected function setUp(): void
    {
        parent::setUp();

        config(['auth.jwt_secret' => bin2hex(random_bytes(32))]);
    }

    public function test_it_ranks_historical_games_within_the_inclusive_utc_range(): void
    {
        $admin = $this->loginAs('admin');
        $memberId = (string) Str::uuid();
        $categoryId = (string) Str::uuid();

        DB::table('members')->insert([
            'id' => $memberId,
            'full_name' => 'Socio de prueba',
            'document_number' => $memberId,
        ]);

        DB::table('categories')->insert([
            'id' => $categoryId,
            'name' => 'Categoría actual '.$categoryId,
        ]);

        $first = $this->createGame($categoryId, '4006381333931');
        $second = $this->createGame($categoryId, '0123456789012');
        $third = $this->createGame($categoryId, '96385074');

        $this->recordLoan($admin, $memberId, $first, 'Zorro', '2026-11-01 00:00:00');
        $this->recordLoan($admin, $memberId, $first, 'Zorro', '2026-11-02 23:59:59');
        $this->recordLoan($admin, $memberId, $second, 'Águila', '2026-11-02 12:00:00');
        $this->recordLoan($admin, $memberId, $third, 'Abeja', '2026-11-02 12:00:00');

        $this->recordLoan($admin, $memberId, $first, 'Zorro', '2026-10-31 23:59:59');
        $this->recordLoan($admin, $memberId, $first, 'Zorro', '2026-11-03 00:00:00');

        $response = $this->getJson(self::REPORT_URL.'?from=2026-11-01&to=2026-11-02')
            ->assertOk()
            ->assertJsonPath('from', '2026-11-01')
            ->assertJsonPath('to', '2026-11-02')
            ->assertJsonCount(3, 'rows');

        self::assertSame([
            [
                'game_id' => $first,
                'game_title' => 'Zorro',
                'category_name' => 'Categoría histórica',
                'times_requested' => 2,
            ],
            [
                'game_id' => $third,
                'game_title' => 'Abeja',
                'category_name' => 'Categoría histórica',
                'times_requested' => 1,
            ],
            [
                'game_id' => $second,
                'game_title' => 'Águila',
                'category_name' => 'Categoría histórica',
                'times_requested' => 1,
            ],
        ], $response->json('rows'));
    }

    public function test_it_rejects_an_inverted_date_range(): void
    {
        $this->loginAs('admin');

        $this->getJson(self::REPORT_URL.'?from=2026-11-02&to=2026-11-01')
            ->assertUnprocessable()
            ->assertJsonPath('code', 'INVALID_DATE_RANGE');
    }

    public function test_an_operator_cannot_access_the_report(): void
    {
        $this->loginAs('operator');

        $this->getJson(self::REPORT_URL.'?from=2026-11-01&to=2026-11-02')
            ->assertForbidden()
            ->assertJsonPath('code', 'FORBIDDEN_ROLE');
    }

    private function loginAs(string $role): User
    {
        $password = 'ClaveDePrueba123';

        $user = User::query()->create([
            'username' => 'reporte-'.Str::uuid(),
            'password_hash' => Hash::make($password),
            'role' => $role,
        ]);

        $token = $this->postJson('/api/v1/auth/login', [
            'username' => $user->username,
            'password' => $password,
        ])->assertOk()->json('access_token');

        $this->withToken($token);

        return $user;
    }

    private function createGame(string $categoryId, string $ean): string
    {
        $gameId = (string) Str::uuid();

        DB::table('games')->insert([
            'id' => $gameId,
            'title' => 'Título actual',
            'designer' => 'Diseñador de prueba',
            'ean' => $ean,
            'category_id' => $categoryId,
            'total_units' => 5,
            'available_units' => 5,
        ]);

        return $gameId;
    }

    private function recordLoan(
        User $user,
        string $memberId,
        string $gameId,
        string $historicalTitle,
        string $loanedAt,
    ): void {
        $loanId = (string) Str::uuid();
        $date = \Carbon\CarbonImmutable::parse($loanedAt, 'UTC');

        DB::table('loans')->insert([
            'id' => $loanId,
            'member_id' => $memberId,
            'loaned_by_user_id' => $user->getKey(),
            'loaned_at' => $date,
        ]);

        DB::table('loan_items')->insert([
            'id' => (string) Str::uuid(),
            'loan_id' => $loanId,
            'game_id' => $gameId,
            'game_title_snapshot' => $historicalTitle,
            'ean_snapshot' => DB::table('games')->where('id', $gameId)->value('ean'),
            'category_name_snapshot' => 'Categoría histórica',
            'due_at' => $date->addDays(10),
            'returned_at' => $date->addDay(),
        ]);
    }
}