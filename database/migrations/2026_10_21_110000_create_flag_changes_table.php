<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // A flag's history (RFC-0007): never edited, and deleted only with its flag's project, or,
        // for a settings change, with its environment.
        Schema::create('flag_changes', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('flag_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('environment_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('action', 32);
            $table->foreignUlid('user_id')->constrained()->cascadeOnDelete();
            // No foreign key: the record outlives the token, and keeps its name to show for it.
            $table->unsignedBigInteger('token_id')->nullable();
            $table->string('token_name')->nullable();
            $table->json('before')->nullable();
            $table->json('after');
            $table->timestamp('created_at');

            $table->index(['flag_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('flag_changes');
    }
};
