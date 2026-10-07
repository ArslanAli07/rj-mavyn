<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_variants', function (Blueprint $table) {
            $table->string('name')->nullable()->after('product_id');
            $table->unsignedInteger('sort_order')->default(0)->after('servings');
            $table->boolean('is_default')->default(false)->after('sort_order');
            $table->boolean('is_active')->default(true)->after('is_default');
        });

        // Backfill name from weight and set first variant as default per product.
        $variants = DB::table('product_variants')->orderBy('id')->get();
        $seenProducts = [];
        foreach ($variants as $index => $variant) {
            $isFirst = !isset($seenProducts[$variant->product_id]);
            if ($isFirst) {
                $seenProducts[$variant->product_id] = true;
            }

            DB::table('product_variants')->where('id', $variant->id)->update([
                'name' => $variant->weight,
                'sort_order' => $isFirst ? 0 : ($index % 100),
                'is_default' => $isFirst,
                'is_active' => true,
            ]);
        }

        // Normalize sort_order per product.
        $productIds = DB::table('product_variants')->distinct()->pluck('product_id');
        foreach ($productIds as $productId) {
            $ordered = DB::table('product_variants')
                ->where('product_id', $productId)
                ->orderBy('id')
                ->get();
            foreach ($ordered as $i => $variant) {
                DB::table('product_variants')->where('id', $variant->id)->update([
                    'sort_order' => $i,
                    'is_default' => $i === 0,
                ]);
            }
        }

        Schema::create('product_option_groups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('selection_type')->default('multiple'); // single|multiple
            $table->unsignedTinyInteger('min_selections')->default(0);
            $table->unsignedTinyInteger('max_selections')->nullable();
            $table->boolean('is_required')->default(false);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['product_id', 'sort_order']);
        });

        Schema::create('product_option_values', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_option_group_id')->constrained('product_option_groups')->cascadeOnDelete();
            $table->string('name');
            $table->decimal('price_adjustment', 10, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['product_option_group_id', 'sort_order']);
        });

        Schema::table('cart_items', function (Blueprint $table) {
            $table->string('configuration_signature', 64)->nullable()->after('weight');
            $table->json('options_snapshot')->nullable()->after('configuration_signature');
            $table->string('special_request', 255)->nullable()->after('options_snapshot');
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->string('variant_name')->nullable()->after('weight');
            $table->json('options_snapshot')->nullable()->after('variant_name');
            $table->string('special_request', 255)->nullable()->after('options_snapshot');
        });

        // Backfill cart signatures for existing rows (variant only, no options).
        $cartItems = DB::table('cart_items')->get();
        foreach ($cartItems as $item) {
            $signature = hash('sha256', json_encode([
                'product_id' => (int) $item->product_id,
                'variant_id' => $item->product_variant_id ? (int) $item->product_variant_id : null,
                'option_ids' => [],
                'special_request' => '',
            ]));

            DB::table('cart_items')->where('id', $item->id)->update([
                'configuration_signature' => $signature,
                'options_snapshot' => json_encode([]),
            ]);
        }

        // Backfill order item variant names from weight.
        DB::table('order_items')->whereNotNull('weight')->update([
            'variant_name' => DB::raw('weight'),
            'options_snapshot' => json_encode([]),
        ]);
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropColumn(['variant_name', 'options_snapshot', 'special_request']);
        });

        Schema::table('cart_items', function (Blueprint $table) {
            $table->dropColumn(['configuration_signature', 'options_snapshot', 'special_request']);
        });

        Schema::dropIfExists('product_option_values');
        Schema::dropIfExists('product_option_groups');

        Schema::table('product_variants', function (Blueprint $table) {
            $table->dropColumn(['name', 'sort_order', 'is_default', 'is_active']);
        });
    }
};
