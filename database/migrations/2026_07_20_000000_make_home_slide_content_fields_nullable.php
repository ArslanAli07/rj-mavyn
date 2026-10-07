<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('home_slides', function (Blueprint $table) {
            $table->string('title')->nullable()->change();
            $table->string('button_text')->nullable()->default(null)->change();
            $table->string('button_link')->nullable()->default(null)->change();
            $table->string('left_icon')->nullable()->default(null)->change();
            $table->string('right_badge_text')->nullable()->default(null)->change();
            $table->string('right_badge_emoji')->nullable()->default(null)->change();
        });
    }

    public function down(): void
    {
        Schema::table('home_slides', function (Blueprint $table) {
            $table->string('title')->nullable(false)->change();
            $table->string('button_text')->default('Explore Collection')->nullable(false)->change();
            $table->string('button_link')->default('/shop')->nullable(false)->change();
            $table->string('left_icon')->default('🍰')->nullable(false)->change();
            $table->string('right_badge_text')->default('• 100% Organic Ingredients • Premium Taste')->nullable(false)->change();
            $table->string('right_badge_emoji')->default('🍰')->nullable(false)->change();
        });
    }
};
