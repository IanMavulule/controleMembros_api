<?php

namespace App\Models;

use App\Traits\HasUniqueNameCode;
use Database\Factories\LocationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * O code é único dentro de cada província (ex.: pode haver "Centro" em duas províncias).
 */
#[Fillable(['province_id', 'name'])]
class Location extends Model
{
    /** @use HasFactory<LocationFactory> */
    use HasFactory, HasUniqueNameCode;

    /**
     * @return BelongsTo<Province, $this>
     */
    public function province(): BelongsTo
    {
        return $this->belongsTo(Province::class);
    }

    /**
     * @return HasMany<Person, $this>
     */
    public function people(): HasMany
    {
        return $this->hasMany(Person::class);
    }
}
