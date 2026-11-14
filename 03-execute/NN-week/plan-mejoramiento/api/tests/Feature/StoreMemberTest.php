<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class StoreMemberTest extends TestCase
{
    use DatabaseTransactions;

    private const MEMBERS_URL = '/api/v1/members';

    public function test_operator_can_create_a_member_and_preserve_leading_zeroes(): void
    {
        $this->authenticateOperator();

        $data = $this->memberData();

        $response = $this->postJson(self::MEMBERS_URL, $data)
            ->assertCreated()
            ->assertJsonPath('full_name', $data['full_name'])
            ->assertJsonPath('document_number', $data['document_number']);

        $this->assertDatabaseHas('members', ['id' => $response->json('id')] + $data);
    }

    public function test_it_rejects_a_duplicate_document(): void
    {
        $this->authenticateOperator();

        $data = $this->memberData();

        $this->postJson(self::MEMBERS_URL, $data)->assertCreated();

        $data['full_name'] = 'Segundo socio';

        $this->postJson(self::MEMBERS_URL, $data)
            ->assertConflict()
            ->assertJsonPath('code', 'DOCUMENT_TAKEN');

        $this->assertDatabaseMissing('members', $data);
    }

    public function test_it_requires_name_and_document(): void
    {
        $this->authenticateOperator();

        $this->postJson(self::MEMBERS_URL, [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['full_name', 'document_number']);
    }

    public function test_creating_a_member_requires_a_token(): void
    {
        $data = $this->memberData();

        $this->postJson(self::MEMBERS_URL, $data)
            ->assertUnauthorized()
            ->assertJsonPath('code', 'UNAUTHENTICATED');

        $this->assertDatabaseMissing('members', $data);
    }

    private function memberData(): array
    {
        return [
            'full_name' => 'Socio de prueba',
            'document_number' => '001'.Str::uuid(),
        ];
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