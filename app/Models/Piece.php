<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganisation;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Piece extends Model
{
    use HasFactory, SoftDeletes, BelongsToOrganisation;

    protected $fillable = [
        'organisation_id',
        'module',
        'reference',
        'designation',
        'categorie',
        'unite',
        'stock_qte',
        'stock_min',
        'prix_unitaire_moyen',
        'fournisseur',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'stock_qte' => 'integer',
            'stock_min' => 'integer',
            'prix_unitaire_moyen' => 'decimal:2',
        ];
    }

    public function interventions(): BelongsToMany
    {
        return $this->belongsToMany(Intervention::class, 'intervention_pieces')
            ->withPivot('quantite', 'prix_unitaire')
            ->withTimestamps();
    }

    public function indicateursPerformance(): HasMany
    {
        return $this->hasMany(IndicateurPerformancePiece::class);
    }

    public function enSousStock(): bool
    {
        return $this->stock_qte <= $this->stock_min;
    }
}
