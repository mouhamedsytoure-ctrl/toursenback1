<?php

namespace App\Notifications;

use App\Models\Paiement;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Carbon;

class RecuDisponible extends Notification
{
    use Queueable;

    public function __construct(public Paiement $paiement)
    {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $paiement = $this->paiement;
        $periodeLabel = $this->moisFr($paiement->periode);
        $montant = number_format((float) $paiement->montant, 0, '.', ' ');
        $frontend = rtrim(config('app.frontend_url'), '/');
        $lien = $frontend . '/espace?tab=paiements&paiement=' . $paiement->id;

        return (new MailMessage)
            ->subject('Votre reçu de loyer est disponible — SITS')
            ->greeting('Bonjour ' . $notifiable->name . ',')
            ->line("Votre paiement de loyer pour {$periodeLabel} ({$montant} FCFA) a bien été enregistré.")
            ->line('Votre reçu est disponible dès maintenant dans votre espace locataire.')
            ->action('Voir mon reçu', $lien)
            ->line("Si le bouton ne fonctionne pas, connectez-vous simplement sur l'application et rendez-vous dans l'onglet \"Paiements\".");
    }

    private function moisFr(?string $periode): string
    {
        if (! $periode) return '';
        $mois = ['01' => 'janvier', '02' => 'février', '03' => 'mars', '04' => 'avril',
            '05' => 'mai', '06' => 'juin', '07' => 'juillet', '08' => 'août',
            '09' => 'septembre', '10' => 'octobre', '11' => 'novembre', '12' => 'décembre'];
        $parts = explode('-', $periode);
        if (count($parts) === 2 && isset($mois[$parts[1]])) {
            return $mois[$parts[1]] . ' ' . $parts[0];
        }
        return $periode;
    }
}
