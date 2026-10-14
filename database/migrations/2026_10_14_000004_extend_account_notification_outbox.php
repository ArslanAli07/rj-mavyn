<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notification_outbox', function (Blueprint $table) {
            $table->unsignedBigInteger('order_id')->nullable()->change();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('discarded_at')->nullable();
        });
    }

    public function down(): void
    {
        // Retain account notification history and nullable references on rollback.
        Schema::table('notification_outbox', fn (Blueprint $table) => $table->dropColumn(['user_id', 'expires_at', 'discarded_at']));
    }
};
