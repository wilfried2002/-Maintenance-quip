<?php

namespace App\Http\Controllers;

use App\Models\Intervention;
use App\Notifications\InterventionAssignee;
use App\Notifications\InterventionStatusUpdated;
use App\Services\IndicateurPerformanceCalculator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InterventionController extends Controller
{
    /** The assigned technician advances the work; an admin can supervise it. */
    public function updateStatus(Request $request, Intervention $intervention): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user->isAdmin() || $intervention->technicien_id === $user->id, 403);

        $data = $request->validate([
            'statut' => ['required', 'in:en_cours,terminee,annulee'],
        ]);

        $transitions = [
            'planifiee' => ['en_cours', 'annulee'],
            'en_cours' => ['terminee', 'annulee'],
        ];

        if (!in_array($data['statut'], $transitions[$intervention->statut] ?? [], true)) {
            throw ValidationException::withMessages([
                'statut' => 'Cette transition de statut n’est pas autorisée.',
            ]);
        }

        $updates = ['statut' => $data['statut']];
        if ($data['statut'] === 'en_cours' && !$intervention->date_debut) {
            $updates['date_debut'] = now();
        }
        if ($data['statut'] === 'terminee' && !$intervention->date_fin) {
            $updates['date_fin'] = now();
        }

        $intervention->update($updates);
        $this->notifyStatusChange($intervention->fresh(), $user);

        return back()->with('status', 'Statut de l’intervention mis à jour.');
    }

    public function update(Request $request, Intervention $intervention, IndicateurPerformanceCalculator $calculator): RedirectResponse
    {
        $data = $request->validate([
            'titre' => ['required', 'string', 'max:255'],
            'type_intervention' => ['required', 'in:preventive,corrective,predictive'],
            'statut' => ['required', 'in:planifiee,en_cours,terminee,annulee'],
            'priorite' => ['required', 'in:basse,normale,haute,critique'],
            'date_planifiee' => ['nullable', 'date'],
            'date_debut' => ['nullable', 'date'],
            'date_fin' => ['nullable', 'date'],
            'technicien_id' => ['nullable', 'exists:users,id'],
            'description' => ['nullable', 'string'],
            'cout_main_oeuvre' => ['nullable', 'numeric', 'min:0'],
            'duree_heures' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string'],
        ]);

        $previousTechnicienId = $intervention->technicien_id;
        $previousStatus = $intervention->statut;
        $intervention->update($data);

        if ($intervention->technicien_id && $intervention->technicien_id !== $previousTechnicienId) {
            $intervention->load(['technicien', 'equipementable']);
            $intervention->technicien?->notify(new InterventionAssignee($intervention));
        }
        if ($intervention->statut !== $previousStatus) {
            $this->notifyStatusChange($intervention->fresh(), $request->user());
        }
        $intervention->pieces()->get()->each(fn ($piece) => $calculator->recalculerPiece($piece));

        return back()->with('status', 'Intervention mise à jour.');
    }

    public function destroy(Intervention $intervention, IndicateurPerformanceCalculator $calculator): RedirectResponse
    {
        $pieces = $intervention->pieces()->get();

        DB::transaction(function () use ($intervention, $pieces) {
            foreach ($pieces as $piece) {
                $piece->increment('stock_qte', $piece->pivot->quantite);
            }

            $intervention->pieces()->detach();
            $intervention->delete();
        });

        $pieces->each(fn ($piece) => $calculator->recalculerPiece($piece));

        return back()->with('status', 'Intervention supprimée et stock des pièces restitué.');
    }

    /**
     * Enregistrer les notes de terrain d'une intervention (observations du technicien
     * pendant/après l'exécution) — distinct de description, saisie à la planification.
     */
    private function notifyStatusChange(Intervention $intervention, object $actor): void
    {
        $labels = [
            'en_cours' => 'démarrée',
            'terminee' => 'terminée',
            'annulee' => 'annulée',
        ];
        $organisation = $actor->getCurrentOrganisation();
        $admins = $organisation
            ? $organisation->users()->wherePivot('role', 'admin')->wherePivot('is_active', true)->get()
            : collect();

        $recipients = $admins
            ->when($intervention->technicien_id, fn ($users) => $users->push($intervention->technicien))
            ->filter()
            ->unique('id')
            ->reject(fn ($user) => $user->id === $actor->id);

        $recipients->each(fn ($user) => $user->notify(new InterventionStatusUpdated(
            $intervention,
            $labels[$intervention->statut] ?? $intervention->statut,
        )));
    }

    public function updateNotes(Request $request, Intervention $intervention): RedirectResponse
    {
        $data = $request->validate([
            'notes' => ['nullable', 'string'],
        ]);

        $intervention->update($data);

        return back()->with('status', 'Notes enregistrées.');
    }
}
