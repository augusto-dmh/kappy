<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * GitHub users may have no public email; provisioning stores a null email
     * for them. The unique index is preserved — SQL unique constraints ignore
     * NULLs, so multiple email-less GitHub users coexist.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('email')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Backfill any null emails with a unique placeholder before restoring
        // the NOT NULL constraint, so the column change does not fail.
        foreach (DB::table('users')->whereNull('email')->pluck('id') as $id) {
            DB::table('users')->where('id', $id)->update([
                'email' => "github-user-{$id}@users.noreply.github.com",
            ]);
        }

        Schema::table('users', function (Blueprint $table) {
            $table->string('email')->nullable(false)->change();
        });
    }
};
