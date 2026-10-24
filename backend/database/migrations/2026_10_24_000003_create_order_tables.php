<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('promo_codes', function (Blueprint $table) {
            $table->id();
            $table->string('code', 64)->unique();
            $table->string('type', 8); // percent | fixed
            $table->unsignedInteger('value'); // проценты или копейки
            $table->unsignedInteger('min_total')->default(0);
            $table->unsignedInteger('max_uses')->nullable();
            $table->unsignedInteger('per_user_limit')->nullable();
            $table->unsignedInteger('used_count')->default(0);
            $table->json('category_ids')->nullable();
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('time_slots', function (Blueprint $table) {
            $table->id();
            $table->string('type', 16); // pickup | courier
            $table->date('date');
            $table->time('starts_at');
            $table->time('ends_at');
            $table->unsignedSmallInteger('capacity');
            $table->unsignedSmallInteger('booked')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['type', 'date', 'starts_at']);
        });

        Schema::create('delivery_zones', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->json('polygon'); // [[lat, lng], ...]
            $table->unsignedInteger('price');
            $table->unsignedInteger('min_order')->default(0);
            $table->unsignedInteger('free_from')->nullable();
            $table->unsignedSmallInteger('eta_minutes')->default(120);
            $table->unsignedSmallInteger('max_transit_minutes')->nullable(); // ограничение для скоропортящегося
            $table->unsignedInteger('sort')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('number', 32)->unique();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status', 24)->index();
            $table->string('fulfilment', 16);
            $table->string('contact_name');
            $table->string('contact_phone', 32);
            $table->string('contact_email')->nullable();
            $table->json('address')->nullable();
            $table->foreignId('slot_id')->nullable()->constrained('time_slots')->nullOnDelete();
            $table->foreignId('zone_id')->nullable()->constrained('delivery_zones')->nullOnDelete();
            $table->foreignId('promo_code_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('subtotal');
            $table->unsignedInteger('discount')->default(0);
            $table->unsignedInteger('points_spent')->default(0);
            $table->unsignedInteger('delivery_cost')->default(0);
            $table->unsignedInteger('total');
            $table->string('payment_status', 16)->default('pending');
            $table->text('comment')->nullable();
            $table->timestamp('reserved_until')->nullable()->index();
            $table->string('tracking_token', 64)->unique();
            $table->string('external_tracking')->nullable(); // номер СДЭК / Почты
            $table->timestamp('loyalty_awarded_at')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'created_at']);
        });

        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_variant_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedBigInteger('category_id')->nullable();
            $table->string('product_name');
            $table->string('variant_name');
            $table->string('sku', 64);
            $table->unsignedInteger('price');
            $table->unsignedInteger('qty');
        });

        Schema::create('order_status_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->string('from', 24)->nullable();
            $table->string('to', 24);
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('comment')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('promo_redemptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('promo_code_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('phone', 32)->nullable()->index();
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('cart_items', function (Blueprint $table) {
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_variant_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('qty');
            $table->primary(['user_id', 'product_variant_id']);
        });

        Schema::create('favorites', function (Blueprint $table) {
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->timestamp('created_at')->useCurrent();
            $table->primary(['user_id', 'product_id']);
        });

        Schema::create('reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->string('author_name');
            $table->unsignedTinyInteger('rating');
            $table->text('text');
            $table->string('status', 16)->default('pending')->index(); // pending | approved | rejected
            $table->timestamps();
            $table->unique(['order_id', 'product_id']);
        });
    }

    public function down(): void
    {
        foreach (['reviews', 'favorites', 'cart_items', 'promo_redemptions', 'order_status_history', 'order_items', 'orders', 'delivery_zones', 'time_slots', 'promo_codes'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
