<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_number')->unique(); // TAS-2024-00001
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('coupon_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('delivery_zone_id')->nullable()->constrained()->nullOnDelete();

            // Customer snapshot (in case user is deleted)
            $table->string('customer_name');
            $table->string('customer_email');
            $table->string('customer_phone');

            // Delivery address snapshot
            $table->string('delivery_country')->default('Pakistan');
            $table->string('delivery_city');
            $table->string('delivery_area')->nullable();
            $table->text('delivery_address');
            $table->string('delivery_postal_code')->nullable();

            // Financials
            $table->decimal('subtotal', 10, 2);
            $table->decimal('delivery_fee', 10, 2)->default(0);
            $table->decimal('discount_amount', 10, 2)->default(0);
            $table->decimal('tax_amount', 10, 2)->default(0);
            $table->decimal('grand_total', 10, 2);

            // Payment
            $table->enum('payment_method', ['cod', 'easypaisa', 'jazzcash', 'bank_transfer'])->default('cod');
            $table->enum('payment_status', ['pending', 'paid', 'failed', 'refunded'])->default('pending');

            // Order Status
            $table->enum('status', [
                'pending', 'confirmed', 'preparing', 'out_for_delivery', 'delivered', 'cancelled'
            ])->default('pending');

            $table->text('order_notes')->nullable();
            $table->text('admin_notes')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamps();

            $table->index('order_number');
            $table->index('user_id');
            $table->index('status');
            $table->index('payment_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
