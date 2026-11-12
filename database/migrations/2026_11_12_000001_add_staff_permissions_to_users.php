<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role')->default('worker');
            $table->boolean('active')->default(true);
            $table->boolean('can_verify_payments')->default(false);
        });
        DB::statement("ALTER TABLE users ADD CONSTRAINT users_valid_role CHECK (role IN ('admin', 'worker'))");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE users DROP CONSTRAINT users_valid_role');
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn(['role', 'active', 'can_verify_payments']));
    }
};
