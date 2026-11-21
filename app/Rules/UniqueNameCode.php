<?php

namespace App\Rules;

use App\Traits\HasUniqueNameCode;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Eloquent\Model;

/**
 * Impede nomes que, depois de normalizados (espaços, maiúsculas e acentos),
 * coincidam com o de outro registo do mesmo model.
 */
class UniqueNameCode implements ValidationRule
{
    /**
     * @param  class-string<Model&HasUniqueNameCode>  $modelClass
     * @param  string  $label  Nome do recurso nas mensagens (ex.: "programa", "grupo", "província").
     * @param  bool  $feminine  Usa "uma"/"da" nas mensagens (ex.: "Já existe uma turma").
     * @param  array<string, mixed>  $scope  Limita a unicidade.
     */
    public function __construct(
        private readonly string $modelClass,
        private readonly string $label,
        private readonly ?int $ignoreId = null,
        private readonly bool $feminine = false,
        private readonly array $scope = [],
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value)) {
            return;
        }

        $code = $this->modelClass::codeFromName($value);

        if ($code === '') {
            $of = $this->feminine ? 'da' : 'do';
            $fail("O nome {$of} {$this->label} deve conter letras ou números.");

            return;
        }

        $duplicate = $this->modelClass::query()
            ->where('code', $code)
            ->where($this->scope)
            ->when($this->ignoreId, fn ($q) => $q->whereKeyNot($this->ignoreId))
            ->first();

        if ($duplicate) {
            $article = $this->feminine ? 'uma' : 'um';
            $fail("Já existe {$article} {$this->label} com este nome: \"{$duplicate->name}\".");
        }
    }
}
