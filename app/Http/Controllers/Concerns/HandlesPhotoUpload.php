<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

trait HandlesPhotoUpload
{
    protected function photoValidationRules(): array
    {
        return ['nullable', 'image', 'max:4096'];
    }

    /**
     * Remplace la photo du modèle si un nouveau fichier est envoyé, en supprimant
     * l'ancienne du disque privé. Ne fait rien si aucun fichier n'est présent.
     *
     * Les photos sont stockées sur le disque « local » (storage/app/private) :
     * elles ne sont servies que via FichierController (URL authentifiée), jamais
     * depuis /storage/... qui était public.
     */
    protected function replacePhoto(Request $request, $model, string $folder): void
    {
        if (!$request->hasFile('photo')) {
            return;
        }

        if ($model->photo) {
            Storage::disk('local')->delete($model->photo);
        }

        $model->photo = $request->file('photo')->store($folder, 'local');
    }
}
