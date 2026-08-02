<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Numero WhatsApp de l'agence, distinct du telephone d'appel classique.
    // Affiche sur la vitrine publique en plus (ou a la place) du telephone.
    public function up(): void
    {
        Schema::table('agences', function (Blueprint $table) {
            $table->string('whatsapp')->nullable()->after('telephone');
        });
    }

    public function down(): void
    {
        Schema::table('agences', function (Blueprint $table) {
            $table->dropColumn('whatsapp');
        });
    }
};
