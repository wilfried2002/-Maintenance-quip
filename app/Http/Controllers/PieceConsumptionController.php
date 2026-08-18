<?php

namespace App\Http\Controllers;

use App\Models\EquipementBureau;
use App\Models\EquipementIndustriel;
use App\Models\Intervention;
use App\Models\Piece;
use App\Models\Vehicule;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PieceConsumptionController extends Controller
{
    private const MODULE_PAR_CLASSE = [
        EquipementIndustriel::class => 'equipements_industriels',
        Vehicule::class => 'parc_automobile',
        EquipementBureau::class => 'equipement_bureau',
    ];

    /**
     * Enregistrer la consommation d'une pièce sur une intervention : décrémente le
     * stock du même coup, en figeant le prix unitaire du moment (le prix moyen de la
     * pièce peut évoluer plus tard sans changer le coût déjà comptabilisé).
     */
    public function store(Request $request, Intervention $intervention): RedirectResponse
    {
        $data = $request->validate([
            'piece_id' => ['required', 'exists:pieces,id'],
            'quantite' => ['required', 'integer', 'min:1'],
        ]);

        $piece = Piece::findOrFail($data['piece_id']);

        // Le stock est cloisonné par module (voir Piece::module) : une pièce du parc
        // automobile ne doit pas pouvoir être consommée sur une intervention industrielle.
        $moduleIntervention = self::MODULE_PAR_CLASSE[$intervention->equipementable_type] ?? null;
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

        return back()->with('status', 'Pièce ajoutée à l\'intervention.');
    }

    /**
     * Retirer une ligne de consommation : restitue la quantité au stock.
     */
    public function destroy(Intervention $intervention, int $interventionPiece): RedirectResponse
    {
        $row = DB::table('intervention_pieces')
            ->where('id', $interventionPiece)
            ->where('intervention_id', $intervention->id)
            ->first();

        if (!$row) {
            abort(404);
        }

        DB::transaction(function () use ($row) {
            Piece::where('id', $row->piece_id)->increment('stock_qte', $row->quantite);
            DB::table('intervention_pieces')->where('id', $row->id)->delete();
        });

        return back()->with('status', 'Pièce retirée de l\'intervention, stock restitué.');
    }
}
