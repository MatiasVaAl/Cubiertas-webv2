<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('operation_audits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $table->string('action');
            $table->string('entity_type');
            $table->bigInteger('entity_id');
            $table->jsonb('changed_fields');
            $table->timestamps();
            $table->index(['entity_type', 'entity_id']);
        });
        DB::statement('CREATE TRIGGER immutable_operation_audit BEFORE UPDATE OR DELETE ON operation_audits FOR EACH ROW EXECUTE FUNCTION reject_commercial_mutation()');
    }

    public function down(): void
    {
        Schema::dropIfExists('operation_audits');
    }
};
