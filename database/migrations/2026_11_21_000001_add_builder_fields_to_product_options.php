<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_option_groups', function (Blueprint $table) {
            // Step of the product builder this group appears in ('style' or 'extras'); null keeps the classic layout.
            $table->string('step', 20)->nullable()->after('sort_order');
            // How the choices render: select (classic dropdown), tiles (buttons with prices) or swatches (colours).
            $table->string('display', 20)->default('select')->after('step');
            $table->string('help_text', 255)->nullable()->after('display');
            // The group only applies while this option value is selected (e.g. "Gloves size" needs "Matching Gloves").
            $table->unsignedBigInteger('depends_on_value_id')->nullable()->after('help_text');
        });
        Schema::table('product_option_values', function (Blueprint $table) {
            $table->string('swatch', 9)->nullable()->after('sort_order');
            // When set, choosing this value asks the customer for text (e.g. a Pantone colour code).
            $table->string('input_label', 120)->nullable()->after('swatch');
        });
        Schema::table('products', function (Blueprint $table) {
            $table->boolean('allows_design_upload')->default(false);
        });
        foreach (['cart_items', 'order_items'] as $name) {
            Schema::table($name, function (Blueprint $table) {
                // {inputs: [{value_id,label,value}], picks: [{group_id,label,values}], files: [{path,name,size,mime}]}
                $table->json('customization')->nullable();
            });
        }
    }

    public function down(): void
    {
        foreach (['cart_items', 'order_items'] as $name) {
            Schema::table($name, fn (Blueprint $table) => $table->dropColumn('customization'));
        }
        Schema::table('products', fn (Blueprint $table) => $table->dropColumn('allows_design_upload'));
        Schema::table('product_option_values', fn (Blueprint $table) => $table->dropColumn(['swatch', 'input_label']));
        Schema::table('product_option_groups', fn (Blueprint $table) => $table->dropColumn(['step', 'display', 'help_text', 'depends_on_value_id']));
    }
};
