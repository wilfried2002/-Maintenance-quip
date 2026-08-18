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

    protected function destroyDocument(\App\Models\Document $document): void
    {
        Storage::disk('public')->delete($document->chemin);
        $document->delete();
    }
}
