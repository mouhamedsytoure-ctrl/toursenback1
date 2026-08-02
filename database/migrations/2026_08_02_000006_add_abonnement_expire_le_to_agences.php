<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Date jusqu'a laquelle un abonnement PAYANT est valide (distinct de
    // essai_termine_le, qui concerne uniquement le plan essai). Null = pas
    // de limite (accord manuel du proprietaire de la plateforme).
    public function up(): void
    {
        Schema::table('agences', function (Blueprint $table) {
            $table->timestamp('abonnement_expire_le')->nullable()->after('essai_termine_le');
        });
    }

    public function down(): void
    {
        Schema::table('agences', function (Blueprint $table) {
            $table->dropColumn('abonnement_expire_le');
        });
    }
};
