<?php

namespace App\Rules;

use App\Models\Person;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * O número de celular identifica a pessoa: não pode haver duas com o mesmo número.
 * A mensagem indica quem já usa o número, para o obreiro reaproveitar o registo.
 */
class UniquePersonPhone implements ValidationRule
{
    public function __construct(private readonly ?int $ignoreId = null) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $existing = Person::query()
            ->where('phone', $value)
            ->when($this->ignoreId, fn ($q) => $q->whereKeyNot($this->ignoreId))
            ->first();

        if ($existing) {
            $fail("Já existe uma pessoa com este número: {$existing->full_name} (ID {$existing->id}).");
        }
    }
}
