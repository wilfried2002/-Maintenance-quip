<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\HandlesPagination;
use App\Models\DemandeIntervention;
use App\Models\EquipementBureau;
use App\Models\EquipementIndustriel;
use App\Models\Intervention;
use App\Models\User;
use App\Models\Vehicule;
use App\Notifications\DemandeInterventionSoumise;
use App\Services\RoleService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Demandes d'intervention des utilisateurs finaux + workflow de validation :
 * soumise → approuvee/refusee (responsables du module) → convertie en
 * intervention planifiée. Comprend la vue calendrier/planning.
 */
class DemandeInterventionController extends Controller
{
    use HandlesPagination;

    private const CLASSES_PAR_MODULE = [
        'parc_automobile' => Vehicule::class,
        'equipements_industriels' => EquipementIndustriel::class,
        'equipements_bureau' => EquipementBureau::class,
    ];

    private const LABELS_MODULE = [
        'parc_automobile' => 'Parc automobile',
        'equipements_industriels' => 'Équipements industriels',
        'equipement_bureau' => 'Équipements de bureau',
    ];

    // ── Côté demandeur ──────────────────────────────────────────────────────

    public function mesDemandes(): Response
    {
        return Inertia::render('Demandes/Mes', [
            'demandes' => DemandeIntervention::query()
                ->where('demandeur_id', Auth::id())
                ->with('intervention:id,titre,statut,date_planifiee')
                ->latest()
                ->limit(100)
                ->get()
                ->map(fn (DemandeIntervention $demande) => $this->versTableau($demande)),
            'equipements' => $this->equipementsParModule(),
            'modules' => self::LABELS_MODULE,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'module' => ['required', 'string', 'in:'.implode(',', array_keys(self::CLASSES_PAR_MODULE))],
            'equipementable_id' => ['nullable', 'integer'],
            'titre' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'priorite' => ['nullable', 'in:basse,normale,haute,critique'],
        ]);

        $classe = self::CLASSES_PAR_MODULE[$data['module']];

        // L'équipement doit exister dans l'organisation courante (scope global).
        if (!empty($data['equipementable_id']) && !$classe::whereKey($data['equipementable_id'])->exists()) {
            return back()->withErrors(['equipementable_id' => 'Équipement introuvable.']);
        }

        $demande = DemandeIntervention::create([
            'module' => $data['module'],
            'equipementable_type' => !empty($data['equipementable_id']) ? $classe : null,
            'equipementable_id' => $data['equipementable_id'] ?? null,
            'titre' => $data['titre'],
            'description' => $data['description'] ?? null,
            'priorite' => $data['priorite'] ?? 'normale',
            'statut' => 'soumise',
            'demandeur_id' => Auth::id(),
        ]);

        // Préviens tous les acteurs du module concerné (le demandeur excepté).
        RoleService::usersWithModuleAccess($data['module'])
            ->reject(fn (User $destinataire) => $destinataire->id === Auth::id())
            ->each(fn (User $destinataire) => $destinataire->notify(new DemandeInterventionSoumise($demande)));

        return back()->with('status', 'Demande envoyée — les responsables du module sont notifiés.');
    }

    // ── Côté responsables du module ─────────────────────────────────────────

    public function index(Request $request): Response
    {
        [$tri, $sens, $parPage] = $this->parametresTri($request, ['created_at', 'titre', 'statut', 'priorite'], 'created_at');

        $recherche = $this->termeRecherche($request);

        $modules = RoleService::modulesAccessibles(Auth::user());

        $demandes = DemandeIntervention::query()
            ->whereIn('module', $modules !== [] ? $modules : ['__aucun__'])
            ->when($recherche !== '', fn ($q) => $q->where(fn ($w) => $w
                ->where('titre', 'like', "%{$recherche}%")
                ->orWhereHas('demandeur', fn ($u) => $u->where('name', 'like', "%{$recherche}%"))))
            ->with(['demandeur:id,name', 'intervention:id,titre,statut,date_planifiee', 'equipementable'])
            // Les demandes en attente d'abord, puis par date décroissante (le tri
            // applicatif prime sur le tri colonne volontairement demandé).
            ->orderByRaw("CASE WHEN statut = 'soumise' THEN 0 ELSE 1 END")
            ->orderBy($tri, $sens)
            ->paginate($parPage)
            ->withQueryString();

        $demandes->through(fn (DemandeIntervention $demande) => $this->versTableau($demande));

        return Inertia::render('Demandes/Index', [
            'demandes' => $demandes,
        ]);
    }

    public function decision(Request $request, DemandeIntervention $demande): RedirectResponse
    {
        $this->autoriserDecision($demande);

        $data = $request->validate([
            'action' => ['required', 'in:approuver,refuser'],
            'motif_decision' => ['nullable', 'string', 'max:2000'],
        ]);

        if ($data['action'] === 'refuser' && trim((string) ($data['motif_decision'] ?? '')) === '') {
            return back()->withErrors(['motif_decision' => 'Un motif est requis pour refuser une demande.']);
        }

        $demande->update([
            'statut' => $data['action'] === 'approuver' ? 'approuvee' : 'refusee',
            'decideur_id' => Auth::id(),
            'motif_decision' => $data['motif_decision'] ?? null,
            'decide_le' => now(),
        ]);

        return back()->with('status', $data['action'] === 'approuver' ? 'Demande approuvée.' : 'Demande refusée.');
    }

