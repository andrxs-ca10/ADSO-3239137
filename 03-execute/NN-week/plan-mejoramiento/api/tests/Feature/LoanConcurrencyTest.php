<?php

namespace Tests\Feature;

use Firebase\JWT\JWT;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class LoanConcurrencyTest extends TestCase
{
    public function test_only_one_operator_can_borrow_the_last_unit(): void
    {
        $db = DB::connection();

        if ($db->getDriverName() !== 'pgsql' || $db->getDatabaseName() !== 'board_games_concurrency_test') {
            self::markTestSkipped('Esta prueba requiere PostgreSQL y la base board_games_concurrency_test.');
        }

        $categoryId = (string) Str::uuid();
        $gameId = (string) Str::uuid();
        $memberIds = [(string) Str::uuid(), (string) Str::uuid()];
        $operatorIds = [(string) Str::uuid(), (string) Str::uuid()];
        $secret = bin2hex(random_bytes(32));
        $issuedAt = time();
        $processes = [];
        $lockHeld = false;

        try {
            // Los procesos necesitan ver estos datos antes de competir por la unidad.
            $db->transaction(function () use ($db, $categoryId, $gameId, $memberIds, $operatorIds): void {
                $db->table('categories')->insert([
                    'id' => $categoryId,
                    'name' => 'Concurrencia '.$categoryId,
                ]);

                $db->table('games')->insert([
                    'id' => $gameId,
                    'title' => 'Juego de concurrencia',
                    'designer' => 'Diseñador de prueba',
                    'ean' => '4006381333931',
                    'category_id' => $categoryId,
                    'total_units' => 1,
                    'available_units' => 1,
                ]);

                foreach ($memberIds as $index => $memberId) {
                    $db->table('members')->insert([
                        'id' => $memberId,
                        'full_name' => 'Socio de concurrencia',
                        'document_number' => $memberId,
                    ]);

                    $db->table('users')->insert([
                        'id' => $operatorIds[$index],
                        'username' => 'concurrencia-'.$operatorIds[$index],
                        'password_hash' => Hash::make('ClaveDePrueba123'),
                        'role' => 'operator',
                    ]);
                }
            });

            $db->beginTransaction();
            $lockHeld = true;
            $db->table('games')->where('id', $gameId)->lockForUpdate()->first();

            $workerNames = ['loan-'.$operatorIds[0], 'loan-'.$operatorIds[1]];

            foreach ($memberIds as $index => $memberId) {
                $token = JWT::encode([
                    'sub' => $operatorIds[$index],
                    'role' => 'operator',
                    'iat' => $issuedAt,
                    'exp' => $issuedAt + 300,
                ], $secret, 'HS256');

                $process = $this->loanProcess($workerNames[$index], $secret, $token, $memberId, $gameId);
                $processes[] = $process;
                $process->start();
            }

            $deadline = microtime(true) + 15;
            $waiting = 0;

            do {
                foreach ($processes as $process) {
                    self::assertTrue(
                        $process->isRunning(),
                        'Una solicitud terminó antes de llegar al bloqueo. '.$process->getOutput().' '.$process->getErrorOutput(),
                    );
                }

                $db->selectOne('SELECT pg_stat_clear_snapshot()');

                $waiting = (int) $db->selectOne(
                    "SELECT count(*) AS waiting
                     FROM pg_stat_activity
                     WHERE application_name IN (?, ?)
                       AND wait_event_type = 'Lock'
                       AND query ILIKE '%for update%'",
                    $workerNames,
                )->waiting;

                if ($waiting === 2) {
                    break;
                }

                usleep(50_000);
            } while (microtime(true) < $deadline);

            self::assertSame(2, $waiting, 'Las dos solicitudes deben estar esperando por el juego.');

            $db->commit();
            $lockHeld = false;

            $responses = [];

            foreach ($processes as $process) {
                self::assertSame(0, $process->wait(), $process->getErrorOutput());
                $responses[] = json_decode($process->getOutput(), true, 512, JSON_THROW_ON_ERROR);
            }

            usort($responses, static fn (array $a, array $b): int => $a['status'] <=> $b['status']);

            self::assertSame(201, $responses[0]['status']);
            self::assertSame(409, $responses[1]['status']);
            self::assertSame('GAME_NOT_AVAILABLE', $responses[1]['body']['code']);

            $loanId = $responses[0]['body']['id'];

            self::assertSame([$loanId], $db->table('loans')
                ->whereIn('member_id', $memberIds)
                ->pluck('id')
                ->all());

            self::assertSame([$loanId], $db->table('loan_items')
                ->where('game_id', $gameId)
                ->pluck('loan_id')
                ->all());

            self::assertSame(0, (int) $db->table('games')
                ->where('id', $gameId)
                ->value('available_units'));
        } finally {
            if ($lockHeld) {
                $db->rollBack();
            }

            foreach ($processes as $process) {
                if ($process->isRunning()) {
                    $process->stop();
                }
            }

            $db->transaction(function () use ($db, $gameId, $memberIds, $operatorIds, $categoryId): void {
                $db->table('loan_items')->where('game_id', $gameId)->delete();
                $db->table('loans')->whereIn('member_id', $memberIds)->delete();
                $db->table('games')->where('id', $gameId)->delete();
                $db->table('members')->whereIn('id', $memberIds)->delete();
                $db->table('users')->whereIn('id', $operatorIds)->delete();
                $db->table('categories')->where('id', $categoryId)->delete();
            });
        }
    }

    private function loanProcess(
        string $name,
        string $secret,
        string $token,
        string $memberId,
        string $gameId,
    ): Process {
        $script = <<<'PHP'
            require 'vendor/autoload.php';

            $app = require 'bootstrap/app.php';
            $app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

            \Illuminate\Support\Facades\DB::selectOne(
                "SELECT set_config('application_name', ?, false)",
                [$argv[1]],
            );

            $request = \Illuminate\Http\Request::create(
                '/api/v1/loans',
                'POST',
                [],
                [],
                [],
                [
                    'HTTP_ACCEPT' => 'application/json',
                    'HTTP_AUTHORIZATION' => 'Bearer '.getenv('TEST_LOAN_TOKEN'),
                    'CONTENT_TYPE' => 'application/json',
                ],
                json_encode([
                    'member_id' => $argv[2],
                    'items' => [['game_id' => $argv[3]]],
                ], JSON_THROW_ON_ERROR),
            );

            $kernel = $app->make(\Illuminate\Contracts\Http\Kernel::class);
            $response = $kernel->handle($request);
            $kernel->terminate($request, $response);

            echo json_encode([
                'status' => $response->getStatusCode(),
                'body' => json_decode($response->getContent(), true),
            ], JSON_THROW_ON_ERROR);
            PHP;

        return new Process(
            [PHP_BINARY, '-r', $script, $name, $memberId, $gameId],
            base_path(),
            [
                'APP_ENV' => 'testing',
                'JWT_SECRET' => $secret,
                'TEST_LOAN_TOKEN' => $token,
            ],
            timeout: 30,
        );
    }
}