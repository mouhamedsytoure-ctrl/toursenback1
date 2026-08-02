<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * Remplace la notification par defaut de Laravel (qui pointe vers une route
 * web) pour envoyer un lien vers le front Angular, avec token + email.
 */
class ReinitialiserMotDePasse extends ResetPassword
{
    public function toMail($notifiable): MailMessage
    {
        $url = rtrim(config('app.frontend_url'), '/')
            . '/reinitialiser-mot-de-passe?token=' . $this->token
            . '&email=' . urlencode($notifiable->getEmailForPasswordReset());

        return (new MailMessage)
            ->subject('Reinitialisation de votre mot de passe')
            ->line('Vous recevez cet email car une reinitialisation de mot de passe a ete demandee pour votre compte.')
            ->action('Reinitialiser mon mot de passe', $url)
            ->line('Ce lien expire dans 60 minutes.')
            ->line("Si vous n'etes pas a l'origine de cette demande, aucune action n'est requise.");
    }
}
