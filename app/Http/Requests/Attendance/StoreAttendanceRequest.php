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
 * Regista a presença de uma pessoa num culto, identificada pelo número de celular.
 * Nome, apelido e género só são obrigatórios quando o número ainda não existe.
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
            $this->existingPerson = Person::where('phone', $this->input('phone'))->first() ?? false;
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
            'phone' => ['required', 'string', 'regex:'.self::PHONE_REGEX],
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
            'name.required' => 'Número não registado: indique o nome para registar a pessoa.',
            'surname.required' => 'Número não registado: indique o apelido para registar a pessoa.',
            'gender.required' => 'Número não registado: indique o género para registar a pessoa.',
            'gender.enum' => 'O género deve ser "male" ou "female".',
            'location_id.exists' => 'Local não encontrado.',
            'class_group_id.exists' => 'Turma não encontrada.',
        ];
    }
}
