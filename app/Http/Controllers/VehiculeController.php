<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\HandlesCoutsEntretien;
use App\Http\Controllers\Concerns\HandlesDocuments;
use App\Http\Controllers\Concerns\HandlesEquipementStats;
use App\Http\Controllers\Concerns\HandlesPhotoUpload;
use App\Http\Controllers\Concerns\HandlesPieces;
use App\Http\Controllers\Concerns\HandlesPlansMaintenance;
use App\Models\Document;
use App\Models\Fournisseur;
use App\Models\Intervention;
use App\Models\Piece;
use App\Models\PlanMaintenance;
use App\Models\Vehicule;
use App\Services\ModuleDashboardService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class VehiculeController extends Controller
{
    use HandlesPhotoUpload, HandlesDocuments, HandlesPlansMaintenance, HandlesCoutsEntretien, HandlesEquipementStats, HandlesPieces;

    private const MODULE = 'parc_automobile';

    public function index(ModuleDashboardService $service): Response
    {
        $vehicules = Vehicule::with('chauffeur:id,name')->orderBy('immatriculation')->get();
        
        // Ensure photo_url is always present in serialized data for Inertia
        // This guarantees photos persist after page refresh
        $vehicules->each(function ($vehicule) {
            $vehicule->photo_url = $vehicule->getPhotoUrlAttribute();
        });

        return Inertia::render('Vehicules/Index', [
            'vehicules' => $vehicules,
            'chauffeurs' => $this->organisationUsers(),
            'fournisseurs' => Fournisseur::orderBy('nom')->get(['id', 'nom']),
            'stats' => $service->calculer(Vehicule::class),
        ]);
    }

    public function show(Vehicule $vehicule): Response
    {
        $vehicule->load([
            'chauffeur',
            'fournisseur',
            'documents.uploader',
            'interventions' => fn ($q) => $q->with('technicien')->latest('date_planifiee')->limit(10),
            'plansMaintenance' => fn ($q) => $q->where('actif', true),
        ]);
        $vehicule->plansMaintenance->append(['prochaine_echeance', 'en_retard']);

        return Inertia::render('Vehicules/Show', [
            'vehicule' => $vehicule,
            'stats' => $this->equipementStats($vehicule, Vehicule::class),
        ]);
    }

    public function dashboard(ModuleDashboardService $service): Response
    {
        return Inertia::render('Vehicules/Dashboard', [
            'stats' => $service->calculer(Vehicule::class),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:50', 'unique:vehicules,code'],
            'immatriculation' => ['required', 'string', 'max:50', 'unique:vehicules,immatriculation'],
            'marque' => ['nullable', 'string', 'max:255'],
            'modele' => ['nullable', 'string', 'max:255'],
            'type_vehicule' => ['required', 'in:vl,pl,utilitaire,engin,moto'],
            'type_carburant' => ['nullable', 'string', 'max:255'],
            'date_mise_circulation' => ['nullable', 'date'],
            'date_acquisition' => ['nullable', 'date'],
            'valeur_acquisition' => ['nullable', 'numeric'],
            'kilometrage_actuel' => ['nullable', 'integer', 'min:0'],
            'chauffeur_id' => ['nullable', 'exists:users,id'],
            'statut' => ['required', 'in:en_service,en_panne,en_maintenance,hors_service,reforme'],
            'criticite' => ['required', 'in:basse,moyenne,haute,critique'],
            'date_fin_garantie' => ['nullable', 'date'],
            'fournisseur_id' => ['nullable', 'exists:fournisseurs,id'],
            'photo' => $this->photoValidationRules(),
            'notes' => ['nullable', 'string'],
        ]);

        if ($request->hasFile('photo')) {
            $data['photo'] = $request->file('photo')->store('vehicules', 'public');
        }

        Vehicule::create($data);

        return back()->with('status', 'Véhicule enregistré.');
    }

    public function update(Request $request, Vehicule $vehicule): RedirectResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:50', 'unique:vehicules,code,' . $vehicule->id],
            'immatriculation' => ['required', 'string', 'max:50', 'unique:vehicules,immatriculation,' . $vehicule->id],
            'marque' => ['nullable', 'string', 'max:255'],
            'modele' => ['nullable', 'string', 'max:255'],
            'type_vehicule' => ['required', 'in:vl,pl,utilitaire,engin,moto'],
            'type_carburant' => ['nullable', 'string', 'max:255'],
            'date_mise_circulation' => ['nullable', 'date'],
            'date_acquisition' => ['nullable', 'date'],
            'valeur_acquisition' => ['nullable', 'numeric'],
            'kilometrage_actuel' => ['nullable', 'integer', 'min:0'],
            'chauffeur_id' => ['nullable', 'exists:users,id'],
            'statut' => ['required', 'in:en_service,en_panne,en_maintenance,hors_service,reforme'],
            'criticite' => ['required', 'in:basse,moyenne,haute,critique'],
            'date_fin_garantie' => ['nullable', 'date'],
            'fournisseur_id' => ['nullable', 'exists:fournisseurs,id'],
            'photo' => $this->photoValidationRules(),
            'notes' => ['nullable', 'string'],
        ]);

        $vehicule->fill($data);
        $this->replacePhoto($request, $vehicule, 'vehicules');
        $vehicule->save();

        return back()->with('status', 'Véhicule mis à jour.');
    }

    public function destroy(Vehicule $vehicule): RedirectResponse
    {
        $vehicule->delete();

        return back()->with('status', 'Véhicule supprimé.');
    }

    public function documentsStore(Request $request, Vehicule $vehicule): RedirectResponse
    {
        $request->validate($this->documentsValidationRules());

        $count = $this->storeDocuments($request, $vehicule, 'documents/vehicules');

        return back()->with('status', $count > 1 ? "$count documents ajoutés." : 'Document ajouté.');
    }

    public function documentsDestroy(Vehicule $vehicule, Document $document): RedirectResponse
    {
        $this->destroyDocument($document, $vehicule);

        return back()->with('status', 'Document supprimé.');
    }

    public function interventionsIndex(): Response
    {
        return Inertia::render('Vehicules/Interventions', [
            'interventions' => Intervention::query()
                ->where('equipementable_type', Vehicule::class)
                ->with(['equipementable', 'technicien', 'pieces'])
                ->latest('date_planifiee')
                ->get(),
            'equipements' => Vehicule::orderBy('immatriculation')->get(['id', 'code', 'immatriculation']),
            'techniciens' => $this->organisationUsers(),
            'pieces' => $this->piecesForModule(self::MODULE),
        ]);
    }

    public function piecesIndex(): Response
    {
        return Inertia::render('Vehicules/Pieces', [
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
            'equipementable_id' => ['required', 'exists:vehicules,id'],
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
            'equipementable_type' => Vehicule::class,
        ]);

        $this->recordCoutMainOeuvre($intervention);

        return back()->with('status', 'Intervention enregistrée.');
    }

    public function plansIndex(): Response
    {
        $plans = PlanMaintenance::query()
            ->where('equipementable_type', Vehicule::class)
            ->with('equipementable')
            ->orderBy('operation')
            ->get()
            ->append(['prochaine_echeance', 'en_retard']);

        return Inertia::render('Vehicules/Plans', [
            'plans' => $plans,
            'equipements' => Vehicule::orderBy('immatriculation')->get(['id', 'code', 'immatriculation']),
        ]);
    }

    public function plansStore(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'equipementable_id' => ['required', 'exists:vehicules,id'],
            ...$this->planValidationRules(),
        ]);

        PlanMaintenance::create([
            ...$data,
            'equipementable_type' => Vehicule::class,
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
