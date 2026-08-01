<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    // Permet un compte plateforme (is_platform_admin) sans agence rattachee.
    // Les 7 autres tables restent volontairement en NOT NULL.
    public function up(): void
    {
        DB::statement('ALTER TABLE users MODIFY agence_id BIGINT UNSIGNED NULL');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE users MODIFY agence_id BIGINT UNSIGNED NOT NULL');
    }
};
