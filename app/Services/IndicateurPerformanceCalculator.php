<?php

namespace App\Services;

use App\Models\IndicateurPerformancePiece;
use App\Models\Intervention;
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
     * Indicateurs calculés :
     * - nombre_remplacements : nombre total de pièces consommées
     * - duree_vie_moyenne_jours : écart moyen entre remplacements sur le MÊME équipement
     * - mtbf_heures : Mean Time Between Failures = durée moyenne de service avant panne
     * - taux_defaillance : % de remplacements lors d'interventions correctives (panne)
     * - cout_total_remplacement : coût total des remplacements
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

    /**
     * Calcule les indicateurs pour une pièce spécifique.
     */
    private function calculerPour(int $pieceId): void
    {
        $consommations = DB::table('intervention_pieces')
            ->join('interventions', 'interventions.id', '=', 'intervention_pieces.intervention_id')
            ->where('intervention_pieces.piece_id', $pieceId)
            ->select([
                'intervention_pieces.quantite',
                'intervention_pieces.prix_unitaire',
                'interventions.id as intervention_id',
                'interventions.type_intervention',
                'interventions.equipementable_type',
                'interventions.equipementable_id',
                'interventions.duree_heures',
                'interventions.statut',
                DB::raw('COALESCE(interventions.date_fin, interventions.date_planifiee, interventions.created_at) as date_evenement'),
            ])
            ->orderBy('date_evenement')
            ->get();

        if ($consommations->isEmpty()) {
            return;
        }

        $nombreRemplacements = (int) $consommations->sum('quantite');
        $coutTotal = (float) $consommations->sum(fn ($c) => $c->quantite * $c->prix_unitaire);

        // Taux de défaillance = % de remplacements lors d'interventions correctives
        // Interventions correctives = pannes, interventions préventives = maintenance planifiée
        $replacementsCorrectifs = $consommations
            ->where('type_intervention', 'corrective')
            ->sum('quantite');
        $tauxDefaillance = $nombreRemplacements > 0 
            ? $replacementsCorrectifs / $nombreRemplacements 
            : 0;

        // Durée de vie moyenne : écart en jours entre remplacements successifs de la
        // MÊME pièce sur le MÊME équipement
        $dureeVieMoyenne = $this->calculerDureeVieMoyenne($consommations);

        // MTBF (Mean Time Between Failures) en heures
        // = durée moyenne du temps de service (heures d'utilisation) entre deux pannes
        $mtbf = $this->calculerMTBF($consommations);

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
                'mtbf_heures' => $mtbf,
                'taux_defaillance' => $tauxDefaillance,
                'cout_total_remplacement' => $coutTotal,
                'derniere_maj' => now()->toDateString(),
            ]
        );
    }

    /**
     * Calcule la durée de vie moyenne en jours = écart moyen entre remplacements
     * successifs sur le MÊME équipement.
     */
    private function calculerDureeVieMoyenne($consommations): ?float
    {
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

        return $ecarts->isNotEmpty() ? $ecarts->avg() : null;
    }

    /**
     * Calcule le MTBF (Mean Time Between Failures) en heures.
     * 
     * MTBF = durée moyenne du service (en heures) entre deux défaillances (interventions correctives).
     * 
     * Algorithme :
     * 1. Grouper les consommations par équipement
     * 2. Pour chaque équipement, identifier les dates de remplacement
     * 3. Entre chaque remplacement consécutif, sommer les heures de service (duree_heures des interventions)
     * 4. Faire la moyenne de ces périodes
     */
    private function calculerMTBF($consommations): ?float
    {
        // Grouper par équipement
        $parEquipement = $consommations
            ->groupBy(fn ($c) => $c->equipementable_type . '#' . $c->equipementable_id);

        $periodesService = [];

        foreach ($parEquipement as $groupe) {
            // Trier par date
            $groupe = $groupe->sortBy('date_evenement')->values();

            // Récupérer toutes les interventions et durations pour cet équipement
            $interventionIds = $groupe->pluck('intervention_id')->unique();
            
            $interventions = Intervention::whereIn('id', $interventionIds)
                ->select('id', 'date_end', 'duree_heures')
                ->get()
                ->keyBy('id');

            // Entre chaque remplacement, cumuler les heures de service
            for ($i = 1; $i < $groupe->count(); $i++) {
                $dateDebut = Carbon::parse($groupe[$i - 1]->date_evenement);
                $dateFin = Carbon::parse($groupe[$i]->date_evenement);

                // Cumuler les heures des interventions entre ces deux remplacements
                $heuresCumulees = $groupe
                    ->filter(function ($c) use ($dateDebut, $dateFin) {
                        $date = Carbon::parse($c->date_evenement);
                        return $date->isBetween($dateDebut, $dateFin);
                    })
                    ->sum('duree_heures');

                if ($heuresCumulees > 0) {
                    $periodesService[] = $heuresCumulees;
                }
            }
        }

        return !empty($periodesService) ? (float) (array_sum($periodesService) / count($periodesService)) : null;
    }
}

