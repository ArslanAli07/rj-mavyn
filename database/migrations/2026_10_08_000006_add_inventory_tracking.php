<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Opt-in only: historical quantities never implicitly become authoritative stock.
        Schema::table('products', function (Blueprint $table) {
            $table->string('inventory_mode')->default('untracked');
            $table->unsignedInteger('reserved_quantity')->default(0);
            $table->unsignedBigInteger('inventory_version')->default(0);
        });
        Schema::table('product_variants', function (Blueprint $table) {
            $table->boolean('track_inventory')->default(false);
            $table->boolean('in_stock')->default(true);
            $table->unsignedInteger('stock_quantity')->default(0);
            $table->unsignedInteger('reserved_quantity')->default(0);
            $table->unsignedBigInteger('inventory_version')->default(0);
        });
        Schema::table('orders', function (Blueprint $table) {
            $table->boolean('inventory_managed')->default(false);
        });
        Schema::create('inventory_reservations', function (Blueprint $table) {
            $table->id();
            $table->string('reference')->unique();
            // Restrict raw cascades. Supported deletion detaches only terminal records.
            $table->foreignId('order_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('order_item_id')->nullable()->unique()->constrained()->restrictOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('product_variant_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('stock_type');
            $table->unsignedBigInteger('stock_id');
            $table->unsignedInteger('quantity');
            $table->string('state')->default('reserved');
            $table->json('snapshot');
            $table->timestamps();
            $table->index(['stock_type', 'stock_id', 'state']);
        });
        Schema::create('inventory_movements', function (Blueprint $table) {
            $table->id();
            $table->string('event_key', 64)->unique();
            $table->string('stock_type');
            $table->unsignedBigInteger('stock_id');
            $table->foreignId('inventory_reservation_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('admin_id')->nullable()->constrained()->nullOnDelete();
            $table->string('kind');
            $table->integer('stock_delta');
            $table->integer('reserved_delta');
            $table->unsignedInteger('stock_after');
            $table->unsignedInteger('reserved_after');
            $table->json('context');
            $table->timestamp('created_at')->useCurrent();
            $table->index(['stock_type', 'stock_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_movements');
        Schema::dropIfExists('inventory_reservations');
        Schema::table('orders', fn (Blueprint $table) => $table->dropColumn('inventory_managed'));
        Schema::table('product_variants', fn (Blueprint $table) => $table->dropColumn(['track_inventory', 'in_stock', 'stock_quantity', 'reserved_quantity', 'inventory_version']));
        Schema::table('products', fn (Blueprint $table) => $table->dropColumn(['inventory_mode', 'reserved_quantity', 'inventory_version']));
    }
};