    /**
     * Convertit une demande approuvée en intervention planifiée. L'équipement
     * peut être précisé à ce moment si la demande n'en portait pas.
     */
    public function convertir(Request $request, DemandeIntervention $demande): RedirectResponse
    {
        $this->autoriserDecision($demande);

        abort_if($demande->statut !== 'approuvee', 422, 'Seule une demande approuvée peut être convertie.');

        $data = $request->validate([
            'equipementable_id' => ['nullable', 'integer'],
            'date_planifiee' => ['required', 'date'],
            'technicien_id' => ['nullable', 'integer', 'exists:users,id'],
        ]);

        $classe = self::CLASSES_PAR_MODULE[$demande->module];
        $equipementId = $data['equipementable_id'] ?? $demande->equipementable_id;

        if ($equipementId === null || !$classe::whereKey($equipementId)->exists()) {
            return back()->withErrors(['equipementable_id' => 'Choisissez un équipement valide pour planifier l\'intervention.']);
        }

        $intervention = Intervention::create([
            'equipementable_type' => $classe,
            'equipementable_id' => $equipementId,
            'type_intervention' => 'corrective',
            'statut' => 'planifiee',
            'priorite' => $demande->priorite,
            'date_planifiee' => $data['date_planifiee'],
            'technicien_id' => $data['technicien_id'] ?? null,
            'titre' => $demande->titre,
            'description' => $demande->description,
        ]);

        $demande->update(['statut' => 'convertie', 'intervention_id' => $intervention->id]);

        return back()->with('status', 'Intervention planifiée depuis la demande.');
    }

    // ── Calendrier ──────────────────────────────────────────────────────────

    public function calendrier(Request $request): Response
    {
        $mois = $request->query('mois');
        $debut = ($mois !== null && preg_match('/^\d{4}-\d{2}$/', (string) $mois))
            ? \Illuminate\Support\Carbon::parse($mois.'-01')->startOfMonth()
            : now()->startOfMonth();
        $fin = $debut->copy()->endOfMonth();

        $modules = RoleService::modulesAccessibles(Auth::user());
        $classes = array_values(array_intersect_key(self::CLASSES_PAR_MODULE, array_flip($modules)));

        $evenements = [];

        if ($classes !== []) {
            $interventions = Intervention::query()
                ->whereIn('equipementable_type', $classes)
                ->where(function ($q) use ($debut, $fin) {
                    $q->whereBetween('date_planifiee', [$debut, $fin])
                        ->orWhereBetween('date_debut', [$debut, $fin]);
                })
                ->with('equipementable')
                ->orderBy('date_planifiee')
                ->get();

            $statutCouleurs = [
                'planifiee' => 'bg-blue-600',
                'en_cours' => 'bg-yellow-500',
                'terminee' => 'bg-green-600',
                'annulee' => 'bg-gray-400',
            ];

            foreach ($interventions as $intervention) {
                $date = $intervention->date_debut ?? $intervention->date_planifiee;
                $evenements[] = [
                    'id' => $intervention->id,
                    'titre' => $intervention->titre,
                    'date' => $date?->toDateString(),
                    'statut' => $intervention->statut,
                    'couleur' => $statutCouleurs[$intervention->statut] ?? 'bg-blue-600',
                    'equipement' => $this->libelleEquipement($intervention->equipementable),
                    'module' => RoleService::modulePourClasseEquipement($intervention->equipementable_type),
                ];
            }
        }

        return Inertia::render('Calendrier/Index', [
            'evenements' => $evenements,
            'mois' => $debut->format('Y-m'),
            'modules' => collect(self::LABELS_MODULE)->only($modules)->all(),
        ]);
    }

    // ── Helpers ─────────────────────────────────────────────────────────────

    private function autoriserDecision(DemandeIntervention $demande): void
    {
        $module = $demande->module;

        abort_unless(
            Auth::user()?->hasModuleAccess($module),
            403,
            'Vous ne gérez pas le module concerné par cette demande.'
        );
    }

    private function equipementsParModule(): array
    {
        return [
            'parc_automobile' => Vehicule::orderBy('immatriculation')->get(['id', 'code', 'immatriculation'])->map(fn ($v) => ['id' => $v->id, 'label' => $v->code.' — '.$v->immatriculation]),
            'equipements_industriels' => EquipementIndustriel::orderBy('designation')->get(['id', 'code', 'designation'])->map(fn ($e) => ['id' => $e->id, 'label' => $e->code.' — '.$e->designation]),
            'equipement_bureau' => EquipementBureau::orderBy('designation')->get(['id', 'code', 'designation'])->map(fn ($e) => ['id' => $e->id, 'label' => $e->code.' — '.$e->designation]),
        ];
    }

    private function versTableau(DemandeIntervention $demande): array
    {
        return [
            'id' => $demande->id,
            'module' => $demande->module,
            'module_label' => self::LABELS_MODULE[$demande->module] ?? $demande->module,
            'equipement' => $this->libelleEquipement($demande->equipementable),
            'titre' => $demande->titre,
            'description' => $demande->description,
            'priorite' => $demande->priorite,
            'statut' => $demande->statut,
            'demandeur' => $demande->demandeur?->name,
            'decideur' => $demande->decideur?->name,
            'motif_decision' => $demande->motif_decision,
            'decide_le' => $demande->decide_le?->toIso8601String(),
            'created_at' => $demande->created_at?->toIso8601String(),
            'intervention' => $demande->intervention ? [
                'id' => $demande->intervention->id,
                'titre' => $demande->intervention->titre,
                'statut' => $demande->intervention->statut,
                'date_planifiee' => $demande->intervention->date_planifiee?->toIso8601String(),
            ] : null,
        ];
    }

    private function libelleEquipement(?object $equipement): ?string
    {
        if ($equipement === null) {
            return null;
        }

        return $equipement->immatriculation ?? $equipement->designation ?? $equipement->code ?? null;
    }
}
