<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganisation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class CoutEntretien extends Model
{
    use BelongsToOrganisation;
    use ConsigneActivite;

    protected $table = 'couts_entretien';

    protected $fillable = [
        'organisation_id',
        'equipementable_type',
        'equipementable_id',
        'intervention_id',
        'type_cout',
        'montant',
        'date',
        'description',
    ];

    protected function casts(): array
    {
        return [
            'montant' => 'decimal:2',
            'date' => 'date',
        ];
    }

    public function equipementable(): MorphTo
    {
        return $this->morphTo();
    }

    public function intervention(): BelongsTo
    {
        return $this->belongsTo(Intervention::class);
    }
}
