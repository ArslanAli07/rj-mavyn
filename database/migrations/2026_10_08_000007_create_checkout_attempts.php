<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('checkout_attempts', function (Blueprint $table) {
            $table->string('key_hash', 64)->primary();
            $table->string('owner_hash', 64);
            $table->string('cart_hash', 64);
            $table->string('request_hash', 64)->nullable();
            $table->string('receipt_hash', 64)->nullable();
            // Keep the completed key as a tombstone when an order is deleted.
            $table->foreignId('order_id')->nullable()->unique()->constrained()->nullOnDelete();
            $table->timestamp('committed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('checkout_attempts');
    }
};
