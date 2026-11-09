<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('push_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            // customer — основной домен; staff — штат (продавец, курьер, админ). Один телефон может иметь обе.
            $table->string('scope', 16);
            $table->string('endpoint', 1000);
            $table->string('endpoint_hash', 64)->unique();
            $table->string('public_key', 120);
            $table->string('auth_token', 40);
            $table->string('user_agent', 300)->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'scope']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('push_subscriptions');
    }
};
