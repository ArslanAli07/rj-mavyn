<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Apparel and footwear catalogues ship a measurement chart and a written
     * specification list per product. Both are stored with the product so the
     * storefront can render them without a second data source.
     */
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->json('size_chart')->nullable()->after('description');
            $table->json('specifications')->nullable()->after('size_chart');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['size_chart', 'specifications']);
        });
    }
};
