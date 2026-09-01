<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganisation;
use App\Models\Concerns\ConsigneActivite;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

class PlanMaintenance extends Model
{
    use SoftDeletes, BelongsToOrganisation, ConsigneActivite;

    protected $table = 'plans_maintenance';

    protected $fillable = [
        'organisation_id',
        'equipementable_type',
        'equipementable_id',
        'operation',
        'type_frequence',
        'frequence_valeur',
        'derniere_execution_date',
        'derniere_execution_km',
        'actif',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'derniere_execution_date' => 'date',
            'frequence_valeur' => 'integer',
            'derniere_execution_km' => 'integer',
            'actif' => 'boolean',
        ];
    }

    public function equipementable(): MorphTo
    {
        return $this->morphTo();
    }

    public function interventions(): HasMany
    {
        return $this->hasMany(Intervention::class);
    }

    /**
     * Prochaine échéance calendaire (uniquement pertinent pour type_frequence = 'jours').
     * Base de calcul : dernière exécution, sinon date de création du plan.
     */
    public function getProchaineEcheanceAttribute(): ?Carbon
    {
        if ($this->type_frequence !== 'jours') {
            return null;
        }

        $base = $this->derniere_execution_date ?? $this->created_at;

        return $base?->copy()->addDays($this->frequence_valeur);
    }

    /**
     * En retard : échéance calendaire dépassée, ou (pour le kilométrage) kilométrage
     * actuel du véhicule ayant dépassé le seuil depuis la dernière exécution.
     */
    public function getEnRetardAttribute(): bool
    {
        if (!$this->actif) {
            return false;
        }

        if ($this->type_frequence === 'jours') {
            return $this->prochaine_echeance !== null && $this->prochaine_echeance->isPast();
        }

        if ($this->type_frequence === 'kilometres' && $this->equipementable instanceof Vehicule) {
            $kmDepuis = $this->equipementable->kilometrage_actuel - ($this->derniere_execution_km ?? 0);
            return $kmDepuis >= $this->frequence_valeur;
        }

        return false;
    }
}
