<?php

namespace App\Http\Controllers;

use App\Models\Intervention;
use App\Models\Piece;
use App\Services\IndicateurPerformanceCalculator;
use App\Services\RoleService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class PieceConsumptionController extends Controller
{
    /**
     * Enregistrer la consommation d'une pièce sur une intervention : décrémente le
     * stock du même coup, en figeant le prix unitaire du moment (le prix moyen de la
     * pièce peut évoluer plus tard sans changer le coût déjà comptabilisé).
     */
    public function store(Request $request, Intervention $intervention, IndicateurPerformanceCalculator $calculator): RedirectResponse
    {
        $this->autoriserConsommation($intervention);

        $data = $request->validate([
            'piece_id' => ['required', 'exists:pieces,id'],
            'quantite' => ['required', 'integer', 'min:1'],
        ]);

        $piece = Piece::findOrFail($data['piece_id']);

        // Le stock est cloisonné par module (voir Piece::module) : une pièce du parc
        // automobile ne doit pas pouvoir être consommée sur une intervention industrielle.
        $moduleIntervention = RoleService::modulePourClasseEquipement($intervention->equipementable_type);
        if ($moduleIntervention && $piece->module !== $moduleIntervention) {
            return back()->withErrors([
                'piece_id' => "Cette pièce n'appartient pas au stock de ce module.",
            ]);
        }

        if ($piece->stock_qte < $data['quantite']) {
            return back()->withErrors([
                'quantite' => "Stock insuffisant pour « {$piece->designation} » (disponible : {$piece->stock_qte}).",
            ]);
        }

        DB::transaction(function () use ($intervention, $piece, $data) {
            $intervention->pieces()->attach($piece->id, [
                'quantite' => $data['quantite'],
                'prix_unitaire' => $piece->prix_unitaire_moyen,
            ]);

            $piece->decrement('stock_qte', $data['quantite']);
        });

        $calculator->recalculerPiece($piece);

        return back()->with('status', 'Pièce ajoutée à l\'intervention.');
    }

    /**
     * Retirer une ligne de consommation : restitue la quantité au stock.
     */
    public function destroy(Intervention $intervention, int $interventionPiece, IndicateurPerformanceCalculator $calculator): RedirectResponse
    {
        $this->autoriserConsommation($intervention);

        $row = DB::table('intervention_pieces')
            ->where('id', $interventionPiece)
            ->where('intervention_id', $intervention->id)
            ->first();

        if (!$row) {
            abort(404);
        }

        $piece = Piece::findOrFail($row->piece_id);

        DB::transaction(function () use ($row, $piece) {
            $piece->increment('stock_qte', $row->quantite);
            DB::table('intervention_pieces')->where('id', $row->id)->delete();
        });

        $calculator->recalculerPiece($piece);

        return back()->with('status', 'Pièce retirée de l\'intervention, stock restitué.');
    }

    /**
     * Le module concerné dépend de l'équipement de l'INTERVENTION, pas de l'URL : un
     * rôle autorisé au stock d'un module (ex. responsable_parc → parc automobile) ne
     * doit pas pouvoir consommer le stock d'un autre module (ex. industriel).
     * Complète le check.role posé sur la route, qui ne voit que l'union des rôles.
     */
    private function autoriserConsommation(Intervention $intervention): void
    {
        abort_unless(
            RoleService::peutConsommerPiecesIntervention(Auth::user(), $intervention),
            403,
            'Vous n\'avez pas accès au stock du module de l\'équipement concerné par cette intervention.'
        );
    }
}
