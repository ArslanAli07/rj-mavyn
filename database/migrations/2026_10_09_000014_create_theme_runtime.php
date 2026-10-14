<?php

use App\Services\Themes\InstalledThemeCatalog;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Mavyn\Core\Themes\ThemeCatalog;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('theme_selection', function (Blueprint $table) {
            $table->unsignedTinyInteger('id')->primary();
            $table->string('active_theme', 100);
            $table->string('previous_theme', 100)->nullable();
            $table->timestamps();
        });
        Schema::create('theme_settings', function (Blueprint $table) {
            $table->string('theme_id', 100);
            $table->string('key', 100);
            $table->json('value');
            $table->timestamps();
            $table->primary(['theme_id', 'key']);
        });
        // Preserve the existing presentation without writing commerce or merchant media.
        $installed = is_file(config('themes.catalog')) ? app(InstalledThemeCatalog::class)->load()->all() : [];
        $initial = isset($installed['mavyn.theme-happy-tea']) ? 'mavyn.theme-happy-tea' : ThemeCatalog::DEFAULT;
        DB::table('theme_selection')->insert(['id' => 1, 'active_theme' => $initial, 'created_at' => now(), 'updated_at' => now()]);
    }

    public function down(): void
    {
        Schema::dropIfExists('theme_settings');
        Schema::dropIfExists('theme_selection');
    }
};
