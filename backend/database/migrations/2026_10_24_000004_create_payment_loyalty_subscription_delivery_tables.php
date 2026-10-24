<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_methods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('provider', 16);
            $table->text('token'); // шифруется cast encrypted
            $table->string('title'); // «Карта •• 1234»
            $table->timestamps();
        });

        Schema::create('subscription_plans', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description');
            $table->unsignedInteger('price');
            $table->unsignedSmallInteger('period_days')->default(30);
            $table->unsignedSmallInteger('daily_limit')->default(1);
            $table->unsignedInteger('total_limit')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subscription_plan_id')->constrained()->restrictOnDelete();
            $table->foreignId('payment_method_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status', 16)->index();
            $table->boolean('auto_renew')->default(true);
            $table->timestamp('current_period_start')->nullable();
            $table->timestamp('current_period_end')->nullable()->index();
            $table->unsignedTinyInteger('retry_count')->default(0);
            $table->timestamp('next_retry_at')->nullable();
            $table->timestamp('grace_until')->nullable();
            $table->timestamp('reminded_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->string('provider_subscription_id')->nullable();
            $table->timestamps();
        });

        Schema::create('subscription_redemptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subscription_id')->constrained()->cascadeOnDelete();
            $table->foreignId('seller_id')->nullable()->constrained('users')->nullOnDelete();
            $table->date('redeemed_on')->index();
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->morphs('payable'); // Order | Subscription
            $table->string('provider', 16);
            $table->string('provider_payment_id')->nullable()->unique();
            $table->string('idempotency_key', 64)->unique();
            $table->unsignedInteger('amount');
            $table->string('status', 16)->default('pending')->index();
            $table->string('purpose', 16)->default('initial'); // initial | renewal
            $table->text('payment_url')->nullable();
            $table->json('payload')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
        });

        Schema::create('webhook_events', function (Blueprint $table) {
            $table->id();
            $table->string('provider', 16);
            $table->string('event_id', 128);
            $table->json('payload');
            $table->timestamp('processed_at')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->unique(['provider', 'event_id']);
        });

        // Неизменяемый журнал баллов: только INSERT. Баланс = SUM(amount).
        Schema::create('loyalty_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('type', 16); // earn | spend | refund | expire | adjust
            $table->integer('amount'); // баллы (1 балл = 1 ₽), со знаком
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('comment')->nullable();
            $table->string('idempotency_key', 64)->nullable()->unique();
            $table->timestamp('expires_at')->nullable()->index();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['user_id', 'created_at']);
        });

        Schema::create('deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('courier_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 16)->index();
            $table->unsignedSmallInteger('route_position')->nullable();
            $table->string('proof_photo_path')->nullable();
            $table->string('fail_reason')->nullable();
            $table->unsignedInteger('cod_amount')->default(0);
            $table->string('cod_method', 16)->nullable(); // cash | card
            $table->timestamp('delivered_at')->nullable();
            $table->timestamps();
            $table->index(['courier_id', 'status']);
        });

        Schema::create('delivery_status_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('delivery_id')->constrained()->cascadeOnDelete();
            $table->string('from', 16)->nullable();
            $table->string('to', 16);
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('comment')->nullable();
            $table->decimal('lat', 9, 6)->nullable();
            $table->decimal('lng', 9, 6)->nullable();
            // Время действия на устройстве курьера (офлайн-очередь), отличается от времени синхронизации
            $table->timestamp('happened_at')->nullable();
            $table->string('client_action_id', 64)->nullable()->unique();
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('courier_shifts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('courier_id')->constrained('users')->cascadeOnDelete();
            $table->timestamp('started_at');
            $table->timestamp('ended_at')->nullable();
            $table->unsignedInteger('cash_collected')->default(0);
            $table->unsignedInteger('card_collected')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['courier_shifts', 'delivery_status_history', 'deliveries', 'loyalty_transactions', 'webhook_events', 'payments', 'subscription_redemptions', 'subscriptions', 'subscription_plans', 'payment_methods'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
