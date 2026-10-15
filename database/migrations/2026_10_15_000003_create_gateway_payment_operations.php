<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Provider identifiers are case-sensitive, including on case-insensitive MySQL installs.
        $identifiers = Schema::getConnection()->getDriverName() === 'mysql' ? 'utf8mb4_bin' : null;
        Schema::create('gateway_payment_attempts', function (Blueprint $table) use ($identifiers) {
            $table->uuid('id')->primary();
            $table->foreignId('order_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('sequence');
            $table->unique(['order_id', 'sequence']);
            $table->string('plugin_id', 100);
            $table->string('provider_key', 150);
            $table->string('provider_class');
            $table->unsignedBigInteger('amount_minor');
            $table->char('currency', 3);
            $table->string('owner_reference', 64);
            $table->string('status', 30)->default('created');
            $table->string('reference')->collation($identifiers)->nullable();
            $table->unique(['provider_key', 'reference']);
            $table->json('action')->nullable();
            $table->boolean('needs_review')->default(false);
            $table->boolean('captured')->default(false);
            $table->timestamp('lease_until')->nullable();
            $table->uuid('lease_token')->nullable();
            $table->timestamp('retry_at')->nullable();
            $table->unsignedInteger('tries')->default(0);
            $table->string('last_error_class')->nullable();
            $table->timestamps();
            $table->index(['status', 'retry_at']);
            $table->index(['order_id', 'created_at']);
        });
        Schema::create('gateway_refunds', function (Blueprint $table) use ($identifiers) {
            $table->uuid('id')->primary();
            $table->foreignUuid('attempt_id')->constrained('gateway_payment_attempts')->restrictOnDelete();
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->unsignedBigInteger('amount_minor');
            $table->char('currency', 3);
            $table->string('status', 30)->default('created');
            $table->string('reference')->collation($identifiers)->nullable();
            $table->unique(['attempt_id', 'reference']);
            $table->timestamp('lease_until')->nullable();
            $table->uuid('lease_token')->nullable();
            $table->timestamp('retry_at')->nullable();
            $table->unsignedInteger('tries')->default(0);
            $table->string('last_error_class')->nullable();
            $table->timestamps();
            $table->index(['status', 'retry_at']);
        });
        Schema::create('gateway_events', function (Blueprint $table) use ($identifiers) {
            $table->id();
            $table->string('provider_key', 150);
            $table->string('event_id')->collation($identifiers);
            $table->unique(['provider_key', 'event_id']);
            $table->string('fingerprint', 64);
            $table->uuid('operation_id');
            $table->string('status', 30);
            $table->unsignedBigInteger('amount_minor');
            $table->char('currency', 3);
            $table->timestamp('created_at');
        });
    }

    public function down(): void
    {
        throw new RuntimeException('Online payment history must be retained; use a reviewed recovery plan or forward fix.');
    }
};
