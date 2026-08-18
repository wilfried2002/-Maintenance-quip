<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Organisation extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'code',
        'devise',
        'logo',
        'is_active',
    ];

    public const DEVISES = [
        'XOF' => 'FCFA',
        'EUR' => '€',
        'USD' => '$',
    ];

    public function symboleDevise(): string
    {
        return self::DEVISES[$this->devise] ?? $this->devise;
    }

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_organisations')
            ->withPivot('role', 'is_active')
            ->withTimestamps();
    }
}
