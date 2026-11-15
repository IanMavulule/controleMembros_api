<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_code' => $this->user_code,
            'role' => $this->role->value,
            'role_label' => $this->role->label(),
            'name' => $this->name,
            'lastname' => $this->lastname,
            'phone' => $this->phone,
            'email' => $this->email,
            'is_active' => $this->is_active,
            'groups' => GroupResource::collection($this->whenLoaded('groups')),
            'created_at' => $this->created_at?->toDateTimeString(),
        ];
    }
}
