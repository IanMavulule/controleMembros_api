<?php

namespace App\Rules;

use App\Models\Program;
use App\Models\User;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * O programa tem de existir, estar activo e ser acessível ao utilizador
 * (admin: todos; worker: os dos seus grupos activos).
 */
class AccessibleActiveProgram implements ValidationRule
{
    public function __construct(private readonly User $user) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $program = $this->user->accessiblePrograms()->whereKey($value)->first();

        if (! $program instanceof Program) {
            $fail('Programa não encontrado ou sem acesso.');

            return;
        }

        if (! $program->is_active) {
            $fail('O programa está inactivo.');
        }
    }
}
