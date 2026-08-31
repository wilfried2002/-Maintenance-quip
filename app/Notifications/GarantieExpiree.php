<?php

namespace App\Notifications;

use App\Models\EquipementBureau;
use App\Models\EquipementIndustriel;
use App\Models\Vehicule;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Alerte d'expiration de garantie : déclenchée par alertes:generer pour tout
 * équipement dont la garantie expire bientôt (seuil 30 jours) ou est déjà
 * expirée — date_fin_garantie et criticite étaient saisies mais jamais
 * exploitées auparavant.
 */
class GarantieExpiree extends Notification
{
    use Queueable;

    public function __construct(
        public object $equipement,
        public \Illuminate\Support\Carbon $dateFinGarantie,
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'kind' => 'garantie',
            // Clé utilisée par GenererAlertes::dejaAlerte / resoudreAlertesObsoletes.
            'garantie_equipement_id' => $this->equipement->id,
            'title' => 'Garantie '.$this->verbe(),
            'body' => $this->equipementLabel().' — garantie '.$this->verbe().' le '.$this->dateFinGarantie->format('d/m/Y'),
            'url' => $this->equipementUrl($this->equipement, absolute: false),
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Garantie '.$this->verbe().' — '.$this->equipementLabel())
            ->line('La garantie de l\'équipement suivant '.$this->verbe().' le '.$this->dateFinGarantie->format('d/m/Y').' :')
            ->line($this->equipementLabel())
            ->action('Voir l\'équipement', $this->equipementUrl($this->equipement) ?? url('/dashboard'))
            ->line('Anticipez le renouvellement ou l\'externalisation de la maintenance.');
    }

    private function verbe(): string
    {
        return $this->dateFinGarantie->isPast() ? 'expirée' : 'expire bientôt';
    }

    private function equipementLabel(): string
    {
        return $this->equipement->immatriculation
            ?? $this->equipement->designation
            ?? '—';
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
