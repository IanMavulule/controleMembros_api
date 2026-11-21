<?php

namespace App\Models;

use App\Traits\HasUniqueNameCode;
use Database\Factories\LocationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Zona / bairro: entidade independente com code único (evita "Matola Rio" e "matola  rio").
 */
#[Fillable(['name'])]
class Location extends Model
{
    /** @use HasFactory<LocationFactory> */
    use HasFactory, HasUniqueNameCode;

    /**
     * @return HasMany<Person, $this>
     */
    public function people(): HasMany
    {
        return $this->hasMany(Person::class);
    }
}
