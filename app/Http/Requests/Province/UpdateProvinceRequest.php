<?php

namespace App\Http\Requests\Province;

use App\Models\Province;
use App\Rules\UniqueNameCode;
use Illuminate\Foundation\Http\FormRequest;

class UpdateProvinceRequest extends FormRequest
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
        return [
            'name' => ['required', 'string', 'max:255', new UniqueNameCode(Province::class, 'província', $this->route('province')->id, feminine: true)],
        ];
    }
}
