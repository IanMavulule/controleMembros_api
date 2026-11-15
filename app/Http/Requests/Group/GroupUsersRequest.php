<?php

namespace App\Http\Requests\Group;

use Illuminate\Foundation\Http\FormRequest;

/**
 * PUT (sincronizar) aceita lista vazia; POST (adicionar) exige pelo menos um utilizador.
 */
class GroupUsersRequest extends FormRequest
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
            'user_ids' => $this->isMethod('put') ? ['present', 'array'] : ['required', 'array', 'min:1'],
            'user_ids.*' => ['integer', 'distinct', 'exists:users,id'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'user_ids.*.exists' => 'Utilizador não encontrado.',
        ];
    }
}
