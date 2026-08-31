<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganisation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Document extends Model
{
    use BelongsToOrganisation;

    protected $fillable = [
        'organisation_id',
        'equipementable_type',
        'equipementable_id',
        'nom_original',
        'chemin',
        'type_mime',
        'taille',
        'uploaded_by',
    ];

    protected $appends = ['url'];

    /**
     * Les fichiers vivent sur le disque privé (storage/app/private) : l'URL passe
     * par FichierController, qui vérifie organisation + module avant de servir le
     * fichier (plus d'URL /storage/... publique et non authentifiée). URL
     * relative pour ne pas dépendre de APP_URL (voir HasPhoto).
     */
    public function getUrlAttribute(): string
    {
        return route('fichiers.document', ['document' => $this->id], absolute: false);
    }

    public function equipementable(): MorphTo
    {
        return $this->morphTo();
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
