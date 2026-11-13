<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('members', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('full_name');
            $table->string('document_number')->unique();
        });

        Schema::create('loans', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('member_id')->constrained('members')->restrictOnDelete();
            $table->foreignUuid('loaned_by_user_id')->constrained('users')->restrictOnDelete();
            $table->timestamp('loaned_at');

            $table->index('member_id');
            $table->index('loaned_by_user_id');
            $table->index('loaned_at');
        });

        Schema::create('loan_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('loan_id')->constrained('loans')->restrictOnDelete();
            $table->foreignUuid('game_id')->constrained('games')->restrictOnDelete();
            $table->string('game_title_snapshot');
            $table->string('ean_snapshot');
            $table->string('category_name_snapshot');
            $table->timestamp('due_at');
            $table->timestamp('returned_at')->nullable();

            $table->unique(['loan_id', 'game_id']);
            $table->index('game_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('loan_items');
        Schema::dropIfExists('loans');
        Schema::dropIfExists('members');
    }
};