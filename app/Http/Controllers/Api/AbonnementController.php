<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FactureAbonnement;
use App\Services\PayDunyaService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AbonnementController extends Controller
{
    private const LIBELLES = ['starter' => 'Standard', 'pro' => 'Pro', 'illimite' => 'VIP'];

    /**
     * POST /api/abonnement/payer
     * Cree une facture PayDunya pour la formule choisie et renvoie l'URL
     * de paiement (Wave/Orange Money/carte) vers laquelle le front redirige.
     */
    public function payer(Request $request, PayDunyaService $paydunya)
    {
        abort_unless($request->user()->isSuperAdmin(), 403);

        $data = $request->validate([
            'plan' => ['required', 'in:starter,pro,illimite'],
        ]);

        $agence = $request->user()->agence;
        abort_unless($agence, 404, "Aucune agence rattachee a ce compte.");

        $montant = config("plans.{$data['plan']}.prix_mensuel");

        $facture = FactureAbonnement::create([
            'agence_id' => $agence->id,
            'plan'      => $data['plan'],
            'montant'   => $montant,
            'statut'    => 'en_attente',
        ]);

        try {
            $res = $paydunya->creerFacture($facture, self::LIBELLES[$data['plan']]);
        } catch (\Throwable $e) {
            Log::error('Echec creation facture PayDunya', ['erreur' => $e->getMessage(), 'facture_id' => $facture->id]);
            $facture->update(['statut' => 'echouee']);

            return response()->json([
                'message' => "Le paiement en ligne n'est pas encore disponible. Contactez-nous directement.",
            ], 503);
        }

        $facture->update(['token_paydunya' => $res['token']]);

        return response()->json(['url' => $res['url']]);
    }

    /**
     * POST /api/abonnement/webhook  (PUBLIC -- appele par les serveurs PayDunya)
     * Ne fait jamais confiance au contenu du webhook : reconfirme toujours
     * le statut aupres de l'API PayDunya avant de reactiver quoi que ce soit.
     */
    public function webhook(Request $request, PayDunyaService $paydunya)
    {
        Log::info('Webhook PayDunya recu', $request->all());

        $token = $request->input('data.invoice.token')
            ?? $request->input('token')
            ?? $request->input('invoice_token');

        if (! $token) {
            return response()->json(['message' => 'Token absent.'], 400);
        }

        $facture = FactureAbonnement::where('token_paydunya', $token)->first();
        if (! $facture) {
            Log::warning('Webhook PayDunya : facture inconnue', ['token' => $token]);
            return response()->json(['message' => 'Facture inconnue.'], 404);
        }

        if ($facture->statut === 'payee') {
            return response()->json(['message' => 'Deja traitee.']);
        }

        if (! $paydunya->estPayee($token)) {
            $facture->update(['statut' => 'echouee']);
            return response()->json(['message' => 'Paiement non confirme.']);
        }

        DB::transaction(function () use ($facture) {
            $facture->update(['statut' => 'payee', 'payee_le' => now()]);

            $agence = $facture->agence;
            $agence->update([
                'plan'                  => $facture->plan,
                'plan_souhaite'         => null,
                'statut'                => 'actif',
                'quota_logements'       => config("plans.{$facture->plan}.quota_logements"),
                'max_utilisateurs'      => config("plans.{$facture->plan}.max_utilisateurs"),
                // Reconduit 30 jours a partir de maintenant, ou depuis la fin
                // de la periode en cours si elle n'est pas encore terminee.
                'abonnement_expire_le'  => ($agence->abonnement_expire_le && $agence->abonnement_expire_le->isFuture()
                    ? $agence->abonnement_expire_le
                    : now())->copy()->addDays(30),
            ]);
        });

        return response()->json(['message' => 'Abonnement reactive.']);
    }
}
