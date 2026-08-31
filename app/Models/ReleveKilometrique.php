<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganisation;
use App\Models\Concerns\ConsigneActivite;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Relevé kilométrique d'un véhicule : chaque lecture du compteur est
 * historisée (le compteur courant du véhicule n'est plus qu'une vue du
 * dernier relevé), les plans de maintenance au kilomètre s'appuient dessus.
 */
class ReleveKilometrique extends Model
{
    use BelongsToOrganisation;
    use ConsigneActivite;

    protected $fillable = [
        'organisation_id',
        'vehicule_id',
        'kilometrage',
        'date_releve',
        'source',
        'user_id',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'date_releve' => 'date',
        ];
    }

    public function vehicule(): BelongsTo
    {
        return $this->belongsTo(Vehicule::class);
    }

    public function utilisateur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
