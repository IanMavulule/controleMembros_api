<?php

namespace App\Traits;

use Illuminate\Database\Eloquent\Builder;

trait SearchesFullName
{
    /**
     * Pesquisa cada palavra do termo no nome ou apelido ("Maria Cos" encontra "Maria Cossa").
     */
    protected function applyFullNameSearch(Builder $query, ?string $search, string $prefix = '', string $lastnameColumn = 'lastname'): void
    {
        $tokens = preg_split('/\s+/', trim((string) $search), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        if ($tokens === []) {
            return;
        }

        $name = $prefix ? "{$prefix}.name" : 'name';
        $lastname = $prefix ? "{$prefix}.{$lastnameColumn}" : $lastnameColumn;

        foreach ($tokens as $token) {
            $query->where(function (Builder $q) use ($token, $name, $lastname) {
                $q->where($name, 'like', "%{$token}%")
                    ->orWhere($lastname, 'like', "%{$token}%");
            });
        }
    }
}
