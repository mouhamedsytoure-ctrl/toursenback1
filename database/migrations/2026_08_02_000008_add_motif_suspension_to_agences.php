<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Permet de distinguer une suspension "reparable en payant" (l'agence
    // voit les formules et un paiement la reactive automatiquement) d'une
    // suspension pour un autre motif (l'agence doit contacter le proprietaire
    // de la plateforme, payer ne change rien).
    public function up(): void
    {
        Schema::table('agences', function (Blueprint $table) {
            $table->enum('motif_suspension', ['paiement', 'autre'])->nullable()->after('statut');
            $table->string('note_suspension', 500)->nullable()->after('motif_suspension');
        });
    }

    public function down(): void
    {
        Schema::table('agences', function (Blueprint $table) {
            $table->dropColumn(['motif_suspension', 'note_suspension']);
        });
    }
};
