<?php

namespace App\Models;

use App\Enums\RoleEnum;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

#[Fillable(['user_code', 'role', 'name', 'lastname', 'phone', 'email', 'password', 'is_active'])]
#[Hidden(['password'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'role' => RoleEnum::class,
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return BelongsToMany<Group, $this>
     */
    public function groups(): BelongsToMany
    {
        return $this->belongsToMany(Group::class)->withTimestamps();
    }

    /**
     * Programas a que o utilizador tem acesso: todos para o admin; para o worker,
     * os programas associados aos seus grupos activos.
     *
     * @return Builder<Program>
     */
    public function accessiblePrograms(): Builder
    {
        if ($this->isAdmin()) {
            return Program::query();
        }

        return Program::query()->whereHas('groups', fn (Builder $groups) => $groups
            ->where('groups.is_active', true)
            ->whereHas('users', fn (Builder $users) => $users->whereKey($this->id))
        );
    }

    public function canAccessProgram(int $programId): bool
    {
        return $this->accessiblePrograms()->whereKey($programId)->exists();
    }

    public function isAdmin(): bool
    {
        return $this->role === RoleEnum::Admin;
    }

    public function hasRole(string|RoleEnum $role): bool
    {
        $value = $role instanceof RoleEnum ? $role->value : $role;

        return $this->role->value === $value;
    }

    /**
     * @param  array<int, string>  $roles
     */
    public function hasAnyRole(array $roles): bool
    {
        return in_array($this->role->value, $roles, true);
    }

    public function getFullNameAttribute(): string
    {
        return trim($this->name.' '.$this->lastname);
    }
}
