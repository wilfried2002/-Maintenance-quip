<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

trait HandlesDocuments
{
    protected function documentsValidationRules(): array
    {
        return [
            'documents' => ['required', 'array'],
            'documents.*' => ['file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:10240'],
        ];
    }

    /**
     * Enregistre chaque fichier envoyé comme un Document rattaché au modèle, et
     * retourne le nombre de documents créés.
     */
    protected function storeDocuments(Request $request, $model, string $folder): int
    {
        $count = 0;

        foreach ($request->file('documents', []) as $file) {
            $path = $file->store($folder, 'public');

            $model->documents()->create([
                // Organisation du document = celle de l'équipement porteur (le scope
                // BelongsToOrganisation la poserait aussi depuis la session, mais
                // l'équipement reste la source de vérité).
                'organisation_id' => $model->organisation_id,
                'nom_original' => $file->getClientOriginalName(),
                'chemin' => $path,
                'type_mime' => $file->getClientMimeType(),
                'taille' => $file->getSize(),
                'uploaded_by' => Auth::id(),
            ]);

            $count++;
        }

        return $count;
    }

    /**
     * Supprime un document après avoir vérifié qu'il est bien rattaché à l'équipement
     * de l'URL : sinon, n'importe quel membre de l'organisation pourrait effacer par
     * ID (IDOR) le document d'un autre équipement. Le cloisonnement ENTRE
     * organisations est, lui, assuré par le scope global BelongsToOrganisation du
     * modèle Document (le binding de route ne résout donc jamais un document d'une
     * autre organisation).
     */
    protected function destroyDocument(\App\Models\Document $document, $model): void
    {
        abort_unless(
            $document->equipementable_type === $model::class
                && (int) $document->equipementable_id === (int) $model->id,
            404
        );

        Storage::disk('public')->delete($document->chemin);
        $document->delete();
    }
}
