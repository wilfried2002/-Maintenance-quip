<?php

namespace App\Http\Controllers;

use App\Models\CoutEntretien;
use App\Models\EquipementBureau;
use App\Models\EquipementIndustriel;
use App\Models\Vehicule;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CoutEntretienController extends Controller
{
    private const TYPES = [
        'industriel' => EquipementIndustriel::class,
        'vehicule' => Vehicule::class,
        'bureau' => EquipementBureau::class,
    ];

    public function index(): Response
    {
        $couts = CoutEntretien::with('equipementable')->orderByDesc('date')->get();

        // Le coût des pièces n'est pas dupliqué dans couts_entretien (voir
        // HandlesCoutsEntretien) : il est calculé à la volée depuis intervention_pieces,
        // seule source fiable, pour éviter tout risque de désynchronisation.
        $piecesParEquipement = DB::table('intervention_pieces')
            ->join('interventions', 'interventions.id', '=', 'intervention_pieces.intervention_id')
            ->where('interventions.organisation_id', session('current_organisation_id'))
            ->select(
                'interventions.equipementable_type',
                'interventions.equipementable_id',
                DB::raw('SUM(intervention_pieces.quantite * intervention_pieces.prix_unitaire) as total')
            )
            ->groupBy('interventions.equipementable_type', 'interventions.equipementable_id')
            ->get();

        $totalParType = [
            'main_oeuvre' => (float) $couts->where('type_cout', 'main_oeuvre')->sum('montant'),
            'pieces' => (float) $piecesParEquipement->sum('total'),
            'prestation_externe' => (float) $couts->where('type_cout', 'prestation_externe')->sum('montant'),
            'autre' => (float) $couts->where('type_cout', 'autre')->sum('montant'),
        ];

        $parEquipement = [];

        foreach ($couts as $cout) {
            if (!$cout->equipementable) {
                continue;
            }
            $key = $cout->equipementable_type . '#' . $cout->equipementable_id;
            $parEquipement[$key] ??= ['label' => $this->labelPourEquipement($cout->equipementable), 'total' => 0];
            $parEquipement[$key]['total'] += (float) $cout->montant;
        }

        foreach ($piecesParEquipement as $ligne) {
            $key = $ligne->equipementable_type . '#' . $ligne->equipementable_id;
            if (!isset($parEquipement[$key])) {
                $equip = $ligne->equipementable_type::find($ligne->equipementable_id);
                if (!$equip) {
                    continue;
                }
                $parEquipement[$key] = ['label' => $this->labelPourEquipement($equip), 'total' => 0];
            }
            $parEquipement[$key]['total'] += (float) $ligne->total;
        }

        $parEquipement = collect($parEquipement)->sortByDesc('total')->values();

        return Inertia::render('Couts/Index', [
            'couts' => $couts->values(),
            'totalParType' => $totalParType,
            'totalGeneral' => array_sum($totalParType),
            'parEquipement' => $parEquipement,
            'equipements' => [
                'industriel' => EquipementIndustriel::orderBy('designation')->get(['id', 'code', 'designation']),
                'vehicule' => Vehicule::orderBy('immatriculation')->get(['id', 'code', 'immatriculation']),
                'bureau' => EquipementBureau::orderBy('designation')->get(['id', 'code', 'designation']),
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'type_equipement' => ['required', 'in:industriel,vehicule,bureau'],
            'equipementable_id' => ['required', 'integer'],
            'type_cout' => ['required', 'in:prestation_externe,autre'],
            'montant' => ['required', 'numeric', 'min:0.01'],
            'date' => ['required', 'date'],
            'description' => ['nullable', 'string', 'max:255'],
        ]);

        $class = self::TYPES[$data['type_equipement']];

        if (!$class::whereKey($data['equipementable_id'])->exists()) {
            return back()->withErrors(['equipementable_id' => 'Équipement introuvable.']);
        }

        CoutEntretien::create([
            'equipementable_type' => $class,
            'equipementable_id' => $data['equipementable_id'],
            'type_cout' => $data['type_cout'],
            'montant' => $data['montant'],
            'date' => $data['date'],
            'description' => $data['description'] ?? null,
        ]);

        return back()->with('status', 'Coût enregistré.');
    }

    /**
     * Export CSV (ouvrable dans Excel) du journal des coûts + des lignes de pièces
     * consommées, qui elles ne figurent pas dans couts_entretien (voir index()).
     */
    public function export(): StreamedResponse
    {
        $couts = CoutEntretien::with('equipementable')->orderByDesc('date')->get();

        $piecesLignes = DB::table('intervention_pieces')
            ->join('interventions', 'interventions.id', '=', 'intervention_pieces.intervention_id')
            ->join('pieces', 'pieces.id', '=', 'intervention_pieces.piece_id')
            ->where('interventions.organisation_id', session('current_organisation_id'))
            ->select(
                'interventions.equipementable_type',
                'interventions.equipementable_id',
                'interventions.date_planifiee',
                'interventions.date_fin',
                'pieces.designation as piece_designation',
                'intervention_pieces.quantite',
                'intervention_pieces.prix_unitaire'
            )
            ->orderByDesc('interventions.date_planifiee')
            ->get();

        $filename = 'couts-entretien-'.now()->format('Y-m-d').'.csv';
        $devise = Auth::user()->getCurrentOrganisation()?->devise ?? 'XOF';

        return response()->streamDownload(function () use ($couts, $piecesLignes, $devise) {
            $handle = fopen('php://output', 'w');
            // BOM UTF-8 pour qu'Excel affiche correctement les accents.
            fwrite($handle, "\xEF\xBB\xBF");
            fputcsv($handle, ['Date', 'Équipement', 'Type', 'Description', "Montant ({$devise})"], ';');

            foreach ($couts as $cout) {
                fputcsv($handle, [
                    optional($cout->date)->format('d/m/Y'),
                    $cout->equipementable ? $this->labelPourEquipement($cout->equipementable) : '—',
                    $this->typeCoutLabel($cout->type_cout),
                    $cout->description ?? '',
                    number_format((float) $cout->montant, 2, ',', ''),
                ], ';');
            }

            foreach ($piecesLignes as $ligne) {
                $equip = $ligne->equipementable_type::find($ligne->equipementable_id);
                $date = $ligne->date_fin ?? $ligne->date_planifiee;

                fputcsv($handle, [
                    $date ? \Illuminate\Support\Carbon::parse($date)->format('d/m/Y') : '',
                    $equip ? $this->labelPourEquipement($equip) : '—',
                    'Pièces',
                    $ligne->piece_designation.' × '.$ligne->quantite,
                    number_format($ligne->quantite * $ligne->prix_unitaire, 2, ',', ''),
                ], ';');
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    private function typeCoutLabel(string $type): string
    {
        return match ($type) {
            'main_oeuvre' => "Main d'œuvre",
            'prestation_externe' => 'Prestation externe',
            'autre' => 'Autre',
            default => $type,
        };
    }

    public function destroy(CoutEntretien $cout): RedirectResponse
    {
        if ($cout->intervention_id) {
            return back()->withErrors([
                'cout' => "Ce coût provient d'une intervention : pour le modifier, agissez sur l'intervention elle-même.",
            ]);
        }

        $cout->delete();

        return back()->with('status', 'Coût supprimé.');
    }

    private function labelPourEquipement($equip): string
    {
        return match (get_class($equip)) {
            Vehicule::class => "{$equip->code} — {$equip->immatriculation}",
            default => "{$equip->code} — {$equip->designation}",
        };
    }
}
