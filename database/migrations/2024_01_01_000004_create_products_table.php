<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->string('slug')->unique();
            $table->string('sku')->unique()->nullable();
            $table->text('short_description')->nullable();
            $table->longText('description')->nullable();
            $table->text('ingredients')->nullable();
            $table->text('allergens')->nullable();
            $table->decimal('price', 10, 2);
            $table->decimal('discount_price', 10, 2)->nullable();
            $table->string('weight')->nullable();     // e.g. "1 kg", "500g"
            $table->string('servings')->nullable();   // e.g. "8-10 persons"
            $table->integer('stock_quantity')->default(0);
            $table->boolean('in_stock')->default(true);
            $table->boolean('is_featured')->default(false);
            $table->boolean('is_bestseller')->default(false);
            $table->boolean('is_new_arrival')->default(false);
            $table->boolean('is_active')->default(true);
            $table->string('main_image')->nullable();
            $table->unsignedBigInteger('sales_count')->default(0);
            $table->timestamps();

            $table->index('slug');
            $table->index('category_id');
            $table->index(['is_active', 'is_featured']);
            $table->index(['is_active', 'is_bestseller']);
            $table->index(['is_active', 'is_new_arrival']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
