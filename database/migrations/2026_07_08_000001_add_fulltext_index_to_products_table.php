<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add a FULLTEXT index on products(title, short_description) for fast
     * full-text search. Only runs on MySQL/MariaDB — SQLite (used in tests)
     * does not support FULLTEXT so the migration is skipped there.
     */
    public function up(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::statement('ALTER TABLE products ADD FULLTEXT search_index (title, short_description)');
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::statement('ALTER TABLE products DROP INDEX search_index');
    }
};
