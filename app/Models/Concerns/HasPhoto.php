<?php

namespace App\Models\Concerns;

trait HasPhoto
{
    /**
     * URL relative (pas absolue) : évite de dépendre d'APP_URL, qui peut ne pas
     * correspondre à l'hôte/port réellement utilisé en local (ex. `php artisan serve`
     * sur un port personnalisé) — le navigateur résout le chemin sur l'origine courante.
     */
    public function getPhotoUrlAttribute(): ?string
    {
        return $this->photo ? '/storage/' . ltrim($this->photo, '/') : null;
    }

    public function initializeHasPhoto(): void
    {
        $this->append('photo_url');
    }
}
