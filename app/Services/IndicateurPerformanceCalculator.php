<?php

namespace App\Services;

use App\Models\IndicateurPerformancePiece;
use App\Models\Piece;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class IndicateurPerformanceCalculator
{
    /**
     * Recalcule les indicateurs de performance de toutes les pièces ayant au moins
     * une consommation enregistrée, à partir des données réelles (intervention_pieces
     * + interventions) — pas de saisie manuelle.
     *
     * Passe par le modèle Piece (scopé par organisation via BelongsToOrganisation)
     * plutôt qu'une requête brute sur intervention_pieces : déclenché depuis l'UI
     * (bouton "Recalculer"), ça limite le calcul aux pièces de l'organisation courante ;
     * déclenché par la commande planifiée (contexte console, sans session), le scope est
     * inactif et toutes les organisations sont traitées — comportement voulu dans les
     * deux cas.
     *
     * @return int Nombre de pièces mises à jour.
     */
    public function calculerTout(): int
    {
        $pieceIds = Piece::whereHas('interventions')->pluck('id');

        foreach ($pieceIds as $pieceId) {
            $this->calculerPour($pieceId);
        }

        return $pieceIds->count();
    }

    private function calculerPour(int $pieceId): void
    {
        $consommations = DB::table('intervention_pieces')
            ->join('interventions', 'interventions.id', '=', 'intervention_pieces.intervention_id')
            ->where('intervention_pieces.piece_id', $pieceId)
            ->select([
                'intervention_pieces.quantite',
                'intervention_pieces.prix_unitaire',
                'interventions.type_intervention',
                'interventions.equipementable_type',
                'interventions.equipementable_id',
                DB::raw('COALESCE(interventions.date_fin, interventions.date_planifiee, interventions.created_at) as date_evenement'),
            ])
            ->get();

        if ($consommations->isEmpty()) {
            return;
        }

        $nombreRemplacements = (int) $consommations->sum('quantite');
        $coutTotal = (float) $consommations->sum(fn ($c) => $c->quantite * $c->prix_unitaire);
        $tauxDefaillance = $consommations->avg(fn ($c) => $c->type_intervention === 'corrective' ? 1 : 0);

        // Durée de vie moyenne : écart en jours entre remplacements successifs de la
        // MÊME pièce sur le MÊME équipement (mélanger plusieurs équipements fausserait
        // la mesure — l'usure d'une pièce sur un équipement est indépendante des autres).
        $ecarts = $consommations
            ->groupBy(fn ($c) => $c->equipementable_type . '#' . $c->equipementable_id)
            ->flatMap(function ($groupe) {
                $dates = $groupe->pluck('date_evenement')
                    ->map(fn ($d) => Carbon::parse($d))
                    ->sort()
                    ->values();

                $ecartsGroupe = [];
                for ($i = 1; $i < $dates->count(); $i++) {
                    $ecartsGroupe[] = $dates[$i - 1]->diffInDays($dates[$i]);
                }

                return $ecartsGroupe;
            });

        $dureeVieMoyenne = $ecarts->isNotEmpty() ? $ecarts->avg() : null;

        IndicateurPerformancePiece::updateOrCreate(
            [
                'piece_id' => $pieceId,
                'equipementable_type' => null,
                'equipementable_id' => null,
            ],
            [
                'organisation_id' => Piece::find($pieceId)?->organisation_id,
                'nombre_remplacements' => $nombreRemplacements,
                'duree_vie_moyenne_jours' => $dureeVieMoyenne,
                'taux_defaillance' => $tauxDefaillance,
                'cout_total_remplacement' => $coutTotal,
                'derniere_maj' => now()->toDateString(),
            ]
        );
    }
}
