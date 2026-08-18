<?php

namespace App\Notifications;

use App\Models\EquipementBureau;
use App\Models\EquipementIndustriel;
use App\Models\PlanMaintenance;
use App\Models\Vehicule;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PlanMaintenanceEnRetard extends Notification
{
    use Queueable;

    public function __construct(public PlanMaintenance $plan)
    {
    }

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toDatabase(object $notifiable): array
    {
        $equipement = $this->plan->equipementable;

        return [
            'kind' => 'plan_en_retard',
            'plan_id' => $this->plan->id,
            'title' => 'Plan de maintenance en retard',
            'body' => $this->plan->operation.' — '.$this->equipementLabel($equipement),
            'url' => $this->equipementUrl($equipement, absolute: false),
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $equipement = $this->plan->equipementable;
        $url = $this->equipementUrl($equipement) ?? url('/dashboard');

        return (new MailMessage)
            ->subject('Plan de maintenance en retard — '.$this->plan->operation)
            ->line('Le plan de maintenance préventive suivant est en retard :')
            ->line('« '.$this->plan->operation.' » sur '.$this->equipementLabel($equipement))
            ->action('Voir l\'équipement', $url)
            ->line('Merci de planifier l\'intervention correspondante dès que possible.');
    }

    private function equipementLabel(mixed $equipement): string
    {
        if (!$equipement) {
            return 'équipement supprimé';
        }

        return $equipement->immatriculation ?? $equipement->designation ?? '—';
    }

    private function equipementUrl(mixed $equipement, bool $absolute = true): ?string
    {
        if (!$equipement) {
            return null;
        }

        $routeName = match ($equipement::class) {
            EquipementIndustriel::class => 'equipements-industriels.show',
            Vehicule::class => 'vehicules.show',
            EquipementBureau::class => 'equipements-bureau.show',
            default => null,
        };

        return $routeName ? route($routeName, $equipement->id, $absolute) : null;
    }
}
