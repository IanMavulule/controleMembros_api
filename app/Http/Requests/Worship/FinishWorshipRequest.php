<?php

namespace App\Http\Requests\Worship;

use App\Models\Worship;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Validator;

class FinishWorshipRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::forUser($this->user())->authorize('finish', $this->route('worship'))->allowed();
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'end_time' => ['required', 'date_format:H:i'],
        ];
    }

    /**
     * A hora de fim tem de ser posterior à hora de início guardada.
     *
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($validator->errors()->has('end_time')) {
                    return;
                }

                /** @var Worship $worship */
                $worship = $this->route('worship');

                if ($this->input('end_time') <= substr((string) $worship->start_time, 0, 5)) {
                    $validator->errors()->add('end_time', 'A hora de fim deve ser posterior à hora de início.');
                }
            },
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'end_time.required' => 'Indique a hora de fim do culto.',
            'end_time.date_format' => 'A hora de fim deve estar no formato HH:MM.',
        ];
    }
}
