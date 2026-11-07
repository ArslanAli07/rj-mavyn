<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The mega-menu groups a group's subcategories into named columns
     * ("subgroup", e.g. "Jackets" / "Coats") and can flag a link with a
     * "nav_badge" ("new" | "hot").
     */
    public function up(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->string('subgroup')->nullable()->after('parent_id');
            $table->string('nav_badge')->nullable()->after('subgroup');
        });
    }

    public function down(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->dropColumn(['subgroup', 'nav_badge']);
        });
    }
};
