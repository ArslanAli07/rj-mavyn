<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['products', 'product_variants'] as $name) {
            Schema::table($name, fn (Blueprint $table) => $table->json('plugin_attributes')->nullable());
        }
    }

    public function down(): void
    {
        foreach (['products', 'product_variants'] as $name) {
            Schema::table($name, fn (Blueprint $table) => $table->dropColumn('plugin_attributes'));
        }
    }
};
