<?php

namespace App\Notifications;

use App\Models\EquipementBureau;
use App\Models\EquipementIndustriel;
use App\Models\Intervention;
use App\Models\Vehicule;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class InterventionAssignee extends Notification
{
    use Queueable;

    public function __construct(public Intervention $intervention)
    {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'kind' => 'intervention_assignee',
            'intervention_id' => $this->intervention->id,
            'title' => 'Intervention à démarrer',
            'body' => $this->intervention->titre.' — '.$this->equipementLabel(),
            'url' => $this->interventionUrl(),
        ];
    }

    private function equipementLabel(): string
    {
        $equipement = $this->intervention->equipementable;

        return $equipement?->code.' — '.($equipement?->designation ?? $equipement?->immatriculation ?? 'Équipement');
    }

    private function interventionUrl(): string
    {
        $route = match ($this->intervention->equipementable_type) {
            EquipementIndustriel::class => 'equipements-industriels.interventions.index',
            Vehicule::class => 'vehicules.interventions.index',
            EquipementBureau::class => 'equipements-bureau.interventions.index',
            default => 'dashboard',
        };

        return route($route, absolute: false);
    }
}
