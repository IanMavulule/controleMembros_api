<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class DashboardFilterRequest extends FormRequest
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
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:date_from'],
            'program_id' => ['nullable', 'integer', 'exists:programs,id'],
            'search' => ['nullable', 'string', 'max:100'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'date_from.date_format' => 'A data inicial deve estar no formato AAAA-MM-DD.',
            'date_to.date_format' => 'A data final deve estar no formato AAAA-MM-DD.',
            'date_to.after_or_equal' => 'A data final deve ser igual ou posterior à data inicial.',
            'program_id.exists' => 'Programa não encontrado.',
        ];
    }
}
