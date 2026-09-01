<?php

namespace App\Http\Controllers;

use App\Models\EquipementBureau;
use App\Models\EquipementIndustriel;
use App\Models\Intervention;
use App\Models\Piece;
use App\Models\PlanMaintenance;
use App\Models\Vehicule;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Corbeille : les soft deletes existaient en base mais aucune interface ne
 * permettait de voir — encore moins restaurer — les éléments supprimés.
 * Réservée aux admins de l'organisation (voir routes).
 */
class CorbeilleController extends Controller
{
    /**
     * @var array<string, array{0: class-string, 1: string}>
     */
    private const TYPES = [
        'vehicules' => [Vehicule::class, 'immatriculation'],
        'equipements_industriels' => [EquipementIndustriel::class, 'designation'],
        'equipements_bureau' => [EquipementBureau::class, 'designation'],
        'interventions' => [Intervention::class, 'titre'],
        'plans' => [PlanMaintenance::class, 'operation'],
        'pieces' => [Piece::class, 'designation'],
    ];

    private const LABELS = [
        'vehicules' => 'Véhicules',
        'equipements_industriels' => 'Équipements industriels',
        'equipements_bureau' => 'Équipements de bureau',
        'interventions' => 'Interventions',
        'plans' => 'Plans de maintenance',
        'pieces' => 'Pièces',
    ];

    public function index(): Response
    {
        $corbeilles = [];

        foreach (self::TYPES as $type => [$classe, $colonneLibelle]) {
            // Qui a supprimé : dernière activité « suppression » pour ce type.
            $suppressions = \App\Models\Activite::query()
                ->where('action', 'suppression')
                ->where('sujet_type', $classe)
                ->orderByDesc('created_at')
                ->get()
                ->groupBy('sujet_id');

            $elements = $classe::onlyTrashed()
                ->orderByDesc('deleted_at')
                ->limit(50)
                ->get()
                ->map(function ($element) use ($colonneLibelle, $suppressions) {
                    $suppression = $suppressions->get($element->id)?->first();

                    return [
                        'id' => $element->id,
                        'libelle' => $element->{$colonneLibelle} ?? $element->code ?? '#'.$element->id,
                        'deleted_at' => $element->deleted_at?->toIso8601String(),
                        'supprime_par' => $suppression?->user?->name,
                    ];
                })
                ->values();

            if ($elements->isNotEmpty()) {
                $corbeilles[] = [
                    'type' => $type,
                    'label' => self::LABELS[$type],
                    'elements' => $elements,
                ];
            }
        }

        return Inertia::render('Corbeille/Index', ['corbeilles' => $corbeilles]);
    }

    public function restore(string $type, int $id): RedirectResponse
    {
        [$classe] = self::TYPES[$type] ?? abort(404);

        $element = $classe::onlyTrashed()->findOrFail($id);
        $element->restore(); // journalisé « restauration » par ConsigneActivite

        return back()->with('status', 'Élément restauré.');
    }

    public function destroy(string $type, int $id): RedirectResponse
    {
        [$classe] = self::TYPES[$type] ?? abort(404);

        $element = $classe::onlyTrashed()->findOrFail($id);
        $element->forceDelete();

        return back()->with('status', 'Élément supprimé définitivement.');
    }
}
