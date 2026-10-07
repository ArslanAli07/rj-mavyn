<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('media_assets', function (Blueprint $table) {
            $table->string('status')->default('active')->index();
            $table->string('processing_status')->default('pending')->index();
            $table->text('processing_error')->nullable();
            $table->unsignedInteger('processing_generation')->default(1);
            $table->unsignedInteger('processing_attempts')->default(0);
            $table->timestamp('processing_started_at')->nullable();
        });
        Schema::table('order_items', function (Blueprint $table) {
            $table->foreignId('media_asset_id')->nullable()->constrained('media_assets')->restrictOnDelete();
            $table->json('media_snapshot')->nullable();
        });
        Schema::create('media_retention_holds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('media_asset_id')->constrained()->restrictOnDelete();
            $table->foreignId('order_item_id')->nullable()->constrained()->nullOnDelete();
            $table->string('reference')->unique();
            $table->json('snapshot');
            $table->timestamps();
        });
        // Independent of the asset row: committed cleanup intent must outlive metadata deletion.
        Schema::create('media_cleanup_tasks', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('media_asset_id')->nullable()->unique();
            $table->json('paths');
            $table->string('status')->default('queued')->index();
            $table->unsignedInteger('attempts')->default(0);
            $table->text('last_error')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('media_cleanup_tasks');
        Schema::dropIfExists('media_retention_holds');
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('media_asset_id');
            $table->dropColumn('media_snapshot');
        });
        Schema::table('media_assets', function (Blueprint $table) {
            $table->dropColumn(['status', 'processing_status', 'processing_error', 'processing_generation', 'processing_attempts', 'processing_started_at']);
        });
    }
};
