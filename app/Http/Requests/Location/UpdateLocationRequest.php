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
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        /** @var Location $location */
        $location = $this->route('location');

        return [
            'name' => ['sometimes', 'string', 'max:255', new UniqueNameCode(Location::class, 'zona/bairro', $location->id, feminine: true)],
        ];
    }
}
