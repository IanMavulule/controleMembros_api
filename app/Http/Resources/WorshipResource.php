<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WorshipResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'program_id' => $this->program_id,
            'program' => new ProgramResource($this->whenLoaded('program')),
            'name' => $this->name,
            'title' => $this->title,
            'date' => $this->date?->format('Y-m-d'),
            'start_time' => $this->start_time ? substr($this->start_time, 0, 5) : null,
            'end_time' => $this->end_time ? substr($this->end_time, 0, 5) : null,
            'attendances_count' => $this->whenHas('attendances_count', fn () => (int) $this->attendances_count),
            'members_count' => $this->whenHas('members_count', fn () => (int) $this->members_count),
            'visitors_count' => $this->whenHas('visitors_count', fn () => (int) $this->visitors_count),
            'created_by' => $this->whenLoaded('creator', fn () => $this->creator ? [
                'id' => $this->creator->id,
                'name' => $this->creator->full_name,
            ] : null),
            'created_at' => $this->created_at?->toDateTimeString(),
            'updated_at' => $this->updated_at?->toDateTimeString(),
        ];
    }
}
