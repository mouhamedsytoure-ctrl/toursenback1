<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;

class BienvenueLocataire extends Notification
{
    use Queueable;

    public function __construct(
        public string $nom,
        public string $emailConnexion,
        public string $motDePasse,
        public ?string $contratPdf = null,
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $frontend = rtrim(config('app.frontend_url'), '/');

        $message = (new MailMessage)
            ->subject('Bienvenue chez SITS — votre espace locataire est prêt')
            ->greeting('Bonjour ' . $this->nom . ',')
            ->line("Votre compte locataire vient d'être créé sur l'application SITS.")
            ->line("Depuis votre espace, vous pouvez à tout moment : consulter votre contrat, suivre vos paiements de loyer et télécharger vos reçus, et envoyer une réclamation à votre agence.")
            ->action('Me connecter', $frontend)
            ->line('Vos identifiants de connexion :')
            ->line("Identifiant : **{$this->emailConnexion}**")
            ->line("Mot de passe provisoire : **{$this->motDePasse}**")
            ->line('Merci de changer ce mot de passe dès votre première connexion, depuis votre espace.')
            ->line("Cet identifiant sert uniquement à vous connecter à l'application : les messages de l'agence continueront de vous parvenir sur votre adresse email habituelle.");

        if ($this->contratPdf !== null) {
            $message->line('Vous trouverez votre contrat de location en pièce jointe.')
                ->attachData($this->contratPdf, 'contrat.pdf', ['mime' => 'application/pdf']);
        }

        return $message;
    }
}
