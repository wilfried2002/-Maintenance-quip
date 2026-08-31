<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganisation;
use App\Models\Concerns\ConsigneActivite;
use App\Models\Concerns\HasDocuments;
use App\Models\Concerns\HasPhoto;
use App\Models\Concerns\HasPlansMaintenance;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class EquipementIndustriel extends Model
{
    use HasFactory, SoftDeletes, HasPhoto, HasDocuments, HasPlansMaintenance, BelongsToOrganisation, ConsigneActivite;

    protected $table = 'equipements_industriels';

    /**
     * Ensure that photo_url accessor is always included in JSON responses.
     * This is critical for displaying photos in the frontend.
     */
    protected $appends = ['photo_url'];

    protected $fillable = [
        'organisation_id',
        'code',
        'designation',
        'categorie',
        'marque',
        'modele',
        'numero_serie',
        'ligne_production',
        'puissance_kw',
        'date_mise_service',
        'date_acquisition',
        'valeur_acquisition',
        'localisation',
        'statut',
        'criticite',
        'date_fin_garantie',
        'fournisseur_id',
        'responsable_id',
        'photo',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'date_mise_service' => 'date',
            'date_acquisition' => 'date',
            'date_fin_garantie' => 'date',
            'valeur_acquisition' => 'decimal:2',
            'puissance_kw' => 'decimal:2',
        ];
    }

    public function responsable(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsable_id');
    }

    public function fournisseur(): BelongsTo
    {
        return $this->belongsTo(Fournisseur::class);
    }

    public function interventions(): MorphMany
    {
        return $this->morphMany(Intervention::class, 'equipementable');
    }

    public function couts(): MorphMany
    {
        return $this->morphMany(CoutEntretien::class, 'equipementable');
    }
}
