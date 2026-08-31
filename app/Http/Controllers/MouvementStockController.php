<?php

namespace App\Http\Controllers;

use App\Models\MouvementStock;
use App\Models\Piece;
use App\Services\RoleService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Mouvements de stock : entrées (réapprovisionnement), sorties manuelles et
 * ajustements d'inventaire, journalisés avec le stock résultant. L'accès suit
 * les mêmes règles que les stocks {module}/pieces (responsable_parc ne gère que
 * le stock du parc automobile, etc.) — vérifié PAR PIÈCE dans store().
 */
class MouvementStockController extends Controller
{
    public function index(): Response
    {
        $modules = RoleService::modulesStockAccessibles(Auth::user());

        $mouvements = MouvementStock::query()
            ->whereHas('piece', fn ($q) => $q->whereIn('module', $modules))
            ->with(['piece:id,reference,designation,module,unite', 'user:id,name'])
            ->latest()
            ->limit(300)
            ->get()
            ->map(fn (MouvementStock $mouvement) => [
                'id' => $mouvement->id,
                'created_at' => $mouvement->created_at?->toISOString(),
                'piece' => $mouvement->piece
                    ? "{$mouvement->piece->reference} — {$mouvement->piece->designation}"
                    : '—',
                'module' => $mouvement->piece?->module,
                'module_label' => config('modules.list')[$mouvement->piece?->module ?? '']['label'] ?? '—',
                'type' => $mouvement->type,
                'quantite' => $mouvement->quantite,
                'stock_apres' => $mouvement->stock_apres,
                'unite' => $mouvement->piece?->unite,
                'motif' => $mouvement->motif,
                'user' => $mouvement->user?->name,
            ]);

        return Inertia::render('Mouvements/Index', [
            'mouvements' => $mouvements,
        ]);
    }

    public function store(Request $request, Piece $piece): RedirectResponse
    {
        abort_unless(
            RoleService::peutGererStockModule(Auth::user(), $piece->module),
            403,
            'Vous ne gérez pas le stock du module de cette pièce.'
        );

        $data = $request->validate([
            'type' => ['required', 'in:entree,sortie,ajustement'],
            'quantite' => ['required', 'integer', 'min:0'],
            'motif' => ['nullable', 'string', 'max:255'],
        ]);

        // Sortie et ajustement doivent être justifiés ; une entrée n'a pas besoin
        // de motif (réappro habituel) mais peut en porter un.
        if (in_array($data['type'], ['sortie', 'ajustement'], true) && empty($data['motif'])) {
            throw ValidationException::withMessages([
                'motif' => 'Un motif est obligatoire pour une sortie ou un ajustement.',
            ]);
        }

        DB::transaction(function () use ($piece, $data) {
            // Verrou pessimiste : le contrôle et la mise à jour sont atomiques.
            $pieceVerrouillee = Piece::whereKey($piece->id)->lockForUpdate()->firstOrFail();

            $stockApres = match ($data['type']) {
                'entree' => $pieceVerrouillee->stock_qte + $data['quantite'],
                'sortie' => $pieceVerrouillee->stock_qte - $data['quantite'],
                // Ajustement : la quantité saisie EST le stock physique compté.
                'ajustement' => $data['quantite'],
            };

            if ($stockApres < 0) {
                throw ValidationException::withMessages([
                    'quantite' => "Stock insuffisant pour « {$pieceVerrouillee->designation} » (disponible : {$pieceVerrouillee->stock_qte}).",
                ]);
            }

            MouvementStock::create([
                // Source de vérité : l'organisation de la pièce (le scope la poserait
                // aussi depuis la session, mais restons explicites).
                'organisation_id' => $pieceVerrouillee->organisation_id,
                'piece_id' => $pieceVerrouillee->id,
                'type' => $data['type'],
                'quantite' => $data['quantite'],
                'stock_apres' => $stockApres,
                'motif' => $data['motif'] ?? null,
                'user_id' => Auth::id(),
            ]);

            $pieceVerrouillee->update(['stock_qte' => $stockApres]);
        });

        return back()->with('status', 'Mouvement enregistré.');
    }
}
