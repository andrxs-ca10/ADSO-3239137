<?php

namespace Tests\Feature;

use App\Models\User;
use Firebase\JWT\JWT;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class GameCoverTest extends TestCase
{
    use DatabaseTransactions;

    private string $gameId;
    private string $url;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');

        $secret = bin2hex(random_bytes(32));
        config(['auth.jwt_secret' => $secret]);

        $admin = User::query()->create([
            'username' => 'portadas-'.Str::uuid(),
            'password_hash' => Hash::make('ClaveDePrueba123'),
            'role' => 'admin',
        ]);

        $now = time();

        $this->withToken(JWT::encode([
            'sub' => (string) $admin->getKey(),
            'role' => 'admin',
            'iat' => $now,
            'exp' => $now + 300,
        ], $secret, 'HS256'));

        $categoryId = (string) Str::uuid();
        $this->gameId = (string) Str::uuid();
        $this->url = '/api/v1/games/'.$this->gameId.'/cover';

        DB::table('categories')->insert([
            'id' => $categoryId,
            'name' => 'Prueba '.$categoryId,
        ]);

        DB::table('games')->insert([
            'id' => $this->gameId,
            'title' => 'Juego de prueba',
            'designer' => 'Diseñador de prueba',
            'ean' => '4006381333931',
            'category_id' => $categoryId,
            'total_units' => 1,
            'available_units' => 1,
        ]);
    }

    public function test_admin_can_upload_download_and_delete_a_cover(): void
    {
        $file = $this->png();
        $contents = $file->get();

        $coverKey = $this->put($this->url, ['file' => $file], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('id', $this->gameId)
            ->json('cover_key');

        $this->assertDatabaseHas('games', [
            'id' => $this->gameId,
            'cover_key' => $coverKey,
        ]);

        Storage::disk('local')->assertExists($coverKey);

        $download = $this->get($this->url)
            ->assertOk()
            ->assertHeader('Content-Type', 'image/png');

        self::assertSame($contents, $download->streamedContent());

        $this->deleteJson($this->url)->assertNoContent();

        Storage::disk('local')->assertMissing($coverKey);
        $this->assertDatabaseHas('games', [
            'id' => $this->gameId,
            'cover_key' => null,
        ]);

        $this->getJson($this->url)
            ->assertNotFound()
            ->assertJsonPath('code', 'NOT_FOUND');
    }

       public function test_it_rejects_a_text_file_named_like_a_png(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'cover-');
        self::assertNotFalse($path);

        try {
            file_put_contents($path, 'Esto es texto.');

            $file = new UploadedFile(
                $path,
                'portada.png',
                'image/png',
                UPLOAD_ERR_OK,
                true,
            );

            $this->put($this->url, ['file' => $file], ['Accept' => 'application/json'])
                ->assertUnprocessable()
                ->assertJsonValidationErrors('file');

            Storage::disk('local')->assertDirectoryEmpty('game-covers');
        } finally {
            unlink($path);
        }
    }

    public function test_it_rejects_a_cover_larger_than_two_megabytes(): void
    {
        $file = $this->png()->size(2049);

        $this->put($this->url, ['file' => $file], ['Accept' => 'application/json'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('file');

        Storage::disk('local')->assertDirectoryEmpty('game-covers');
    }

    private function png(): UploadedFile
    {
        return UploadedFile::fake()->createWithContent(
            'portada.png',
            base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jR1sAAAAASUVORK5CYII=', true),
        );
    }
}