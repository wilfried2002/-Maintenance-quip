<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganisation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class IndicateurPerformancePiece extends Model
{
    use BelongsToOrganisation;

    protected $table = 'indicateurs_performance_pieces';

    protected $fillable = [
        'organisation_id',
        'piece_id',
        'equipementable_type',
        'equipementable_id',
        'nombre_remplacements',
        'duree_vie_moyenne_jours',
        'mtbf_heures',
        'taux_defaillance',
        'cout_total_remplacement',
        'derniere_maj',
    ];

    protected function casts(): array
    {
        return [
            'nombre_remplacements' => 'integer',
            'duree_vie_moyenne_jours' => 'decimal:2',
            'mtbf_heures' => 'decimal:2',
            'taux_defaillance' => 'decimal:4',
            'cout_total_remplacement' => 'decimal:2',
            'derniere_maj' => 'date',
        ];
    }

    public function piece(): BelongsTo
    {
        return $this->belongsTo(Piece::class);
    }

    public function equipementable(): MorphTo
    {
        return $this->morphTo();
    }
}
