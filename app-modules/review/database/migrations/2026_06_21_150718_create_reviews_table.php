<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('reviews', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignId('pull_request_id')->constrained()->cascadeOnDelete();
            $table->string('head_sha', 40);
            $table->string('trigger', 20);
            $table->string('status', 20)->default('queued');
            $table->boolean('is_incremental')->default(false);
            $table->string('generator_model', 100)->nullable();
            $table->string('critic_model', 100)->nullable();
            $table->unsignedInteger('input_tokens')->nullable();
            $table->unsignedInteger('output_tokens')->nullable();
            $table->unsignedInteger('cached_tokens')->nullable();
            $table->unsignedInteger('cost_cents')->nullable();
            $table->unsignedBigInteger('github_check_run_id')->nullable();
            $table->unsignedBigInteger('summary_comment_id')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->string('failure_reason', 500)->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reviews');
    }
};
