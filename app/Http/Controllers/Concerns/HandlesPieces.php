<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Piece;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Stock cloisonné par module (industriel/parc auto/bureau) : même principe que les
 * interventions et plans de maintenance, qui vivent déjà à l'intérieur de chaque module
 * malgré une table partagée (voir Piece::module, ajouté pour ce cloisonnement).
 */
trait HandlesPieces
{
    protected function pieceValidationRules(): array
    {
        return [
            'designation' => ['required', 'string', 'max:255'],
            'categorie' => ['nullable', 'string', 'max:255'],
            'unite' => ['nullable', 'string', 'max:50'],
            'stock_qte' => ['nullable', 'integer', 'min:0'],
            'stock_min' => ['nullable', 'integer', 'min:0'],
            'prix_unitaire_moyen' => ['nullable', 'numeric', 'min:0'],
            'fournisseur' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
        ];
    }

    protected function piecesForModule(string $module)
    {
        return Piece::where('module', $module)->orderBy('designation')->get();
    }

    protected function storePieceForModule(Request $request, string $module): RedirectResponse
    {
        $data = $request->validate([
            'reference' => ['required', 'string', 'max:100', 'unique:pieces,reference'],
            ...$this->pieceValidationRules(),
        ]);

        Piece::create([...$data, 'module' => $module]);

        return back()->with('status', 'Pièce enregistrée.');
    }

    protected function updatePieceForModule(Request $request, Piece $piece, string $module): RedirectResponse
    {
        abort_if($piece->module !== $module, 404);

        $data = $request->validate([
            'reference' => ['required', 'string', 'max:100', Rule::unique('pieces', 'reference')->ignore($piece->id)],
            ...$this->pieceValidationRules(),
        ]);

        $piece->update($data);

        return back()->with('status', 'Pièce mise à jour.');
    }

    protected function destroyPieceForModule(Piece $piece, string $module): RedirectResponse
    {
        abort_if($piece->module !== $module, 404);

        $piece->delete();

        return back()->with('status', 'Pièce supprimée.');
    }
}
