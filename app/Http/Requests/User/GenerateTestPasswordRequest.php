<?php

namespace App\Http\Requests\User;

use App\Services\UserService;
use Illuminate\Foundation\Http\FormRequest;

class GenerateTestPasswordRequest extends FormRequest
{
    public function authorize(UserService $userService): bool
    {
        return $this->user()->isAdmin()
            && $userService->canUseTestCredentials()
            && $this->user()->id !== $this->route('user')->id;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [];
    }
}
