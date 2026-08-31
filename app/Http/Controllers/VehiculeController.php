<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\HandlesCoutsEntretien;
use App\Http\Controllers\Concerns\HandlesDocuments;
use App\Http\Controllers\Concerns\HandlesEquipementModule;
use App\Http\Controllers\Concerns\HandlesEquipementStats;
use App\Http\Controllers\Concerns\HandlesPhotoUpload;
use App\Http\Controllers\Concerns\HandlesPieces;
use App\Http\Controllers\Concerns\HandlesPlansMaintenance;
use App\Models\Document;
use App\Models\Fournisseur;
use App\Models\Vehicule;
use App\Services\ModuleDashboardService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Parc automobile. Tout le comportement générique (dashboard, interventions, plans
 * de maintenance, stock de pièces) vit dans le trait HandlesEquipementModule —
 * ce contrôleur ne garde que la spécificité des véhicules (immatriculation,
 * chauffeur, kilométrage...).
 */
class VehiculeController extends Controller
{
    use HandlesPhotoUpload, HandlesDocuments, HandlesPlansMaintenance, HandlesCoutsEntretien, HandlesEquipementStats, HandlesPieces, HandlesEquipementModule;

    private const MODULE = 'parc_automobile';

    protected function equipementClasse(): string
    {
        return Vehicule::class;
    }

    protected function moduleKey(): string
    {
        return self::MODULE;
    }

    protected function viewDir(): string
    {
        return 'Vehicules';
    }

    protected function equipementsPourSelect()
    {
        return Vehicule::orderBy('immatriculation')->get(['id', 'code', 'immatriculation']);
    }

    public function index(Request $request, ModuleDashboardService $service): Response
    {
        [$tri, $sens, $parPage] = $this->parametresTri(
            $request,
            ['code', 'immatriculation', 'marque', 'statut', 'criticite'],
            'immatriculation',
            'asc'
        );

        $recherche = $this->termeRecherche($request);

        $vehicules = Vehicule::with('chauffeur:id,name')
            ->when($recherche !== '', fn ($q) => $q->where(fn ($w) => $w
                ->where('code', 'like', "%{$recherche}%")
                ->orWhere('immatriculation', 'like', "%{$recherche}%")
                ->orWhere('marque', 'like', "%{$recherche}%")))
            ->orderBy($tri, $sens)
            ->paginate($parPage)
            ->withQueryString();

        // photo_url toujours présent dans les données sérialisées pour Inertia
        // (garantit l'affichage des photos après rafraîchissement).
        $vehicules->through(function ($vehicule) {
            $vehicule->photo_url = $vehicule->getPhotoUrlAttribute();

            return $vehicule;
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
            'releves.utilisateur:id,name',
        ]);
        $vehicule->plansMaintenance->append(['prochaine_echeance', 'en_retard']);

        return Inertia::render('Vehicules/Show', [
            'vehicule' => $vehicule,
            'releves' => $vehicule->releves->take(30)->values(),
            'stats' => $this->equipementStats($vehicule, Vehicule::class),
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
            $data['photo'] = $request->file('photo')->store('vehicules', 'local');
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

        $ancienKilometrage = (int) $vehicule->getOriginal('kilometrage_actuel');
        $vehicule->save();

        // Toute hausse du compteur via la fiche alimente l'historique des relevés
        // (10/10) — le compteur ne doit plus jamais bouger sans trace.
        if ((int) $vehicule->kilometrage_actuel > $ancienKilometrage) {
            \App\Models\ReleveKilometrique::create([
                'vehicule_id' => $vehicule->id,
                'kilometrage' => (int) $vehicule->kilometrage_actuel,
                'date_releve' => now()->toDateString(),
                'source' => 'edition_vehicule',
                'user_id' => Auth::id(),
                'note' => 'Compteur mis à jour depuis la fiche véhicule',
            ]);
        }

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
}
