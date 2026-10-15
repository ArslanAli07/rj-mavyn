<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('gateway_payment_attempts', function (Blueprint $table) {
            $table->json('review_reasons')->nullable();
        });
        // Existing holds have unknown provenance and must not be cleared by a dispute callback.
        DB::table('gateway_payment_attempts')->where('needs_review', true)->update([
            'review_reasons' => json_encode(['legacy_review'], JSON_THROW_ON_ERROR),
        ]);
    }

    public function down(): void
    {
        throw new RuntimeException('Payment review reasons must be retained; deploy a forward fix.');
    }
};
