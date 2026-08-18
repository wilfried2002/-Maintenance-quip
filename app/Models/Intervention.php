<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganisation;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Intervention extends Model
{
    use HasFactory, SoftDeletes, BelongsToOrganisation;

    protected $fillable = [
        'organisation_id',
        'equipementable_type',
        'equipementable_id',
        'plan_maintenance_id',
        'type_intervention',
        'statut',
        'priorite',
        'date_planifiee',
        'date_debut',
        'date_fin',
        'technicien_id',
        'titre',
        'description',
        'cout_main_oeuvre',
        'duree_heures',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'date_planifiee' => 'datetime',
            'date_debut' => 'datetime',
            'date_fin' => 'datetime',
            'cout_main_oeuvre' => 'decimal:2',
            'duree_heures' => 'decimal:2',
        ];
    }

    public function equipementable(): MorphTo
    {
        return $this->morphTo();
    }

    public function planMaintenance(): BelongsTo
    {
        return $this->belongsTo(PlanMaintenance::class);
    }

    public function technicien(): BelongsTo
    {
        return $this->belongsTo(User::class, 'technicien_id');
    }

    public function pieces(): BelongsToMany
    {
        return $this->belongsToMany(Piece::class, 'intervention_pieces')
            ->withPivot('id', 'quantite', 'prix_unitaire')
            ->withTimestamps();
    }

    public function couts(): HasMany
    {
        return $this->hasMany(CoutEntretien::class);
    }

    public function coutTotalPieces(): float
    {
        return (float) $this->pieces->sum(fn (Piece $piece) => $piece->pivot->quantite * $piece->pivot->prix_unitaire);
    }

    public function coutTotal(): float
    {
        return (float) $this->cout_main_oeuvre + $this->coutTotalPieces();
    }
}
