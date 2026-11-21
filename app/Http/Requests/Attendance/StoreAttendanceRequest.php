<?php

namespace App\Http\Requests\Attendance;

use App\Enums\GenderEnum;
use App\Models\Person;
use App\Traits\NormalizesPhone;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Regista a presença de uma pessoa num culto. A pessoa é reconhecida pelo person_id
 * (escolhida na lista) ou pelo número de celular. Sem número, é sempre uma pessoa nova.
 * Nome, apelido e género só são obrigatórios quando a pessoa ainda não existe.
 */
class StoreAttendanceRequest extends FormRequest
{
    use NormalizesPhone {
        prepareForValidation as normalizePhoneInput;
    }

    private Person|false|null $existingPerson = null;

    public function authorize(): bool
    {
        return Gate::forUser($this->user())->authorize('attend', $this->route('worship'))->allowed();
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

    public function existingPerson(): ?Person
    {
        if ($this->existingPerson === null) {
            $this->existingPerson = match (true) {
                filled($this->input('person_id')) => Person::find($this->input('person_id')) ?? false,
                filled($this->input('phone')) => Person::where('phone', $this->input('phone'))->first() ?? false,
                default => false,
            };
        }

        return $this->existingPerson ?: null;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $isNewPerson = fn () => $this->existingPerson() === null;

        return [
            'person_id' => ['nullable', 'integer', 'exists:people,id'],
            'phone' => ['nullable', 'string', 'regex:'.self::PHONE_REGEX],
            'name' => [Rule::requiredIf($isNewPerson), 'nullable', 'string', 'max:255'],
            'surname' => [Rule::requiredIf($isNewPerson), 'nullable', 'string', 'max:255'],
            'gender' => [Rule::requiredIf($isNewPerson), 'nullable', Rule::enum(GenderEnum::class)],
            'location_id' => ['nullable', 'integer', 'exists:locations,id'],
            'class_group_id' => ['nullable', 'integer', 'exists:class_groups,id'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            ...$this->phoneMessages(),
            'person_id.exists' => 'Pessoa não encontrada.',
            'name.required' => 'Indique o nome para registar a pessoa.',
            'surname.required' => 'Indique o apelido para registar a pessoa.',
            'gender.required' => 'Indique o género para registar a pessoa.',
            'gender.enum' => 'O género deve ser "male" ou "female".',
            'location_id.exists' => 'Local não encontrado.',
            'class_group_id.exists' => 'Turma não encontrada.',
        ];
    }
}
