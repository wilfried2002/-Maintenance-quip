<?php

namespace App\Notifications;

use App\Models\DemandeIntervention;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Préviens les responsables du module qu'une demande d'intervention attend leur
 * validation (workflow 7/10).
 */
class DemandeInterventionSoumise extends Notification
{
    use Queueable;

    public function __construct(
        public DemandeIntervention $demande,
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'kind' => 'demande',
            'demande_id' => $this->demande->id,
            'title' => 'Nouvelle demande d\'intervention',
            'body' => $this->demande->titre.' — '.$this->labelModule(),
            'url' => '/demandes',
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Nouvelle demande d\'intervention — '.$this->demande->titre)
            ->line($this->demande->demandeur?->name.' a soumis une demande ('.$this->labelModule().') :')
            ->line($this->demande->titre)
            ->action('Traiter la demande', url('/demandes'));
    }

    private function labelModule(): string
    {
        return match ($this->demande->module) {
            'parc_automobile' => 'Parc automobile',
            'equipements_industriels' => 'Équipements industriels',
            'equipement_bureau' => 'Équipements de bureau',
            default => $this->demande->module,
        };
    }
}
