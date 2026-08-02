<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Trace chaque tentative de paiement d'abonnement (PayDunya).
    // Le statut n'est jamais mis a jour depuis le contenu du webhook seul :
    // toujours reconfirme aupres de l'API PayDunya (voir PayDunyaService).
    public function up(): void
    {
        Schema::create('factures_abonnement', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agence_id')->constrained('agences')->cascadeOnDelete();
            $table->string('plan'); // starter | pro | illimite
            $table->unsignedInteger('montant'); // FCFA
            $table->string('token_paydunya')->nullable()->unique();
            $table->enum('statut', ['en_attente', 'payee', 'echouee', 'annulee'])->default('en_attente');
            $table->timestamp('payee_le')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('factures_abonnement');
    }
};
