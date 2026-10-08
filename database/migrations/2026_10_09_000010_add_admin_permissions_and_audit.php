<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('admins', fn (Blueprint $table) => $table->json('permissions')->nullable());
        Schema::create('admin_audit_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('actor_id')->nullable()->index();
            $table->string('actor_name');
            $table->string('route');
            $table->string('action');
            $table->string('entity_type');
            $table->string('entity_id')->index();
            $table->json('changes');
            $table->timestamp('created_at')->useCurrent()->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_audit_logs');
        Schema::table('admins', fn (Blueprint $table) => $table->dropColumn('permissions'));
    }
};
