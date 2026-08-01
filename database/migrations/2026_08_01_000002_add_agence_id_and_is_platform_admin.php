<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Tables a rattacher a une agence
    private array $tables = [
        'users', 'immeubles', 'terrains', 'logements',
        'contrats', 'paiements', 'reclamations', 'medias',
    ];

    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_platform_admin')->default(false)->after('role');
        });

        // 1) colonne agence_id nullable + FK, sur chaque table
        foreach ($this->tables as $nomTable) {
            Schema::table($nomTable, function (Blueprint $table) {
                $table->foreignId('agence_id')->nullable()
                    ->constrained('agences')->cascadeOnDelete();
            });
        }

        // 2) rattachement de toutes les lignes existantes a l'agence SITS
        $agenceSitsId = DB::table('agences')->where('slug', 'sits')->value('id');

        foreach ($this->tables as $nomTable) {
            DB::table($nomTable)->update(['agence_id' => $agenceSitsId]);
        }

        // 3) passage en NOT NULL (pas de doctrine/dbal installe -> SQL brut)
        foreach ($this->tables as $nomTable) {
            DB::statement("ALTER TABLE `$nomTable` MODIFY `agence_id` BIGINT UNSIGNED NOT NULL");
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $nomTable) {
            Schema::table($nomTable, function (Blueprint $table) {
                $table->dropForeign(['agence_id']);
                $table->dropColumn('agence_id');
            });
        }

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('is_platform_admin');
        });
    }
};
