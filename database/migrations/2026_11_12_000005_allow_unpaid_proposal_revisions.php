<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->timestamp('superseded_at')->nullable();
            $table->text('superseded_reason')->nullable();
            $table->dropUnique('orders_quote_id_unique');
        });
        DB::statement('CREATE UNIQUE INDEX one_current_order_per_quote ON orders (quote_id) WHERE superseded_at IS NULL');
    }

    public function down(): void
    {
        // Una reversión exige resolver manualmente cualquier historial de pedidos sustituidos.
        DB::statement('DROP INDEX one_current_order_per_quote');
        Schema::table('orders', function (Blueprint $table) {
            $table->unique('quote_id');
            $table->dropColumn(['superseded_at', 'superseded_reason']);
        });
    }
};
