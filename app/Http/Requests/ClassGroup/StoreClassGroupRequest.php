<?php

namespace App\Http\Requests\ClassGroup;

use App\Models\ClassGroup;
use App\Rules\UniqueNameCode;
use Illuminate\Foundation\Http\FormRequest;

class StoreClassGroupRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255', new UniqueNameCode(ClassGroup::class, 'turma', feminine: true)],
        ];
    }
}
