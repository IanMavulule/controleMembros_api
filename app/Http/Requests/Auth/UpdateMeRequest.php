<?php

namespace App\Http\Requests\Auth;

use App\Traits\NormalizesPhone;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateMeRequest extends FormRequest
{
    use NormalizesPhone;

    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Apenas o administrador pode alterar nome, apelido e email do próprio perfil.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $rules = [
            'phone' => ['sometimes', 'string', 'regex:'.self::PHONE_REGEX, Rule::unique('users', 'phone')->ignore($this->user()->id)],
        ];

        if ($this->user()->isAdmin()) {
            $rules['name'] = ['sometimes', 'string', 'max:255'];
            $rules['lastname'] = ['sometimes', 'string', 'max:255'];
            $rules['email'] = ['sometimes', 'email:rfc', 'max:255', Rule::unique('users', 'email')->ignore($this->user()->id)];
        }

        return $rules;
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return $this->phoneMessages();
    }
}
