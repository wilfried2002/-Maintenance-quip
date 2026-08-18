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
     * l'ancienne du disque public. Ne fait rien si aucun fichier n'est présent.
     */
    protected function replacePhoto(Request $request, $model, string $folder): void
    {
        if (!$request->hasFile('photo')) {
            return;
        }

        if ($model->photo) {
            Storage::disk('public')->delete($model->photo);
        }

        $model->photo = $request->file('photo')->store($folder, 'public');
    }
}
