<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('paiements', function (Blueprint $table) {
            $table->string('motif_annulation')->nullable()->after('recu_envoye_at');
            $table->dateTime('annule_le')->nullable()->after('motif_annulation');
            $table->foreignId('annule_par')->nullable()->after('annule_le')
                  ->constrained('users')->nullOnDelete();
        });

        // On ne supprime jamais un paiement paye : on l'annule (trace + motif obligatoire),
        // pour eviter qu'un paiement confirme puisse disparaitre sans laisser d'historique.
        DB::statement("ALTER TABLE paiements MODIFY statut ENUM('paye','en_attente','retard','impaye','annule') NOT NULL DEFAULT 'en_attente'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE paiements MODIFY statut ENUM('paye','en_attente','retard','impaye') NOT NULL DEFAULT 'en_attente'");

        Schema::table('paiements', function (Blueprint $table) {
            $table->dropForeign(['annule_par']);
            $table->dropColumn(['motif_annulation', 'annule_le', 'annule_par']);
        });
    }
};
