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
     * URL relative : évite de dépendre d'APP_URL (voir HasPhoto).
     */
    public function getUrlAttribute(): string
    {
        return '/storage/' . ltrim($this->chemin, '/');
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
