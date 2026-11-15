<?php

namespace App\Http\Requests\Location;

use App\Models\Location;
use App\Rules\UniqueNameCode;
use Illuminate\Foundation\Http\FormRequest;

class StoreLocationRequest extends FormRequest
{
    /**
     * Qualquer utilizador autenticado pode criar um local (ex.: o obreiro ao registar
     * uma pessoa no culto). Editar e eliminar continuam reservados ao admin.
     */
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * O nome é único dentro da província escolhida.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'province_id' => ['required', 'integer', 'exists:provinces,id'],
            'name' => [
                'required', 'string', 'max:255',
                new UniqueNameCode(Location::class, 'local nesta província', scope: ['province_id' => (int) $this->input('province_id')]),
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
