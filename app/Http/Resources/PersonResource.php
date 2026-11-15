<?php

namespace App\Http\Resources;

use App\Enums\PersonTypeEnum;
use App\Models\Person;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Person
 */
class PersonResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'surname' => $this->surname,
            'full_name' => $this->full_name,
            'gender' => $this->gender->value,
            'gender_label' => $this->gender->label(),
            'phone' => $this->phone,
            'type' => $this->type->value,
            'type_label' => $this->type->label(),
            'became_member_at' => $this->became_member_at?->toDateTimeString(),
            'attendances_count' => $this->whenCounted('worships'),
            'attendances_to_membership' => $this->whenCounted('worships', fn () => $this->type === PersonTypeEnum::Visitor
                ? max(0, Person::MEMBERSHIP_THRESHOLD - $this->worships_count)
                : 0),
            'location' => new LocationResource($this->whenLoaded('location')),
            'class_group_id' => $this->class_group_id,
            'class_group' => new ClassGroupResource($this->whenLoaded('classGroup')),
            'created_by' => $this->whenLoaded('creator', fn () => $this->creator ? [
                'id' => $this->creator->id,
                'name' => $this->creator->full_name,
            ] : null),
            'registered_at' => $this->whenPivotLoaded('person_worship', fn () => $this->pivot->created_at?->toDateTimeString()),
            'created_at' => $this->created_at?->toDateTimeString(),
        ];
    }
}
