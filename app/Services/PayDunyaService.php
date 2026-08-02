<?php

namespace App\Services;

use App\Models\FactureAbonnement;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Integration PayDunya (facture "Checkout Invoice"), qui agrege Wave,
 * Orange Money, Free Money et cartes bancaires derriere une seule page
 * de paiement hebergee par PayDunya.
 *
 * IMPORTANT : non teste en conditions reelles tant qu'aucun compte marchand
 * PayDunya n'existe (voir config/services.php -> 'paydunya'). Les noms de
 * champs suivent la documentation officielle "Checkout Invoice" ; si la
 * reponse reelle differe legerement, les logs (Log::info ci-dessous)
 * permettront de corriger rapidement une fois les vraies cles disponibles.
 */
class PayDunyaService
{
    private string $base = 'https://app.paydunya.com/api/v1';

    private function headers(): array
    {
        return [
            'PAYDUNYA-MASTER-KEY'  => config('services.paydunya.master_key'),
            'PAYDUNYA-PRIVATE-KEY' => config('services.paydunya.private_key'),
            'PAYDUNYA-PUBLIC-KEY'  => config('services.paydunya.public_key'),
            'PAYDUNYA-TOKEN'       => config('services.paydunya.token'),
            'Content-Type'         => 'application/json',
        ];
    }

    private function configure(): void
    {
        if (! config('services.paydunya.master_key')) {
            throw new RuntimeException(
                "PayDunya n'est pas configure : renseigne PAYDUNYA_MASTER_KEY et les autres cles dans .env."
            );
        }
    }

    /**
     * Cree une facture PayDunya et renvoie l'URL de paiement vers laquelle
     * rediriger l'utilisateur, ainsi que le token pour le suivi.
     */
    public function creerFacture(FactureAbonnement $facture, string $nomFormule): array
    {
        $this->configure();

        $payload = [
            'invoice' => [
                'total_amount' => $facture->montant,
                'description'  => "Abonnement {$nomFormule} - Sunnu Immo (facture #{$facture->id})",
            ],
            'store' => [
                'name' => 'Sunnu Immo',
            ],
            'actions' => [
                'cancel_url'   => config('app.frontend_url') . '/abonnement?paiement=annule',
                'return_url'   => config('app.frontend_url') . '/abonnement?paiement=retour',
                'callback_url' => route('paydunya.webhook'),
            ],
            'custom_data' => [
                'facture_abonnement_id' => $facture->id,
            ],
        ];

        $res = Http::withHeaders($this->headers())
            ->post("{$this->base}/checkout-invoice/create", $payload);

        Log::info('PayDunya creation facture', ['payload' => $payload, 'reponse' => $res->json()]);

        $data = $res->json();

        if (! $res->successful() || ($data['response_code'] ?? null) !== '00') {
            throw new RuntimeException(
                'Creation de la facture PayDunya echouee : ' . ($data['response_text'] ?? 'reponse invalide')
            );
        }

        $token = $data['token'] ?? null;
        if (! $token) {
            throw new RuntimeException('PayDunya n\'a renvoye aucun token de facture.');
        }

        $url = $data['response_url'] ?? "https://paydunya.com/checkout/invoice/{$token}";

        return ['token' => $token, 'url' => $url];
    }

    /**
     * Reconfirme aupres de PayDunya qu'une facture est reellement payee.
     * Ne jamais se fier au seul contenu du webhook : toujours revalider ici.
     */
    public function estPayee(string $token): bool
    {
        $this->configure();

        $res = Http::withHeaders($this->headers())
            ->get("{$this->base}/checkout-invoice/confirm/{$token}");

        Log::info('PayDunya confirmation facture', ['token' => $token, 'reponse' => $res->json()]);

        $data = $res->json();

        return $res->successful()
            && ($data['response_code'] ?? null) === '00'
            && ($data['status'] ?? null) === 'completed';
    }
}
