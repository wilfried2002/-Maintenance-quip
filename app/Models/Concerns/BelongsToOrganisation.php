<?php

namespace App\Models\Concerns;

use App\Models\Organisation;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Cloisonnement SaaS : chaque organisation ne doit voir/manipuler que ses propres données.
 *
 * - En lecture : un scope global filtre automatiquement sur l'organisation courante
 *   (session('current_organisation_id'), posée au login pour tout le monde y compris le
 *   super admin — voir OrganisationSwitchController). Impossible d'oublier de filtrer une
 *   requête dans un contrôleur : le filtrage est appliqué au niveau du modèle. Ça bloque
 *   aussi les fuites par IDOR (deviner l'id d'un enregistrement d'une autre organisation
 *   dans l'URL) puisque findOrFail() passe par la même requête scopée.
 * - En écriture : organisation_id est renseigné automatiquement à la création si absent.
 * - Hors contexte HTTP (commandes artisan planifiées type alertes:generer) : aucune session
 *   active, donc aucun filtre n'est appliqué — ces jobs doivent volontairement parcourir
 *   TOUTES les organisations, pas une seule.
 */
trait BelongsToOrganisation
{
    protected static function bootBelongsToOrganisation(): void
    {
        static::addGlobalScope('organisation', function (Builder $builder) {
            $organisationId = self::currentOrganisationId();

            if ($organisationId !== null) {
                $builder->where($builder->getModel()->getTable().'.organisation_id', $organisationId);
            }
        });

        static::creating(function ($model) {
            if (!$model->organisation_id) {
                $model->organisation_id = self::currentOrganisationId();
            }
        });
    }

    public static function currentOrganisationId(): ?int
    {
        if (!app()->bound('session') || !app('session')->isStarted()) {
            return null;
        }

        return session('current_organisation_id');
    }

    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class);
    }
}
