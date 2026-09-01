<?php

namespace App\Models\Concerns;

use App\Models\Activite;
use Illuminate\Database\Eloquent\Model;

/**
 * Journalisation automatique du cycle de vie : création, modification (avec
 * diff avant/après), suppression et restauration — poser le trait sur un modèle
 * suffit, aucune écriture manuelle dans les contrôleurs.
 *
 * La ligne est rattachée à l'utilisateur authentifié et à l'organisation
 * courante (voir Activite::consigner + BelongsToOrganisation).
 */
trait ConsigneActivite
{
    public static function bootConsigneActivite(): void
    {
        static::created(fn (Model $modele) => Activite::consigner($modele, 'creation'));

        static::updated(fn (Model $modele) => Activite::consigner($modele, 'modification', self::diffChangements($modele)));

        static::deleted(fn (Model $modele) => Activite::consigner($modele, 'suppression'));

        // N'émet un événement que pour les modèles SoftDeletes — inoffensif sinon.
        static::restored(fn (Model $modele) => Activite::consigner($modele, 'restauration'));
    }

    /**
     * Diff des attributs réellement modifiés (hors colonnes techniques).
     *
     * @return array<string, array{avant: mixed, apres: mixed}>|null
     */
    private static function diffChangements(Model $modele): ?array
    {
        $ignorees = ['created_at', 'updated_at', 'deleted_at', 'remember_token'];

        $changements = [];

        foreach (array_keys($modele->getChanges()) as $attribut) {
            if (in_array($attribut, $ignorees, true)) {
                continue;
            }

            $changements[$attribut] = [
                'avant' => $modele->getOriginal($attribut),
                'apres' => $modele->getAttribute($attribut),
            ];
        }

        return $changements ?: null;
    }
}
