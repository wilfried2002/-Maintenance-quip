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
        $pieces = Piece::whereHas('interventions')->get();

        foreach ($pieces as $piece) {
            $this->recalculerPiece($piece);
        }

        return $pieces->count();
    }

    /** Met à jour une pièce dès qu'une consommation est modifiée. */
    public function recalculerPiece(Piece $piece): void
    {
        if (!$piece->interventions()->exists()) {
            IndicateurPerformancePiece::where('piece_id', $piece->id)
                ->whereNull('equipementable_type')
                ->delete();

            return;
        }

        $this->calculerPour($piece);
    }

    private function calculerPour(Piece $piece): void
    {
        $consommations = DB::table('intervention_pieces')
            ->join('interventions', 'interventions.id', '=', 'intervention_pieces.intervention_id')
            ->where('intervention_pieces.piece_id', $piece->id)
            ->select([
                'intervention_pieces.quantite',
                'intervention_pieces.prix_unitaire',
                'interventions.type_intervention',
                'interventions.equipementable_type',
                'interventions.equipementable_id',
                'interventions.date_debut',
                'interventions.date_fin',
                DB::raw('COALESCE(interventions.date_fin, interventions.date_planifiee, interventions.created_at) as date_evenement'),
            ])
            ->get();

        if ($consommations->isEmpty()) {
            return;
        }

        $nombreRemplacements = (int) $consommations->sum('quantite');
        $coutTotal = (float) $consommations->sum(fn ($c) => $c->quantite * $c->prix_unitaire);
        // Le taux porte sur les pièces réellement remplacées : une ligne de
        // consommation de 4 pièces doit peser quatre fois plus qu'une ligne d'une pièce.
        $remplacementsCorrectifs = (int) $consommations
            ->where('type_intervention', 'corrective')
            ->sum('quantite');
        $tauxDefaillance = $nombreRemplacements > 0
            ? $remplacementsCorrectifs / $nombreRemplacements
            : null;

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

        // MTBF (temps moyen entre défaillances, en heures) : écart moyen entre les
        // événements CORRECTIFS consécutifs de la pièce sur le MÊME équipement —
        // mélanger les équipements fausserait la mesure (même principe que $ecarts).
        $ecartsCorrectifs = $consommations
            ->where('type_intervention', 'corrective')
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

        $mtbfHeures = $ecartsCorrectifs->isNotEmpty() ? round($ecartsCorrectifs->avg() * 24, 2) : null;

        // MTTR (temps moyen de réparation, en heures) : durée moyenne entre début et
        // fin des interventions CORRECTIVES ayant consommé la pièce.
        $mttrHeures = $consommations
            ->where('type_intervention', 'corrective')
            ->filter(fn ($c) => $c->date_debut && $c->date_fin)
            ->map(fn ($c) => Carbon::parse($c->date_debut)->floatDiffInHours(Carbon::parse($c->date_fin)))
            ->filter(fn ($heures) => $heures > 0)
            ->pipe(fn ($durees) => $durees->isNotEmpty() ? round($durees->avg(), 2) : null);

        IndicateurPerformancePiece::updateOrCreate(
            [
                'piece_id' => $piece->id,
                'equipementable_type' => null,
                'equipementable_id' => null,
            ],
            [
                'organisation_id' => $piece->organisation_id,
                'nombre_remplacements' => $nombreRemplacements,
                'duree_vie_moyenne_jours' => $dureeVieMoyenne,
                'mtbf_heures' => $mtbfHeures,
                'mttr_heures' => $mttrHeures,
                'taux_defaillance' => $tauxDefaillance,
                'cout_total_remplacement' => $coutTotal,
                'derniere_maj' => now()->toDateString(),
            ]
        );
    }
}
