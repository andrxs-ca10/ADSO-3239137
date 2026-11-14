<?php

namespace Tests\Feature;

use App\Http\Controllers\MemberController;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class StoreMemberTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        Route::post('/api/testing/members', [MemberController::class, 'store']);
    }

    public function test_it_creates_a_member_and_preserves_leading_zeroes(): void
    {
        $data = [
            'full_name' => 'Socio de prueba',
            'document_number' => '0012345678',
        ];

        $response = $this->postJson('/api/testing/members', $data)
            ->assertCreated()
            ->assertJsonPath('full_name', $data['full_name'])
            ->assertJsonPath('document_number', $data['document_number']);

        $this->assertDatabaseHas('members', ['id' => $response->json('id')] + $data);
    }

    public function test_it_rejects_a_duplicate_document(): void
    {
        $data = [
            'full_name' => 'Primer socio',
            'document_number' => '0012345678',
        ];

        $this->postJson('/api/testing/members', $data)->assertCreated();

        $data['full_name'] = 'Segundo socio';

        $this->postJson('/api/testing/members', $data)
            ->assertConflict()
            ->assertJsonPath('code', 'DOCUMENT_TAKEN');
    }

    public function test_it_requires_name_and_document(): void
    {
        $this->postJson('/api/testing/members', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['full_name', 'document_number']);
    }
}