<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('coupon_usages', function (Blueprint $table) {
            $table->string('identity_hash', 64)->nullable();
            $table->string('account_identity_hash', 64)->nullable();
            $table->index(['coupon_id', 'identity_hash']);
            $table->index(['coupon_id', 'account_identity_hash']);
        });

        // Historical usages have no captured IP. Keep their identities unknown;
        // existing user_id still enforces signed-in account limits.
    }

    public function down(): void
    {
        Schema::table('coupon_usages', function (Blueprint $table) {
            $table->dropIndex(['coupon_id', 'identity_hash']);
            $table->dropIndex(['coupon_id', 'account_identity_hash']);
            $table->dropColumn(['identity_hash', 'account_identity_hash']);
        });
    }
};
