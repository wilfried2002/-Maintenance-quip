<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganisation;
use App\Models\Concerns\ConsigneActivite;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class DemandeIntervention extends Model
{
    use BelongsToOrganisation;
    use ConsigneActivite;

    public const STATUTS = ['soumise', 'approuvee', 'refusee', 'convertie'];

    public const PRIORITES = ['basse', 'normale', 'haute', 'critique'];

    protected $fillable = [
        'organisation_id',
        'module',
        'equipementable_type',
        'equipementable_id',
        'titre',
        'description',
        'priorite',
        'statut',
        'demandeur_id',
        'decideur_id',
        'motif_decision',
        'decide_le',
        'intervention_id',
    ];

    protected function casts(): array
    {
        return [
            'decide_le' => 'datetime',
        ];
    }

    public function demandeur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'demandeur_id');
    }

    public function decideur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decideur_id');
    }

    public function intervention(): BelongsTo
    {
        return $this->belongsTo(Intervention::class);
    }

    public function equipementable(): MorphTo
    {
        return $this->morphTo();
    }
}
