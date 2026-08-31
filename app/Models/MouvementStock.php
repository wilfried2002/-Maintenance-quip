<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganisation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MouvementStock extends Model
{
    use BelongsToOrganisation;
    use ConsigneActivite;

    protected $table = 'mouvements_stock';

    protected $fillable = [
        'organisation_id',
        'piece_id',
        'type',
        'quantite',
        'stock_apres',
        'motif',
        'user_id',
    ];

    protected function casts(): array
    {
        return [
            'quantite' => 'integer',
            'stock_apres' => 'integer',
        ];
    }

    public function piece(): BelongsTo
    {
        return $this->belongsTo(Piece::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
