<?php

namespace App\Models\Concerns;

trait HasPhoto
{
    /**
     * Boot the HasPhoto trait.
     * Ensures photo_url accessor is always appended to JSON serialization.
     */
    protected static function bootHasPhoto(): void
    {
        // This ensures the accessor is included even if not in $appends on instance creation
    }

    /**
     * Initialize the HasPhoto trait.
     * Called when a model instance is created, ensuring photo_url is always available.
     */
    public function initializeHasPhoto(): void
    {
        // Ensure photo_url is in the appends list for this instance
        if (!in_array('photo_url', $this->appends)) {
            $this->appends[] = 'photo_url';
        }
    }

    /**
     * Get the photo URL attribute.
     * Les photos vivent sur le disque privé (storage/app/private) : l'URL passe
     * par FichierController, qui vérifie organisation + module avant de servir le
     * fichier — plus d'URL /storage/... publique servie sans authentification.
     * URL relative (pas absolue) : évite de dépendre d'APP_URL, qui peut ne pas
     * correspondre à l'hôte/port réellement utilisé en local (ex. `php artisan serve`
     * sur un port personnalisé) — le navigateur résout le chemin sur l'origine courante.
     */
    public function getPhotoUrlAttribute(): ?string
    {
        if (!$this->photo) {
            return null;
        }

        $type = match (static::class) {
            \App\Models\EquipementIndustriel::class => 'industriels',
            \App\Models\Vehicule::class => 'vehicules',
            \App\Models\EquipementBureau::class => 'bureau',
            default => null,
        };

        return $type
            ? route('fichiers.photo', ['type' => $type, 'id' => $this->getKey()], absolute: false)
            : null;
    }

    /**
     * Override toArray() to ensure photo_url is always included.
     * This is critical for Inertia.js serialization.
     */
    public function toArray(): array
    {
        $array = parent::toArray();
        
        // Ensure photo_url is always present, even if it wasn't appended
        if (!isset($array['photo_url'])) {
            $array['photo_url'] = $this->getPhotoUrlAttribute();
        }
        
        return $array;
    }
}
