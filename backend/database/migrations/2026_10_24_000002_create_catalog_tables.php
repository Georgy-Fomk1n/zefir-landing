<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $seo = function (Blueprint $table) {
            $table->string('seo_title')->nullable();
            $table->string('seo_description', 320)->nullable();
            $table->string('seo_h1')->nullable();
            $table->text('seo_text')->nullable();
            $table->boolean('noindex')->default(false);
        };

        Schema::create('categories', function (Blueprint $table) use ($seo) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('name');
            $table->string('intro')->default('');
            $table->unsignedInteger('sort')->default(0);
            $table->json('cross_listed')->nullable();
            $table->json('items')->nullable();
            $table->boolean('is_active')->default(true);
            $seo($table);
            $table->timestamps();
        });

        Schema::create('products', function (Blueprint $table) use ($seo) {
            $table->id();
            $table->foreignId('category_id')->constrained()->restrictOnDelete();
            $table->string('slug')->unique();
            $table->string('name');
            $table->text('description');
            $table->boolean('is_pp')->default(false);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort')->default(0);
            $table->json('related')->nullable();
            $seo($table);
            $table->timestamps();
            $table->index(['category_id', 'is_active', 'sort']);
        });

        if (Schema::getConnection()->getDriverName() === 'mysql') {
            Schema::table('products', fn (Blueprint $table) => $table->fullText(['name', 'description']));
        }

        Schema::create('product_variants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('sku', 64)->unique();
            $table->string('name');
            $table->unsignedSmallInteger('pack_size')->default(1);
            $table->unsignedInteger('price'); // копейки
            $table->boolean('price_from')->default(false);
            $table->unsignedInteger('stock')->default(0);
            $table->unsignedInteger('reserved')->default(0);
            $table->unsignedInteger('sort')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('product_media', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('type', 8); // image | video
            $table->string('name');
            $table->string('alt');
            $table->unsignedInteger('sort')->default(0);
        });

        Schema::create('seo_pages', function (Blueprint $table) use ($seo) {
            $table->id();
            $table->string('path')->unique();
            $seo($table);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seo_pages');
        Schema::dropIfExists('product_media');
        Schema::dropIfExists('product_variants');
        Schema::dropIfExists('products');
        Schema::dropIfExists('categories');
    }
};
