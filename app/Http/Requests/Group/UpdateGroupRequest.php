<?php

namespace App\Http\Requests\Group;

use App\Models\Group;
use App\Rules\UniqueNameCode;
use Illuminate\Foundation\Http\FormRequest;

class UpdateGroupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->isAdmin();
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:255', new UniqueNameCode(Group::class, 'grupo', $this->route('group')->id)],
            'description' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'is_active' => ['sometimes', 'boolean'],
            'user_ids' => ['sometimes', 'array'],
            'user_ids.*' => ['integer', 'distinct', 'exists:users,id'],
            'program_ids' => ['sometimes', 'array'],
            'program_ids.*' => ['integer', 'distinct', 'exists:programs,id'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'user_ids.*.exists' => 'Utilizador não encontrado.',
            'program_ids.*.exists' => 'Programa não encontrado.',
        ];
    }
}
