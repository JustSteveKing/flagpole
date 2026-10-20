<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('flags', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('project_id')->constrained()->cascadeOnDelete();
            $table->string('key', 64);
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('type', 16);
            // An ordered list of {name, value}: a new non-boolean flag starts on the first.
            $table->json('variants');
            $table->timestamp('archived_at')->nullable();
            $table->unsignedInteger('revision')->default(1);
            $table->timestamps();

            $table->unique(['project_id', 'key']);
        });

        Schema::create('flag_settings', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('flag_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('environment_id')->constrained()->cascadeOnDelete();
            $table->boolean('enabled')->default(false);
            $table->string('off_variant', 64);
            // A serve: {"variant": name} or {"split": [{"variant", "weight"}]}.
            $table->json('default');
            // Read and written whole, in order, as one document (RFC-0002).
            $table->json('rules');
            $table->unsignedInteger('revision')->default(1);
            $table->timestamps();

            $table->unique(['flag_id', 'environment_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('flag_settings');
        Schema::dropIfExists('flags');
    }
};
