<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            // The legacy host calculated and displayed all orders in PKR.
            $table->char('currency', 3)->default('PKR');
            $table->string('currency_symbol', 10)->default('Rs.');
            $table->decimal('tax_rate', 5, 2)->default(0);
            $table->json('brand_snapshot')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('orders', fn (Blueprint $table) => $table->dropColumn(['currency', 'currency_symbol', 'tax_rate', 'brand_snapshot']));
    }
};
