<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('currency_price_backfill_exclusions', function (Blueprint $table) {
            $table->string('kind', 20);
            $table->unsignedBigInteger('entity_id');
            $table->char('currency', 3);
            $table->primary(['kind', 'entity_id', 'currency']);
        });
    }

    public function down(): void
    {
        throw new RuntimeException('Retain deliberate price deletions; deploy a forward fix or restore a reviewed backup.');
    }
};
