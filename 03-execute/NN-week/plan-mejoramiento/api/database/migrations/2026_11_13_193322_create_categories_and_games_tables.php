<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name')->unique();
        });

        Schema::create('games', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('title');
            $table->string('designer');
            $table->string('ean');
            $table->foreignUuid('category_id')->constrained('categories')->restrictOnDelete();
            $table->integer('total_units');
            $table->integer('available_units');
            $table->string('cover_key')->nullable();
            $table->softDeletes();

            $table->index('category_id');
        });

        DB::statement(
            'CREATE UNIQUE INDEX games_active_ean_unique ON games (ean) WHERE deleted_at IS NULL'
        );

        DB::statement(
            'ALTER TABLE games ADD CONSTRAINT games_valid_units CHECK (total_units >= 1 AND available_units BETWEEN 0 AND total_units)'
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('games');
        Schema::dropIfExists('categories');
    }
};