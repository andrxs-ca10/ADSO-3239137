<?php

namespace Tests\Feature;

use Database\Seeders\CategorySeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CategorySeederTest extends TestCase
{
    use DatabaseTransactions;

    public function test_seeding_twice_keeps_the_same_five_categories(): void
    {
        $names = ['cartas', 'estrategia', 'familiares', 'infantiles', 'rol'];

        $categories = DB::table('categories')->whereIn('name', $names);

        $this->seed(CategorySeeder::class);
        $firstRun = (clone $categories)->orderBy('name')->pluck('id', 'name')->all();

        $this->seed(CategorySeeder::class);
        $secondRun = (clone $categories)->orderBy('name')->pluck('id', 'name')->all();

        self::assertSame($names, array_keys($firstRun));
        self::assertSame($firstRun, $secondRun);
    }
}