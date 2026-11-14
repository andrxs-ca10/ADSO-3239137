<?php

namespace Tests\Feature;

use App\Http\Controllers\UserController;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use PDOException;
use Tests\TestCase;

class OperatorRegistrationLoggingTest extends TestCase
{
    public function test_database_failure_keeps_credentials_out_of_the_response_and_log(): void
    {
        config(['app.debug' => true]);

        Route::post('/api/testing/users', [UserController::class, 'store']);

        $password = 'ClaveDePrueba789';
        $passwordHash = Hash::make($password);

        Hash::shouldReceive('make')
            ->once()
            ->with($password)
            ->andReturn($passwordHash);

        $databaseError = new PDOException('Fallo simulado al guardar el operador.');
        $databaseError->errorInfo = ['XX000', 7, 'Fallo simulado.'];

        $exception = new QueryException(
            'pgsql',
            'insert into users (password_hash) values (?)',
            [$passwordHash],
            $databaseError,
        );

        DB::shouldReceive('transaction')
            ->once()
            ->andThrow($exception);

        Log::shouldReceive('error')
            ->once()
            ->with('No se pudo guardar el operador.', ['sqlstate' => 'XX000']);

        $this->postJson('/api/testing/users', [
            'username' => 'operador_prueba',
            'password' => $password,
            'role' => 'operator',
        ])
            ->assertStatus(500)
            ->assertExactJson([
                'code' => 'INTERNAL_ERROR',
                'message' => 'No se pudo crear el operador.',
                'details' => [],
            ]);
    }
}