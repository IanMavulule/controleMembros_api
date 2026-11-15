<?php

namespace App\Models;

use Database\Factories\WorshipFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable(['program_id', 'name', 'title', 'date', 'start_time', 'end_time', 'created_by'])]
class Worship extends Model
{
    /** @use HasFactory<WorshipFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'date' => 'date:Y-m-d',
        ];
    }

    /**
     * @return BelongsTo<Program, $this>
     */
    public function program(): BelongsTo
    {
        return $this->belongsTo(Program::class);
    }

    /**
     * Pessoas presentes no culto.
     *
     * @return BelongsToMany<Person, $this>
     */
    public function people(): BelongsToMany
    {
        return $this->belongsToMany(Person::class)->withPivot('created_by')->withTimestamps();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Um culto com hora de fim está finalizado: fica fechado a alterações.
     */
    public function isFinished(): bool
    {
        return $this->end_time !== null;
    }

    /**
     * Cultos que o utilizador pode ver: todos para o admin; para o worker,
     * só os dos programas associados aos seus grupos activos.
     *
     * @param  Builder<Worship>  $query
     */
    public function scopeAccessibleBy(Builder $query, User $user): void
    {
        if ($user->isAdmin()) {
            return;
        }

        $query->whereIn('program_id', $user->accessiblePrograms()->select('programs.id'));
    }
}
