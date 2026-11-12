<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code')->unique();
            $table->text('description')->nullable();
            $table->string('color')->nullable();
            $table->string('finish')->nullable();
            $table->text('characteristics')->nullable();
            $table->boolean('available')->default(false)->index();
            $table->boolean('demonstration')->default(false);
            $table->timestamps();
        });
        Schema::create('product_images', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->string('path');
            $table->string('alt');
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();
        });
        Schema::create('width_rates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->decimal('min_width_cm', 12, 2);
            $table->decimal('max_width_cm', 12, 2);
            $table->bigInteger('price_per_m2_clp');
            $table->boolean('confirmed')->default(false);
            $table->boolean('active')->default(false);
            $table->timestamps();
        });
        DB::statement('ALTER TABLE width_rates ADD CONSTRAINT valid_width_rate CHECK (min_width_cm >= 0 AND max_width_cm > min_width_cm AND price_per_m2_clp > 0)');
        // numrange(product_id, product_id, '[]') evita requerir extensiones del servidor.
        DB::statement("ALTER TABLE width_rates ADD CONSTRAINT width_rates_no_overlap EXCLUDE USING gist (numrange(product_id::numeric, product_id::numeric, '[]') WITH &&, numrange(min_width_cm, max_width_cm, '[)') WITH &&) WHERE (active)");
        Schema::create('additional_works', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('pricing_mode')->default('manual');
            $table->bigInteger('price_clp')->nullable();
            $table->boolean('confirmed')->default(false);
            $table->boolean('active')->default(false);
            $table->timestamps();
        });
        DB::statement("ALTER TABLE additional_works ADD CONSTRAINT valid_additional_price CHECK (pricing_mode IN ('manual','per_unit') AND (price_clp IS NULL OR price_clp >= 0) AND (NOT confirmed OR pricing_mode = 'manual' OR price_clp IS NOT NULL))");
        Schema::create('workshop_settings', function (Blueprint $table) {
            $table->id();
            $table->boolean('formula_confirmed')->default(false);
            $table->jsonb('dimension_limits')->nullable();
            $table->jsonb('contact')->nullable();
            $table->text('bank_details')->nullable();
            $table->text('payment_instructions')->nullable();
            $table->timestamps();
        });
        DB::statement('ALTER TABLE workshop_settings ADD CONSTRAINT settings_singleton CHECK (id = 1)');
    }

    public function down(): void
    {
        foreach (['workshop_settings', 'additional_works', 'width_rates', 'product_images', 'products'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
