<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Fournisseur;
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
            // Vraie FK vers fournisseurs : l'ancien champ texte libre « fournisseur »
            // était cassé au premier renommage du fournisseur.
            'fournisseur_id' => ['nullable', 'exists:fournisseurs,id'],
            'notes' => ['nullable', 'string'],
        ];
    }

    protected function piecesForModule(string $module)
    {
        return Piece::where('module', $module)->orderBy('designation')->get();
    }

    /**
     * Version paginée pour la page de gestion du stock du module (recherche,
     * tri sur liste blanche, page) — la liste complète reste utilisée pour les
     * listes déroulates (consommation sur intervention, ...).
     */
    protected function piecesPagineesPourModule(string $module, Request $request)
    {
        [$tri, $sens, $parPage] = $this->parametresTri(
            $request,
            ['designation', 'reference', 'categorie', 'stock_qte', 'stock_min', 'prix_unitaire_moyen'],
            'designation',
            'asc'
        );

        $recherche = $this->termeRecherche($request);

        return Piece::where('module', $module)
            ->when($recherche !== '', fn ($q) => $q->where(fn ($w) => $w
                ->where('reference', 'like', "%{$recherche}%")
                ->orWhere('designation', 'like', "%{$recherche}%")))
            ->orderBy($tri, $sens)
            ->paginate($parPage)
            ->withQueryString();
    }

    protected function storePieceForModule(Request $request, string $module): RedirectResponse
    {
        $data = $request->validate([
            'reference' => ['required', 'string', 'max:100', 'unique:pieces,reference'],
            ...$this->pieceValidationRules(),
        ]);

        Piece::create([...$this->avecNomFournisseur($data), 'module' => $module]);

        return back()->with('status', 'Pièce enregistrée.');
    }

    protected function updatePieceForModule(Request $request, Piece $piece, string $module): RedirectResponse
    {
        abort_if($piece->module !== $module, 404);

        $data = $request->validate([
            'reference' => ['required', 'string', 'max:100', Rule::unique('pieces', 'reference')->ignore($piece->id)],
            ...$this->pieceValidationRules(),
        ]);

        $piece->update($this->avecNomFournisseur($data));

        return back()->with('status', 'Pièce mise à jour.');
    }

    protected function destroyPieceForModule(Piece $piece, string $module): RedirectResponse
    {
        abort_if($piece->module !== $module, 404);

        $piece->delete();

        return back()->with('status', 'Pièce supprimée.');
    }

    /**
     * Resynchronise le champ texte pieces.fournisseur (conservé pour l'affichage)
     * depuis la FK fournisseur_id : si un fournisseur est lié, son nom actuel fait
     * foi — sinon on garde le texte existant inchangé.
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function avecNomFournisseur(array $data): array
    {
        if (array_key_exists('fournisseur_id', $data) && $data['fournisseur_id'] !== null) {
            $data['fournisseur'] = Fournisseur::find($data['fournisseur_id'])?->nom;
        }

        return $data;
    }
}
