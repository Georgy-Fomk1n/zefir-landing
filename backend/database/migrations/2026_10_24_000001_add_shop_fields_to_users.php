<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('phone', 32)->nullable()->index()->after('email');
            $table->boolean('marketing_consent')->default(false);
            $table->timestamp('pd_consent_at')->nullable();
            $table->text('two_factor_secret')->nullable();
            $table->timestamp('two_factor_confirmed_at')->nullable();
            $table->string('telegram_chat_id')->nullable();
            $table->boolean('is_active')->default(true);
            // Удаление аккаунта: ПДн обезличиваются, запись остаётся ради заказов и бухгалтерии
            $table->timestamp('anonymized_at')->nullable();
        });

        Schema::create('user_roles', function (Blueprint $table) {
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('role', 16);
            $table->primary(['user_id', 'role']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_roles');
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['phone', 'marketing_consent', 'pd_consent_at', 'two_factor_secret', 'two_factor_confirmed_at', 'telegram_chat_id', 'is_active', 'anonymized_at']);
        });
    }
};
