<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('home_slides', function (Blueprint $table) {
            $table->id();
            $table->string('image_path')->nullable();
            $table->string('title');
            $table->string('title_highlighted')->nullable();
            $table->text('description')->nullable();
            $table->string('button_text')->default('Explore Collection');
            $table->string('button_link')->default('/shop');
            $table->string('left_icon')->default('🍰');
            $table->string('right_badge_text')->default('• 100% Organic Ingredients • Premium Taste');
            $table->string('right_badge_emoji')->default('🍰');
            $table->integer('display_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

    }

    public function down(): void
    {
        Schema::dropIfExists('home_slides');
    }
};
