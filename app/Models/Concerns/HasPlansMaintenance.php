<?php

namespace App\Models\Concerns;

use App\Models\PlanMaintenance;
use Illuminate\Database\Eloquent\Relations\MorphMany;

trait HasPlansMaintenance
{
    public function plansMaintenance(): MorphMany
    {
        return $this->morphMany(PlanMaintenance::class, 'equipementable');
    }
}
