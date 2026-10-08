<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('deployment_runs', function (Blueprint $table) {
            $table->id();
            $table->string('client_id', 100);
            $table->string('release_id', 100)->nullable();
            $table->string('source_hash', 64)->nullable();
            $table->string('outcome', 30);
            $table->json('checks');
            $table->json('versions');
            $table->timestamp('checked_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('deployment_runs');
    }
};
