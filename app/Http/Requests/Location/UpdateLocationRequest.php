<?php

namespace App\Http\Requests\Location;

use App\Models\Location;
use App\Rules\UniqueNameCode;
use Illuminate\Foundation\Http\FormRequest;

class UpdateLocationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->isAdmin();
    }

    /**
     * O nome continua único dentro da província (a nova, se for alterada).
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        /** @var Location $location */
        $location = $this->route('location');
        $provinceId = (int) $this->input('province_id', $location->province_id);

        return [
            'province_id' => ['sometimes', 'integer', 'exists:provinces,id'],
            'name' => [
                'sometimes', 'string', 'max:255',
                new UniqueNameCode(Location::class, 'local nesta província', $location->id, scope: ['province_id' => $provinceId]),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'province_id.exists' => 'Província não encontrada.',
        ];
    }
}
