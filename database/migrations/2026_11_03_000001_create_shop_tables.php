<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $t) {
            $t->id();
            $t->string('slug')->unique();
            $t->string('name');
            $t->string('short')->nullable();
            $t->text('long')->nullable();
            $t->string('category', 20)->index();      // cake | zefir | pryanik | tort | gift
            $t->unsignedInteger('price')->nullable();  // null = цена по запросу
            $t->boolean('price_from')->default(false);
            $t->string('price_note')->nullable();
            $t->boolean('out_of_stock')->default(false);
            $t->boolean('on_order')->default(false);
            $t->boolean('is_new')->default(false);
            $t->boolean('is_pp')->default(false);
            $t->string('allergens')->nullable();
            $t->json('image')->nullable();             // {base, widths, w, h, avif}
            $t->string('upload')->nullable();          // файл, загруженный в админке
            $t->unsignedSmallInteger('sort')->default(0);
            $t->boolean('active')->default(true)->index();
            $t->timestamps();
        });

        Schema::create('product_variants', function (Blueprint $t) {
            $t->id();
            $t->foreignId('product_id')->constrained()->cascadeOnDelete();
            $t->string('label');
            $t->string('sub')->nullable();
            $t->unsignedInteger('price');
            $t->unsignedSmallInteger('sort')->default(0);
        });

        Schema::create('orders', function (Blueprint $t) {
            $t->id();
            $t->string('token', 40)->unique();
            $t->string('status', 20)->default('new')->index();
            $t->string('name');
            $t->string('phone', 30);
            $t->string('shipping', 10);               // pickup | courier
            $t->string('address');
            $t->date('slot_date');
            $t->unsignedTinyInteger('slot_hour');
            $t->string('comment')->nullable();
            $t->string('card_text')->nullable();
            $t->unsignedInteger('subtotal');
            $t->unsignedInteger('fee')->default(0);
            $t->unsignedInteger('total');
            $t->timestamps();
        });

        Schema::create('order_items', function (Blueprint $t) {
            $t->id();
            $t->foreignId('order_id')->constrained()->cascadeOnDelete();
            $t->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $t->string('name');
            $t->json('image')->nullable();
            $t->unsignedInteger('price');
            $t->unsignedSmallInteger('qty');
            $t->unsignedInteger('sum');
        });

        Schema::create('push_subscriptions', function (Blueprint $t) {
            $t->id();
            $t->string('endpoint_hash', 64)->unique();
            $t->text('endpoint');
            $t->string('p256dh');
            $t->string('auth');
            $t->string('encoding', 20)->default('aesgcm');
            $t->string('kind', 10)->index();           // owner | customer
            $t->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
            $t->foreignId('order_id')->nullable()->constrained()->cascadeOnDelete();
            $t->string('user_agent')->nullable();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('push_subscriptions');
        Schema::dropIfExists('order_items');
        Schema::dropIfExists('orders');
        Schema::dropIfExists('product_variants');
        Schema::dropIfExists('products');
    }
};
