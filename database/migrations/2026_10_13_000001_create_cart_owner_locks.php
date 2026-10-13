<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One persistent lock row per cart identity, including an empty guest cart.
        Schema::create('cart_owner_locks', function (Blueprint $table) {
            $table->char('owner_hash', 64)->primary();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cart_owner_locks');
    }
};
