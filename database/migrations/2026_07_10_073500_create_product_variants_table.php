<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_variants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('weight');
            $table->decimal('price', 10, 2);
            $table->decimal('discount_price', 10, 2)->nullable();
            $table->string('servings')->nullable();
            $table->timestamps();

            $table->index('product_id');
        });

        // Add product_variant_id and weight to cart_items
        Schema::table('cart_items', function (Blueprint $table) {
            $table->foreignId('product_variant_id')->nullable()->after('product_id')->constrained('product_variants')->nullOnDelete();
            $table->string('weight')->nullable()->after('product_variant_id');
        });

        // Add product_variant_id and weight to order_items
        Schema::table('order_items', function (Blueprint $table) {
            $table->foreignId('product_variant_id')->nullable()->after('product_id')->constrained('product_variants')->nullOnDelete();
            $table->string('weight')->nullable()->after('product_variant_id');
        });

        // Seed default 4 variants for existing products
        $products = DB::table('products')->get();
        foreach ($products as $product) {
            $basePrice = (float) $product->price;
            $baseDiscountPrice = $product->discount_price ? (float) $product->discount_price : null;

            // Seed 4 variants: 1 Pound, 2 Pounds, 3 Pounds, 4 Pounds
            DB::table('product_variants')->insert([
                [
                    'product_id' => $product->id,
                    'weight' => '1 Pound',
                    'price' => $basePrice,
                    'discount_price' => $baseDiscountPrice,
                    'servings' => '4-6 Servings',
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
                [
                    'product_id' => $product->id,
                    'weight' => '2 Pounds',
                    'price' => $basePrice * 2.0,
                    'discount_price' => $baseDiscountPrice ? $baseDiscountPrice * 2.0 : null,
                    'servings' => '8-10 Servings',
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
                [
                    'product_id' => $product->id,
                    'weight' => '3 Pounds',
                    'price' => $basePrice * 3.0,
                    'discount_price' => $baseDiscountPrice ? $baseDiscountPrice * 3.0 : null,
                    'servings' => '12-14 Servings',
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
                [
                    'product_id' => $product->id,
                    'weight' => '4 Pounds',
                    'price' => $basePrice * 4.0,
                    'discount_price' => $baseDiscountPrice ? $baseDiscountPrice * 4.0 : null,
                    'servings' => '16-20 Servings',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropForeign(['product_variant_id']);
            $table->dropColumn(['product_variant_id', 'weight']);
        });

        Schema::table('cart_items', function (Blueprint $table) {
            $table->dropForeign(['product_variant_id']);
            $table->dropColumn(['product_variant_id', 'weight']);
        });

        Schema::dropIfExists('product_variants');
    }
};
