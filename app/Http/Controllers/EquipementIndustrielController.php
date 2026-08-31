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
use App\Models\EquipementIndustriel;
use App\Models\Fournisseur;
use App\Services\ModuleDashboardService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Équipements industriels. Tout le comportement générique (dashboard,
 * interventions, plans de maintenance, stock de pièces) vit dans le trait
 * HandlesEquipementModule — ce contrôleur ne garde que la spécificité
 * industrielle (ligne de production, puissance, responsable...).
 */
class EquipementIndustrielController extends Controller
{
    use HandlesPhotoUpload, HandlesDocuments, HandlesPlansMaintenance, HandlesCoutsEntretien, HandlesEquipementStats, HandlesPieces, HandlesEquipementModule;

    private const MODULE = 'equipements_industriels';

    protected function equipementClasse(): string
    {
        return EquipementIndustriel::class;
    }

    protected function moduleKey(): string
    {
        return self::MODULE;
    }

    protected function viewDir(): string
    {
        return 'EquipementsIndustriels';
    }

    protected function equipementsPourSelect()
    {
        return EquipementIndustriel::orderBy('designation')->get(['id', 'code', 'designation']);
    }

    public function index(ModuleDashboardService $service): Response
    {
        $equipements = EquipementIndustriel::orderBy('designation')->get();

        // Ensure photo_url is always present in serialized data for Inertia
        // This guarantees photos persist after page refresh
        $equipements->each(function ($equipement) {
            $equipement->photo_url = $equipement->getPhotoUrlAttribute();
        });

        return Inertia::render('EquipementsIndustriels/Index', [
            'equipements' => $equipements,
            'fournisseurs' => Fournisseur::orderBy('nom')->get(['id', 'nom']),
            'stats' => $service->calculer(EquipementIndustriel::class),
        ]);
    }

    public function show(EquipementIndustriel $equipementIndustriel): Response
    {
        $equipementIndustriel->load([
            'responsable',
            'fournisseur',
            'documents.uploader',
            'interventions' => fn ($q) => $q->with('technicien')->latest('date_planifiee')->limit(10),
            'plansMaintenance' => fn ($q) => $q->where('actif', true),
        ]);
        $equipementIndustriel->plansMaintenance->append(['prochaine_echeance', 'en_retard']);

        return Inertia::render('EquipementsIndustriels/Show', [
            'equipement' => $equipementIndustriel,
            'stats' => $this->equipementStats($equipementIndustriel, EquipementIndustriel::class),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate($this->reglesValidation());

        if ($request->hasFile('photo')) {
            $data['photo'] = $request->file('photo')->store('equipements-industriels', 'local');
        }

        EquipementIndustriel::create($data);

        return back()->with('status', 'Équipement enregistré.');
    }

    public function update(Request $request, EquipementIndustriel $equipementIndustriel): RedirectResponse
    {
        $data = $request->validate($this->reglesValidation($equipementIndustriel->id));

        $equipementIndustriel->fill($data);
        $this->replacePhoto($request, $equipementIndustriel, 'equipements-industriels');
        $equipementIndustriel->save();

        return back()->with('status', 'Équipement mis à jour.');
    }

    public function destroy(EquipementIndustriel $equipementIndustriel): RedirectResponse
    {
        $equipementIndustriel->delete();

        return back()->with('status', 'Équipement supprimé.');
    }

    public function documentsStore(Request $request, EquipementIndustriel $equipementIndustriel): RedirectResponse
    {
        $request->validate($this->documentsValidationRules());

        $count = $this->storeDocuments($request, $equipementIndustriel, 'documents/equipements-industriels');

        return back()->with('status', $count > 1 ? "$count documents ajoutés." : 'Document ajouté.');
    }

    public function documentsDestroy(EquipementIndustriel $equipementIndustriel, Document $document): RedirectResponse
    {
        $this->destroyDocument($document, $equipementIndustriel);

        return back()->with('status', 'Document supprimé.');
    }

    /**
     * Règles communes création/édition : le unique sur le code ignore l'enregistrement
     * en cours d'édition ($id null à la création).
     */
    private function reglesValidation(?int $id = null): array
    {
        return [
            'code' => ['required', 'string', 'max:50', 'unique:equipements_industriels,code' . ($id ? ",{$id}" : '')],
            'designation' => ['required', 'string', 'max:255'],
            'categorie' => ['nullable', 'string', 'max:255'],
            'marque' => ['nullable', 'string', 'max:255'],
            'modele' => ['nullable', 'string', 'max:255'],
            'numero_serie' => ['nullable', 'string', 'max:255'],
            'ligne_production' => ['nullable', 'string', 'max:255'],
            'puissance_kw' => ['nullable', 'numeric'],
            'date_mise_service' => ['nullable', 'date'],
            'date_acquisition' => ['nullable', 'date'],
            'valeur_acquisition' => ['nullable', 'numeric'],
            'localisation' => ['nullable', 'string', 'max:255'],
            'statut' => ['required', 'in:en_service,en_panne,en_maintenance,hors_service,reforme'],
            'criticite' => ['required', 'in:basse,moyenne,haute,critique'],
            'date_fin_garantie' => ['nullable', 'date'],
            'fournisseur_id' => ['nullable', 'exists:fournisseurs,id'],
            'photo' => $this->photoValidationRules(),
            'notes' => ['nullable', 'string'],
        ];
    }
}
