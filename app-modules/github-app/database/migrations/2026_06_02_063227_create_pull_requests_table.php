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
        Schema::create('pull_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('repository_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('github_pr_number');
            $table->string('title', 500);
            $table->string('author_login');
            $table->string('base_sha', 40);
            $table->string('head_sha', 40);
            $table->string('state', 20);
            $table->string('linked_issue_ref')->nullable();
            $table->timestamps();

            $table->unique(['repository_id', 'github_pr_number']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pull_requests');
    }
};
