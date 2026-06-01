<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedBigInteger('github_id')->nullable()->unique()->after('id');
            $table->string('github_login')->nullable()->after('github_id');
            $table->string('avatar_url', 500)->nullable()->after('github_login');
            $table->text('github_token')->nullable()->after('avatar_url');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->string('password')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['github_id']);
            $table->dropColumn(['github_id', 'github_login', 'avatar_url', 'github_token']);
        });

        // Backfill any password-less (GitHub-only) accounts with an unusable hash
        // before restoring the NOT NULL constraint, so the rollback does not fail
        // on database engines that reject existing NULLs when altering the column.
        DB::table('users')->whereNull('password')->update([
            'password' => bcrypt(Str::random(40)),
        ]);

        Schema::table('users', function (Blueprint $table) {
            $table->string('password')->nullable(false)->change();
        });
    }
};
