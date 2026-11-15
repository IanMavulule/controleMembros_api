<?php

namespace App\Http\Requests\Worship;

use App\Models\Worship;
use App\Rules\AccessibleActiveProgram;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

class StoreWorshipRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::forUser($this->user())->authorize('create', Worship::class)->allowed();
    }

    protected function prepareForValidation(): void
    {
        foreach (['name', 'title'] as $field) {
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
            'program_id' => ['required', 'integer', new AccessibleActiveProgram($this->user())],
            'name' => ['required', 'string', 'max:255'],
            'title' => ['nullable', 'string', 'max:255'],
            'date' => ['required', 'date_format:Y-m-d'],
            'start_time' => ['required', 'date_format:H:i'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'date.date_format' => 'A data deve estar no formato AAAA-MM-DD.',
            'start_time.date_format' => 'A hora de início deve estar no formato HH:MM.',
        ];
    }
}
