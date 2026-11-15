<?php

namespace App\Models;

use App\Enums\GenderEnum;
use App\Enums\PersonTypeEnum;
use Database\Factories\PersonFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable(['name', 'surname', 'gender', 'phone', 'location_id', 'class_group_id', 'type', 'became_member_at', 'created_by'])]
class Person extends Model
{
    /** @use HasFactory<PersonFactory> */
    use HasFactory;

    /**
     * Número de cultos diferentes (de qualquer programa) para um visitante passar a membro.
     */
    public const MEMBERSHIP_THRESHOLD = 5;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'gender' => GenderEnum::class,
            'type' => PersonTypeEnum::class,
            'became_member_at' => 'datetime',
        ];
    }

    /**
     * Cultos em que a pessoa esteve presente.
     *
     * @return BelongsToMany<Worship, $this>
     */
    public function worships(): BelongsToMany
    {
        return $this->belongsToMany(Worship::class)->withPivot('created_by')->withTimestamps();
    }

    /**
     * @return BelongsTo<Location, $this>
     */
    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    /**
     * @return BelongsTo<ClassGroup, $this>
     */
    public function classGroup(): BelongsTo
    {
        return $this->belongsTo(ClassGroup::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isMember(): bool
    {
        return $this->type === PersonTypeEnum::Member;
    }

    public function getFullNameAttribute(): string
    {
        return trim($this->name.' '.$this->surname);
    }
}
