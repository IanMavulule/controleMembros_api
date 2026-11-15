<?php

namespace App\Http\Requests\Group;

use Illuminate\Foundation\Http\FormRequest;

/**
 * PUT (sincronizar) aceita lista vazia; POST (adicionar) exige pelo menos um programa.
 */
class GroupProgramsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->isAdmin();
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'program_ids' => $this->isMethod('put') ? ['present', 'array'] : ['required', 'array', 'min:1'],
            'program_ids.*' => ['integer', 'distinct', 'exists:programs,id'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'program_ids.*.exists' => 'Programa não encontrado.',
        ];
    }
}
