<?php

namespace App\Notifications;

use App\Models\Intervention;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

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
