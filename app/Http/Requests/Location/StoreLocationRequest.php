<?php

namespace App\Http\Requests\Location;

use App\Models\Location;
use App\Rules\UniqueNameCode;
use Illuminate\Foundation\Http\FormRequest;

class StoreLocationRequest extends FormRequest
{
    /**
     * Qualquer utilizador autenticado pode criar uma zona/bairro (ex.: o obreiro ao registar
     * uma pessoa no culto). Editar e eliminar continuam reservados ao admin.
     */
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255', new UniqueNameCode(Location::class, 'zona/bairro', feminine: true)],
        ];
    }
}
