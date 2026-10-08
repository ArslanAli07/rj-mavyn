<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('extension_runtime', function (Blueprint $table) {
            $table->unsignedTinyInteger('id')->primary();
            $table->unsignedBigInteger('revision')->default(0);
        });
        DB::table('extension_runtime')->insert(['id' => 1, 'revision' => 0]);
        Schema::create('plugin_states', function (Blueprint $table) {
            $table->string('plugin_id', 100)->primary();
            $table->boolean('active')->default(false);
            $table->string('version', 100);
            $table->timestamps();
        });
        Schema::create('plugin_settings', function (Blueprint $table) {
            $table->id();
            $table->string('plugin_id', 100);
            $table->string('key', 100);
            $table->text('value')->nullable();
            $table->boolean('secret')->default(false);
            $table->unique(['plugin_id', 'key']);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plugin_settings');
        Schema::dropIfExists('plugin_states');
        Schema::dropIfExists('extension_runtime');
    }
};
