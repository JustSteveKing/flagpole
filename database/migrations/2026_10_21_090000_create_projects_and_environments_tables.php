<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('projects', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('user_id')->constrained()->cascadeOnDelete();
            $table->string('key', 64);
            $table->string('name');
            // Incremented by every write; the ETag is built from it (RFC-0003).
            $table->unsignedInteger('revision')->default(1);
            $table->timestamps();

            $table->unique(['user_id', 'key']);
        });

        Schema::create('environments', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('project_id')->constrained()->cascadeOnDelete();
            $table->string('key', 64);
            $table->string('name');
            $table->unsignedInteger('revision')->default(1);
            // Counts changes to what this environment's flags evaluate to; the bulk evaluation
            // ETag is built from it (ADR-0003). Separate from revision, which is the row's own.
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();

            $table->unique(['project_id', 'key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('environments');
        Schema::dropIfExists('projects');
    }
};
