<?php

namespace App\Notifications;

use App\Models\Intervention;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * Préviens les admins (et le technicien assigné lorsqu'il n'est pas l'acteur)
 * du changement de statut d'une intervention (workflow — reintgré depuis le
 * commit du 31/08 du dépôt principal).
 */
class InterventionStatusUpdated extends Notification
{
    use Queueable;

    public function __construct(public Intervention $intervention, public string $statusLabel)
    {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'kind' => 'intervention_status_updated',
            'intervention_id' => $this->intervention->id,
            'title' => 'Statut d’intervention mis à jour',
            'body' => $this->intervention->titre.' : '.$this->statusLabel,
            'url' => route('dashboard', absolute: false),
        ];
    }
}
