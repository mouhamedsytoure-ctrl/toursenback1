<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Identite legale de l'agence : indispensable pour les baux et les quittances.
 * Sans ces champs, chaque agence editerait des documents au nom d'une autre.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('agences', function (Blueprint $table) {
            $table->string('representant_legal')->nullable()->after('nom');
            $table->string('representant_fonction')->nullable()->after('representant_legal');
            $table->string('ninea')->nullable()->after('ville');
            $table->string('rccm')->nullable()->after('ninea');
        });

        // L'agence historique garde son identite reelle.
        DB::table('agences')->where('slug', 'sits')->update([
            'representant_legal'    => 'Djibril TIMERA',
            'representant_fonction' => 'Gerant',
            'adresse'               => 'Rue 13x12 Medina, Dakar, Senegal',
            'telephone'             => '33 882 27 28 / 77 566 03 77',
            'email'                 => 'toursen.immo@gmail.com',
        ]);
    }

    public function down(): void
    {
        Schema::table('agences', function (Blueprint $table) {
            $table->dropColumn(['representant_legal', 'representant_fonction', 'ninea', 'rccm']);
        });
    }
};
