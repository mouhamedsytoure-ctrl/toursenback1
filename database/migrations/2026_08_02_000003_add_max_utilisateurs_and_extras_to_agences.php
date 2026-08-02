<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('agences', function (Blueprint $table) {
            $table->unsignedInteger('max_utilisateurs')->default(1)->after('quota_logements');
            // Formule souhaitee a l'inscription (avant paiement) : purement informatif,
            // n'est jamais utilise pour l'application des droits.
            $table->enum('plan_souhaite', ['starter', 'pro', 'illimite'])->nullable()->after('plan');
        });

        $plans = config('plans');
        foreach ($plans as $cle => $p) {
            DB::table('agences')->where('plan', $cle)
                ->update(['max_utilisateurs' => $p['max_utilisateurs']]);
        }
    }

    public function down(): void
    {
        Schema::table('agences', function (Blueprint $table) {
            $table->dropColumn(['max_utilisateurs', 'plan_souhaite']);
        });
    }
};
