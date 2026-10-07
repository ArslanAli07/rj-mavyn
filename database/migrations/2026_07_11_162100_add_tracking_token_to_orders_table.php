<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('tracking_token', 64)->nullable()->unique()->after('order_number');
        });

        // Generate tracking tokens for existing orders
        foreach (\App\Models\Order::whereNull('tracking_token')->get() as $order) {
            $order->timestamps = false; // Prevent modifying updated_at
            $order->update([
                'tracking_token' => \Illuminate\Support\Str::random(32)
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('tracking_token');
        });
    }
};
