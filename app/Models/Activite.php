<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganisation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Facades\Auth;

/**
 * Ligne du journal d'activité (qui a fait quoi, quand, sur quel objet).
 * Alimentée automatiquement par le trait ConsigneActivite posé sur les modèles
 * métier — aucune écriture manuelle nécessaire côté contrôleurs.
 */
class Activite extends Model
{
    use BelongsToOrganisation;

    public const ACTIONS = ['creation', 'modification', 'suppression', 'restauration'];

    protected $fillable = [
        'organisation_id',
        'user_id',
        'sujet_type',
        'sujet_id',
        'action',
        'changements',
    ];

    protected function casts(): array
    {
        return [
            'changements' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function sujet(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Enregistre une ligne d'activité pour un modèle. Ne doit jamais faire échouer
     * l'action métier : toute erreur d'audit est silencieuse (et restera visible
     * en logs via le rapport d'exception global).
     */
    public static function consigner(object $modele, string $action, ?array $changements = null): void
    {
        try {
            $sujetId = $modele instanceof Model ? $modele->getKey() : null;

            self::create([
                'user_id' => Auth::id(),
                'sujet_type' => $modele instanceof Model ? $modele->getMorphClass() : $modele::class,
                'sujet_id' => $sujetId,
                'action' => $action,
                'changements' => $changements,
            ]);
        } catch (\Throwable) {
            // Volontairement silencieux : voir docblock.
        }
    }
}
