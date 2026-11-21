<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('measurement_submissions', function (Blueprint $table) {
            $table->id();
            // Linked only when the order number and the order's email both match; otherwise matched by staff.
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->string('order_reference', 64);
            $table->string('name', 120);
            $table->string('email');
            $table->string('garment', 20);
            $table->string('fit', 10);
            $table->string('unit', 10);
            $table->json('measurements');
            $table->text('notes')->nullable();
            $table->json('photos')->nullable();
            $table->timestamp('confirmed_at');
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
            $table->index('order_reference');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('measurement_submissions');
    }
};
