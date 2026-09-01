<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Fournisseur;
use App\Models\Intervention;
use App\Models\Piece;
use App\Models\PlanMaintenance;
use App\Services\ModuleDashboardService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Partage entre les 3 contrôleurs équipement (industriel / véhicule / bureau) tout
 * ce qui ne dépend que de la classe du modèle, du module et du répertoire de vues :
 * dashboard, interventions, plans de maintenance, stock de pièces du module et
 * liste des membres de l'organisation. Les 3 contrôleurs ne gardent que ce qui leur
 * est propre (validation spécifique au type d'équipement, photo, relations chargées).
 *
 * Nécessite les traits HandlesPieces / HandlesPlansMaintenance /
 * HandlesCoutsEntretien / HandlesEquipementStats / HandlesPagination (déjà
 * utilisés par les contrôleurs).
 */
trait HandlesEquipementModule
{
    use HandlesPagination;

    /** Classe du modèle équipement géré (ex. App\Models\Vehicule). */
    abstract protected function equipementClasse(): string;

    /** Clé de module de config/modules.php (ex. 'parc_automobile'). */
    abstract protected function moduleKey(): string;

    /** Répertoire de vues Inertia, sans slash final (ex. 'Vehicules'). */
    abstract protected function viewDir(): string;

    /** Liste compacte des équipements pour les <select> des formulaires. */
    abstract protected function equipementsPourSelect();

    public function dashboard(ModuleDashboardService $service): Response
    {
        return Inertia::render($this->viewDir().'/Dashboard', [
            'stats' => $service->calculer($this->equipementClasse()),
        ]);
    }

    public function interventionsIndex(Request $request): Response
    {
        [$tri, $sens, $parPage] = $this->parametresTri(
            $request,
            ['date_planifiee', 'titre', 'statut', 'priorite', 'created_at'],
            'date_planifiee'
        );

        $recherche = $this->termeRecherche($request);
        $statut = (string) $request->query('statut', '');
        $statutsValides = ['planifiee', 'en_cours', 'terminee', 'annulee'];

        $interventions = Intervention::query()
            ->where('equipementable_type', $this->equipementClasse())
            ->when($recherche !== '', fn ($q) => $q->where('titre', 'like', "%{$recherche}%"))
            ->when(in_array($statut, $statutsValides, true), fn ($q) => $q->where('statut', $statut))
            ->with(['equipementable', 'technicien', 'pieces'])
            ->orderBy($tri, $sens)
            ->paginate($parPage)
            ->withQueryString();

        return Inertia::render($this->viewDir().'/Interventions', [
            'interventions' => $interventions,
            'equipements' => $this->equipementsPourSelect(),
            'techniciens' => $this->organisationUsers(),
            'pieces' => $this->piecesForModule($this->moduleKey()),
        ]);
    }

    public function interventionsStore(Request $request): RedirectResponse
    {
        $classe = $this->equipementClasse();

        $data = $request->validate([
            'equipementable_id' => ['required', 'exists:'.(new $classe)->getTable().',id'],
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
            'equipementable_type' => $classe,
        ]);

        $this->recordCoutMainOeuvre($intervention);

        // Assignation d'un technicien → notification « intervention à démarrer ».
        if ($intervention->technicien_id) {
            $intervention->load(['technicien', 'equipementable']);
            $intervention->technicien?->notify(new \App\Notifications\InterventionAssignee($intervention));
        }

        return back()->with('status', 'Intervention enregistrée.');
    }

    public function plansIndex(Request $request): Response
    {
        [$tri, $sens, $parPage] = $this->parametresTri(
            $request,
            ['operation', 'frequence_valeur', 'derniere_execution_date', 'created_at'],
            'operation',
            'asc'
        );

        $recherche = $this->termeRecherche($request);

        $plans = PlanMaintenance::query()
            ->where('equipementable_type', $this->equipementClasse())
            ->when($recherche !== '', fn ($q) => $q->where('operation', 'like', "%{$recherche}%"))
            ->with('equipementable')
            ->orderBy($tri, $sens)
            ->paginate($parPage)
            ->withQueryString();

        // Attributs calculés (prochaine échéance / retard) sur la page courante.
        $plans->getCollection()->each->append(['prochaine_echeance', 'en_retard']);

        return Inertia::render($this->viewDir().'/Plans', [
            'plans' => $plans,
            'equipements' => $this->equipementsPourSelect(),
        ]);
    }

    public function plansStore(Request $request): RedirectResponse
    {
        $classe = $this->equipementClasse();

        $data = $request->validate([
            'equipementable_id' => ['required', 'exists:'.(new $classe)->getTable().',id'],
            ...$this->planValidationRules(),
        ]);

        PlanMaintenance::create([
            ...$data,
            'equipementable_type' => $classe,
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

    public function piecesIndex(Request $request): Response
    {
        return Inertia::render($this->viewDir().'/Pieces', [
            'pieces' => $this->piecesPagineesPourModule($this->moduleKey(), $request),
            'fournisseurs' => Fournisseur::orderBy('nom')->get(['id', 'nom']),
        ]);
    }

    public function piecesStore(Request $request): RedirectResponse
    {
        return $this->storePieceForModule($request, $this->moduleKey());
    }

    public function piecesUpdate(Request $request, Piece $piece): RedirectResponse
    {
        return $this->updatePieceForModule($request, $piece, $this->moduleKey());
    }

    public function piecesDestroy(Piece $piece): RedirectResponse
    {
        return $this->destroyPieceForModule($piece, $this->moduleKey());
    }

    /**
     * Membres actifs de l'organisation courante (techniciens assignables,
     * chauffeurs...). Le cloisonnement vient du scope BelongsToOrganisation.
     */
    protected function organisationUsers()
    {
        $organisation = Auth::user()->getCurrentOrganisation();

        return $organisation ? $organisation->users()->get(['users.id', 'users.name']) : collect();
    }
}
