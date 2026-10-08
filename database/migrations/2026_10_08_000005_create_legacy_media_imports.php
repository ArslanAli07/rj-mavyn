<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('legacy_media_imports', function (Blueprint $table) {
            $table->id();
            $table->string('source_key', 64)->unique();
            $table->string('source_disk');
            $table->text('source_path');
            $table->string('checksum', 64);
            $table->string('visibility');
            $table->foreignId('media_asset_id')->nullable()->constrained()->nullOnDelete();
            $table->string('destination_disk')->nullable();
            $table->text('destination_path')->nullable();
            $table->string('status')->default('pending')->index();
            $table->text('last_error')->nullable();
            $table->timestamps();
            $table->index(['visibility', 'checksum']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('legacy_media_imports');
    }
};
