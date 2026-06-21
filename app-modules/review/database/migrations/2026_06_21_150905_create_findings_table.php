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
        Schema::create('findings', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('review_id')->constrained()->cascadeOnDelete();
            $table->string('category', 20);
            $table->string('severity', 20);
            $table->string('path', 500);
            $table->unsignedInteger('line')->nullable();
            $table->string('title', 255);
            $table->text('message');
            $table->text('suggestion')->nullable();
            $table->text('agent_prompt')->nullable();
            $table->unsignedTinyInteger('confidence')->default(0);
            $table->string('critic_verdict', 20)->default('pending');
            $table->string('critic_reason', 500)->nullable();
            $table->string('status', 20)->default('draft');
            $table->unsignedBigInteger('github_comment_id')->nullable();
            $table->string('fingerprint', 64);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('findings');
    }
};
