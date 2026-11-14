<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        $names = ['estrategia', 'familiares', 'cartas', 'rol', 'infantiles'];

        $categories = array_map(
            static fn (string $name): array => [
                'id' => (string) Str::uuid(),
                'name' => $name,
            ],
            $names,
        );

        DB::table('categories')->insertOrIgnore($categories);
    }
}