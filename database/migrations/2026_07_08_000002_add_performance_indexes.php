<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add missing performance indexes across 7 tables.
     * Each index is guarded with an existence check so re-runs are safe.
     */
    public function up(): void
    {
        Schema::table('cart_items', function (Blueprint $table) {
            if (!$this->indexExists('cart_items', 'cart_items_session_id_index')) {
                $table->index('session_id');
            }
            if (!$this->indexExists('cart_items', 'cart_items_user_id_index')) {
                $table->index('user_id');
            }
            if (!$this->indexExists('cart_items', 'cart_items_product_id_index')) {
                $table->index('product_id');
            }
        });

        Schema::table('wishlists', function (Blueprint $table) {
            if (!$this->indexExists('wishlists', 'wishlists_user_id_product_id_index')) {
                $table->index(['user_id', 'product_id']);
            }
        });

        Schema::table('addresses', function (Blueprint $table) {
            if (!$this->indexExists('addresses', 'addresses_user_id_is_default_index')) {
                $table->index(['user_id', 'is_default']);
            }
        });

        Schema::table('coupon_usages', function (Blueprint $table) {
            if (!$this->indexExists('coupon_usages', 'coupon_usages_coupon_id_user_id_index')) {
                $table->index(['coupon_id', 'user_id']);
            }
        });

        Schema::table('orders', function (Blueprint $table) {
            if (!$this->indexExists('orders', 'orders_user_id_created_at_index')) {
                $table->index(['user_id', 'created_at']);
            }
        });

        Schema::table('products', function (Blueprint $table) {
            if (!$this->indexExists('products', 'products_is_active_created_at_index')) {
                $table->index(['is_active', 'created_at']);
            }
        });
    }

    public function down(): void
    {
        Schema::table('cart_items', function (Blueprint $table) {
            if ($this->indexExists('cart_items', 'cart_items_session_id_index'))  $table->dropIndex('cart_items_session_id_index');
            if ($this->indexExists('cart_items', 'cart_items_user_id_index'))     $table->dropIndex('cart_items_user_id_index');
            if ($this->indexExists('cart_items', 'cart_items_product_id_index'))  $table->dropIndex('cart_items_product_id_index');
        });

        Schema::table('wishlists', function (Blueprint $table) {
            if ($this->indexExists('wishlists', 'wishlists_user_id_product_id_index')) $table->dropIndex('wishlists_user_id_product_id_index');
        });

        Schema::table('addresses', function (Blueprint $table) {
            if ($this->indexExists('addresses', 'addresses_user_id_is_default_index')) $table->dropIndex('addresses_user_id_is_default_index');
        });

        Schema::table('coupon_usages', function (Blueprint $table) {
            if ($this->indexExists('coupon_usages', 'coupon_usages_coupon_id_user_id_index')) $table->dropIndex('coupon_usages_coupon_id_user_id_index');
        });

        Schema::table('orders', function (Blueprint $table) {
            if ($this->indexExists('orders', 'orders_user_id_created_at_index')) $table->dropIndex('orders_user_id_created_at_index');
        });

        Schema::table('products', function (Blueprint $table) {
            if ($this->indexExists('products', 'products_is_active_created_at_index')) $table->dropIndex('products_is_active_created_at_index');
        });
    }

    /**
     * Check if an index exists on a table using a raw SHOW INDEX query.
     * Works on MySQL/MariaDB and SQLite (returns false on SQLite gracefully).
     */
    private function indexExists(string $table, string $indexName): bool
    {
        try {
            $driver = DB::getDriverName();

            if ($driver === 'mysql') {
                $result = DB::select("SHOW INDEX FROM `{$table}` WHERE Key_name = ?", [$indexName]);
                return !empty($result);
            }

            if ($driver === 'sqlite') {
                $result = DB::select("PRAGMA index_list({$table})");
                foreach ($result as $row) {
                    if ($row->name === $indexName) return true;
                }
                return false;
            }

            return false;
        } catch (\Exception) {
            return false;
        }
    }
};
