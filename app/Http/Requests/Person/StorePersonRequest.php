<?php

namespace App\Http\Requests\Person;

use App\Enums\GenderEnum;
use App\Enums\PersonTypeEnum;
use App\Rules\UniquePersonPhone;
use App\Traits\NormalizesPhone;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class StorePersonRequest extends FormRequest
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
     * Só o admin pode registar alguém directamente como membro;
     * os restantes entram como visitantes e são promovidos pelas presenças.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'surname' => ['required', 'string', 'max:255'],
            'gender' => ['required', Rule::enum(GenderEnum::class)],
            'phone' => ['required', 'string', 'regex:'.self::PHONE_REGEX, new UniquePersonPhone],
            'location_id' => ['nullable', 'integer', 'exists:locations,id'],
            'class_group_id' => ['nullable', 'integer', 'exists:class_groups,id'],
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
            'type.prohibited' => 'Apenas administradores podem definir o tipo da pessoa.',
            'location_id.exists' => 'Local não encontrado.',
            'class_group_id.exists' => 'Turma não encontrada.',
        ];
    }
}
