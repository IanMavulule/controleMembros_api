<?php

namespace App\Http\Requests\Person;

use App\Enums\GenderEnum;
use App\Enums\PersonTypeEnum;
use App\Rules\UniquePersonPhone;
use App\Traits\NormalizesPhone;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class UpdatePersonRequest extends FormRequest
{
    use NormalizesPhone {
        prepareForValidation as normalizePhoneInput;
    }

    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        $this->normalizePhoneInput();

        foreach (['name', 'surname'] as $field) {
            if (is_string($this->input($field))) {
                $this->merge([$field => Str::squish($this->input($field))]);
            }
        }
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'surname' => ['sometimes', 'string', 'max:255'],
            'gender' => ['sometimes', Rule::enum(GenderEnum::class)],
            'phone' => ['sometimes', 'nullable', 'string', 'regex:'.self::PHONE_REGEX, new UniquePersonPhone($this->route('person')->id)],
            'location_id' => ['sometimes', 'nullable', 'integer', 'exists:locations,id'],
            'class_group_id' => ['sometimes', 'nullable', 'integer', 'exists:class_groups,id'],
            'type' => [Rule::prohibitedIf(! $this->user()->isAdmin()), 'sometimes', Rule::enum(PersonTypeEnum::class)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            ...$this->phoneMessages(),
            'gender.enum' => 'O género deve ser "male" ou "female".',
            'type.enum' => 'O tipo deve ser "visitor" ou "member".',
            'type.prohibited' => 'Apenas administradores podem alterar o tipo da pessoa.',
            'location_id.exists' => 'Local não encontrado.',
            'class_group_id.exists' => 'Turma não encontrada.',
        ];
    }
}
