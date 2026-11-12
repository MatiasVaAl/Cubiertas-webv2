<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('phone', 40);
            $table->string('email')->nullable();
            $table->timestamps();
        });
        Schema::create('quotes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->string('status')->default('pending_review')->index();
            $table->string('delivery_method');
            $table->jsonb('delivery_details')->nullable();
            $table->bigInteger('transport_clp')->nullable();
            $table->text('transport_conditions')->nullable();
            $table->text('notes')->nullable();
            $table->unsignedInteger('current_version')->default(0);
            $table->timestamp('accepted_at')->nullable();
            $table->timestamps();
            $table->index('created_at');
        });
        DB::statement("ALTER TABLE quotes ADD CONSTRAINT valid_quote CHECK (status IN ('pending_review','in_review','awaiting_information','awaiting_acceptance','accepted','rejected') AND delivery_method IN ('pickup','transport') AND (transport_clp IS NULL OR transport_clp >= 0))");
        Schema::create('quote_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quote_id')->constrained()->restrictOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->restrictOnDelete();
            $table->jsonb('snapshot');
            $table->integer('quantity');
            $table->bigInteger('unit_price_clp')->nullable();
            $table->bigInteger('known_subtotal_clp')->default(0);
            $table->jsonb('pending_reasons');
            $table->text('resolution')->nullable();
            $table->timestamps();
        });
        DB::statement('ALTER TABLE quote_lines ADD CONSTRAINT valid_quote_line CHECK (quantity > 0 AND (unit_price_clp IS NULL OR unit_price_clp >= 0) AND known_subtotal_clp >= 0)');
        Schema::create('quote_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quote_id')->constrained()->restrictOnDelete();
            $table->integer('version');
            $table->jsonb('snapshot');
            $table->bigInteger('total_clp');
            $table->foreignId('issued_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->text('justification');
            $table->timestamps();
            $table->unique(['quote_id', 'version']);
            $table->unique(['id', 'quote_id']);
        });
        DB::statement('ALTER TABLE quote_versions ADD CONSTRAINT valid_version CHECK (version > 0 AND total_clp > 0)');
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quote_id')->unique()->constrained()->restrictOnDelete();
            $table->foreignId('quote_version_id')->unique();
            $table->foreign(['quote_version_id', 'quote_id'])->references(['id', 'quote_id'])->on('quote_versions')->restrictOnDelete();
            $table->bigInteger('total_clp');
            $table->string('payment_status')->default('pending')->index();
            $table->string('manufacturing_status')->default('pending')->index();
            $table->string('delivery_status')->index();
            $table->boolean('on_hold')->default(false);
            $table->text('hold_reason')->nullable();
            $table->jsonb('dispatch_details')->nullable();
            $table->timestamps();
        });
        DB::statement("ALTER TABLE orders ADD CONSTRAINT valid_order CHECK (total_clp > 0 AND payment_status IN ('pending','under_review','paid','observed') AND manufacturing_status IN ('pending','in_progress','completed') AND delivery_status IN ('pending_pickup','ready_pickup','delivered','pending_dispatch','handed_to_carrier','delivery_confirmed') AND (manufacturing_status = 'pending' OR payment_status = 'paid') AND (delivery_status IN ('pending_pickup','pending_dispatch') OR manufacturing_status = 'completed'))");
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->restrictOnDelete();
            $table->bigInteger('expected_clp');
            $table->string('path');
            $table->char('sha256', 64)->unique();
            $table->string('mime');
            $table->string('status')->default('under_review')->index();
            $table->foreignId('verified_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('verified_at')->nullable();
            $table->text('observations')->nullable();
            $table->timestamps();
        });
        DB::statement("ALTER TABLE payments ADD CONSTRAINT valid_payment CHECK (expected_clp > 0 AND status IN ('under_review','paid','observed') AND (status <> 'paid' OR (verified_by IS NOT NULL AND verified_at IS NOT NULL)))");
        DB::statement("CREATE UNIQUE INDEX one_verified_payment_per_order ON payments (order_id) WHERE status = 'paid'");
        Schema::create('tracking_credentials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quote_id')->constrained()->restrictOnDelete();
            $table->char('token_hash', 64)->unique();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();
        });
        Schema::create('state_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quote_id')->constrained()->restrictOnDelete();
            $table->foreignId('order_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('type');
            $table->text('message');
            $table->boolean('customer_visible')->default(true);
            $table->timestamps();
        });
        DB::unprepared(<<<'SQL'
            CREATE FUNCTION reject_commercial_mutation() RETURNS trigger LANGUAGE plpgsql AS $$
            BEGIN RAISE EXCEPTION 'El historial comercial emitido es inmutable'; END;
            $$;
            CREATE TRIGGER immutable_quote_version BEFORE UPDATE OR DELETE ON quote_versions FOR EACH ROW EXECUTE FUNCTION reject_commercial_mutation();
            CREATE TRIGGER immutable_state_event BEFORE UPDATE OR DELETE ON state_events FOR EACH ROW EXECUTE FUNCTION reject_commercial_mutation();
            CREATE FUNCTION protect_order_amount() RETURNS trigger LANGUAGE plpgsql AS $$
            BEGIN
                IF NEW.total_clp IS DISTINCT FROM OLD.total_clp OR NEW.quote_version_id IS DISTINCT FROM OLD.quote_version_id OR NEW.quote_id IS DISTINCT FROM OLD.quote_id THEN
                    RAISE EXCEPTION 'El importe y la versión aceptados son inmutables';
                END IF;
                RETURN NEW;
            END;
            $$;
            CREATE TRIGGER immutable_order_amount BEFORE UPDATE ON orders FOR EACH ROW EXECUTE FUNCTION protect_order_amount();
            SQL);
    }

    public function down(): void
    {
        foreach (['state_events', 'tracking_credentials', 'payments', 'orders', 'quote_versions', 'quote_lines', 'quotes', 'customers'] as $table) {
            Schema::dropIfExists($table);
        }
        DB::statement('DROP FUNCTION IF EXISTS reject_commercial_mutation()');
        DB::statement('DROP FUNCTION IF EXISTS protect_order_amount()');
    }
};
