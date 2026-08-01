<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agences', function (Blueprint $table) {
            $table->id();
            $table->string('nom');
            $table->string('slug')->unique();
            $table->string('logo')->nullable();
            $table->string('telephone')->nullable();
            $table->string('email')->nullable();
            $table->string('adresse')->nullable();
            $table->string('ville')->nullable();
            $table->enum('plan', ['essai', 'starter', 'pro', 'illimite'])->default('essai');
            $table->enum('statut', ['actif', 'suspendu', 'expire'])->default('actif');
            $table->unsignedInteger('quota_logements')->default(0);
            $table->timestamp('essai_termine_le')->nullable();
            $table->timestamps();
        });

        // Agence historique : toutes les donnees existantes lui seront rattachees
        // dans la migration suivante (add_agence_id_and_is_platform_admin).
        DB::table('agences')->insert([
            'nom'              => 'SITS',
            'slug'             => 'sits',
            'plan'             => 'illimite',
            'statut'           => 'actif',
            'quota_logements'  => 0,
            'created_at'       => now(),
            'updated_at'       => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('agences');
    }
};
