<?php

namespace App\Traits;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Support\Str;

/**
 * Para models com "name" de exibição e "code" único derivado dele.
 * O code ignora espaços extra, maiúsculas e acentos:
 * "Mesa Profética", "Mesa   Profética" e "mesa profetica" → "mesa-profetica".
 */
trait HasUniqueNameCode
{
    public static function codeFromName(string $name): string
    {
        return Str::slug($name);
    }

    /**
     * Guarda o nome sem espaços extra e mantém o code sempre sincronizado com ele.
     *
     * @return Attribute<string, string>
     */
    protected function name(): Attribute
    {
        return Attribute::make(
            set: fn (string $value) => [
                'name' => Str::squish($value),
                'code' => static::codeFromName($value),
            ],
        );
    }
}
