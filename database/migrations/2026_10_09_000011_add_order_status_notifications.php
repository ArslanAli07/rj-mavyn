<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', fn (Blueprint $table) => $table->unsignedBigInteger('status_revision')->default(0));
        Schema::table('notification_outbox', function (Blueprint $table) {
            $table->string('notification_type')->default('order_confirmation');
            $table->index(['order_id', 'sent_at', 'id']);
        });
    }

    public function down(): void
    {
        Schema::table('notification_outbox', function (Blueprint $table) {
            $table->dropIndex(['order_id', 'sent_at', 'id']);
            $table->dropColumn('notification_type');
        });
        Schema::table('orders', fn (Blueprint $table) => $table->dropColumn('status_revision'));
    }
};
