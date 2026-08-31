<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\HandlesCoutsEntretien;
use App\Http\Controllers\Concerns\HandlesDocuments;
use App\Http\Controllers\Concerns\HandlesEquipementStats;
use App\Http\Controllers\Concerns\HandlesPhotoUpload;
use App\Http\Controllers\Concerns\HandlesPieces;
use App\Http\Controllers\Concerns\HandlesPlansMaintenance;
use App\Models\Document;
use App\Models\EquipementBureau;
use App\Models\Fournisseur;
use App\Models\Intervention;
use App\Models\Piece;
use App\Models\PlanMaintenance;
use App\Notifications\InterventionAssignee;
use App\Services\ModuleDashboardService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class EquipementBureauController extends Controller
{
    use HandlesPhotoUpload, HandlesDocuments, HandlesPlansMaintenance, HandlesCoutsEntretien, HandlesEquipementStats, HandlesPieces;

    private const MODULE = 'equipement_bureau';

    public function index(ModuleDashboardService $service): Response
    {
        $equipements = EquipementBureau::orderBy('designation')->get();
        
        // Ensure photo_url is always present in serialized data for Inertia
        // This guarantees photos persist after page refresh
        $equipements->each(function ($equipement) {
            $equipement->photo_url = $equipement->getPhotoUrlAttribute();
        });

        return Inertia::render('EquipementsBureau/Index', [
            'equipements' => $equipements,
            'fournisseurs' => Fournisseur::orderBy('nom')->get(['id', 'nom']),
            'stats' => $service->calculer(EquipementBureau::class),
        ]);
    }

    public function show(EquipementBureau $equipementBureau): Response
    {
        $equipementBureau->load([
            'fournisseur',
            'documents.uploader',
            'interventions' => fn ($q) => $q->with('technicien')->latest('date_planifiee')->limit(10),
            'plansMaintenance' => fn ($q) => $q->where('actif', true),
        ]);
        $equipementBureau->plansMaintenance->append(['prochaine_echeance', 'en_retard']);

        return Inertia::render('EquipementsBureau/Show', [
            'equipement' => $equipementBureau,
            'stats' => $this->equipementStats($equipementBureau, EquipementBureau::class),
        ]);
    }

    public function dashboard(ModuleDashboardService $service): Response
    {
        return Inertia::render('EquipementsBureau/Dashboard', [
            'stats' => $service->calculer(EquipementBureau::class),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:50', 'unique:equipements_bureau,code'],
            'designation' => ['required', 'string', 'max:255'],
            'categorie' => ['required', 'in:informatique,mobilier,climatisation,electromenager,autre'],
            'marque' => ['nullable', 'string', 'max:255'],
            'modele' => ['nullable', 'string', 'max:255'],
            'numero_serie' => ['nullable', 'string', 'max:255'],
            'date_acquisition' => ['nullable', 'date'],
            'valeur_acquisition' => ['nullable', 'numeric'],
            'localisation' => ['nullable', 'string', 'max:255'],
            'service_affecte' => ['nullable', 'string', 'max:255'],
            'statut' => ['required', 'in:en_service,en_panne,en_maintenance,hors_service,reforme'],
            'criticite' => ['required', 'in:basse,moyenne,haute,critique'],
            'date_fin_garantie' => ['nullable', 'date'],
            'fournisseur_id' => ['nullable', 'exists:fournisseurs,id'],
            'photo' => $this->photoValidationRules(),
            'notes' => ['nullable', 'string'],
        ]);

        if ($request->hasFile('photo')) {
            $data['photo'] = $request->file('photo')->store('equipements-bureau', 'public');
        }

        EquipementBureau::create($data);

        return back()->with('status', 'Équipement enregistré.');
    }

    public function update(Request $request, EquipementBureau $equipementBureau): RedirectResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:50', 'unique:equipements_bureau,code,' . $equipementBureau->id],
            'designation' => ['required', 'string', 'max:255'],
            'categorie' => ['required', 'in:informatique,mobilier,climatisation,electromenager,autre'],
            'marque' => ['nullable', 'string', 'max:255'],
            'modele' => ['nullable', 'string', 'max:255'],
            'numero_serie' => ['nullable', 'string', 'max:255'],
            'date_acquisition' => ['nullable', 'date'],
            'valeur_acquisition' => ['nullable', 'numeric'],
            'localisation' => ['nullable', 'string', 'max:255'],
            'service_affecte' => ['nullable', 'string', 'max:255'],
            'statut' => ['required', 'in:en_service,en_panne,en_maintenance,hors_service,reforme'],
            'criticite' => ['required', 'in:basse,moyenne,haute,critique'],
            'date_fin_garantie' => ['nullable', 'date'],
            'fournisseur_id' => ['nullable', 'exists:fournisseurs,id'],
            'photo' => $this->photoValidationRules(),
            'notes' => ['nullable', 'string'],
        ]);

        $equipementBureau->fill($data);
        $this->replacePhoto($request, $equipementBureau, 'equipements-bureau');
        $equipementBureau->save();

        return back()->with('status', 'Équipement mis à jour.');
    }

    public function destroy(EquipementBureau $equipementBureau): RedirectResponse
    {
        $equipementBureau->delete();

        return back()->with('status', 'Équipement supprimé.');
    }

    public function documentsStore(Request $request, EquipementBureau $equipementBureau): RedirectResponse
    {
        $request->validate($this->documentsValidationRules());

        $count = $this->storeDocuments($request, $equipementBureau, 'documents/equipements-bureau');

        return back()->with('status', $count > 1 ? "$count documents ajoutés." : 'Document ajouté.');
    }

    public function documentsDestroy(EquipementBureau $equipementBureau, Document $document): RedirectResponse
    {
        $this->destroyDocument($document);

        return back()->with('status', 'Document supprimé.');
    }

    public function interventionsIndex(): Response
    {
        return Inertia::render('EquipementsBureau/Interventions', [
            'interventions' => Intervention::query()
                ->where('equipementable_type', EquipementBureau::class)
                ->with(['equipementable', 'technicien', 'pieces'])
                ->latest('date_planifiee')
                ->get(),
            'equipements' => EquipementBureau::orderBy('designation')->get(['id', 'code', 'designation']),
            'techniciens' => $this->organisationUsers(),
            'pieces' => $this->piecesForModule(self::MODULE),
        ]);
    }

    public function piecesIndex(): Response
    {
        return Inertia::render('EquipementsBureau/Pieces', [
            'pieces' => $this->piecesForModule(self::MODULE),
            'fournisseurs' => Fournisseur::orderBy('nom')->get(['id', 'nom']),
        ]);
    }

    public function piecesStore(Request $request): RedirectResponse
    {
        return $this->storePieceForModule($request, self::MODULE);
    }

    public function piecesUpdate(Request $request, Piece $piece): RedirectResponse
    {
        return $this->updatePieceForModule($request, $piece, self::MODULE);
    }

    public function piecesDestroy(Piece $piece): RedirectResponse
    {
        return $this->destroyPieceForModule($piece, self::MODULE);
    }

    public function interventionsStore(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'equipementable_id' => ['required', 'exists:equipements_bureau,id'],
            'type_intervention' => ['required', 'in:preventive,corrective,predictive'],
            'statut' => ['required', 'in:planifiee,en_cours,terminee,annulee'],
            'priorite' => ['required', 'in:basse,normale,haute,critique'],
            'date_planifiee' => ['nullable', 'date'],
            'date_debut' => ['nullable', 'date'],
            'date_fin' => ['nullable', 'date'],
            'technicien_id' => ['nullable', 'exists:users,id'],
            'titre' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'cout_main_oeuvre' => ['nullable', 'numeric'],
            'duree_heures' => ['nullable', 'numeric'],
            'notes' => ['nullable', 'string'],
        ]);

        $intervention = Intervention::create([
            ...$data,
            'equipementable_type' => EquipementBureau::class,
        ]);

        $this->recordCoutMainOeuvre($intervention);

        if ($intervention->technicien_id) {
            $intervention->load(['technicien', 'equipementable']);
            $intervention->technicien?->notify(new InterventionAssignee($intervention));
        }

        return back()->with('status', 'Intervention enregistrée.');
    }

    public function plansIndex(): Response
    {
        $plans = PlanMaintenance::query()
            ->where('equipementable_type', EquipementBureau::class)
            ->with('equipementable')
            ->orderBy('operation')
            ->get()
            ->append(['prochaine_echeance', 'en_retard']);

        return Inertia::render('EquipementsBureau/Plans', [
            'plans' => $plans,
            'equipements' => EquipementBureau::orderBy('designation')->get(['id', 'code', 'designation']),
        ]);
    }

    public function plansStore(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'equipementable_id' => ['required', 'exists:equipements_bureau,id'],
            ...$this->planValidationRules(),
        ]);

        PlanMaintenance::create([
            ...$data,
            'equipementable_type' => EquipementBureau::class,
        ]);

        return back()->with('status', 'Plan de maintenance enregistré.');
    }

    public function plansUpdate(Request $request, PlanMaintenance $plan): RedirectResponse
    {
        $data = $request->validate($this->planValidationRules());

        $plan->update($data);

        return back()->with('status', 'Plan de maintenance mis à jour.');
    }

    public function plansDestroy(PlanMaintenance $plan): RedirectResponse
    {
        $plan->delete();

        return back()->with('status', 'Plan de maintenance supprimé.');
    }

    public function plansMarkExecuted(PlanMaintenance $plan): RedirectResponse
    {
        $this->markPlanExecuted($plan);

        return back()->with('status', 'Exécution enregistrée, échéance réinitialisée.');
    }

    private function organisationUsers()
    {
        $organisation = Auth::user()->getCurrentOrganisation();

        return $organisation ? $organisation->users()->get(['users.id', 'users.name']) : collect();
    }
}
