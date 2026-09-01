<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganisation;
use App\Models\Concerns\ConsigneActivite;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Fournisseur extends Model
{
    use HasFactory, SoftDeletes, BelongsToOrganisation, ConsigneActivite;

    protected $fillable = [
        'organisation_id',
        'nom',
        'contact_nom',
        'telephone',
        'email',
        'adresse',
        'notes',
    ];

    public function equipementsIndustriels(): HasMany
    {
        return $this->hasMany(EquipementIndustriel::class);
    }

    public function vehicules(): HasMany
    {
        return $this->hasMany(Vehicule::class);
    }

    public function equipementsBureau(): HasMany
    {
        return $this->hasMany(EquipementBureau::class);
    }
}
