<?php

namespace App\Services;

use App\Models\Group;
use App\Models\Program;
use App\Models\User;
use DomainException;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class GroupService
{
    /**
     * @param  array{search?: string, is_active?: bool|string, user_id?: int|string, program_id?: int|string, per_page?: int|string}  $filters
     */
    public function list(array $filters = []): LengthAwarePaginator
    {
        return Group::query()
            ->with([
                'programs' => fn ($q) => $q->orderBy('name'),
                'users' => fn ($q) => $q->orderBy('name')->orderBy('lastname'),
            ])
            ->withCount(['users', 'programs'])
            ->when(
                isset($filters['search']),
                fn ($q) => $q->where(function ($sub) use ($filters) {
                    $sub->where('name', 'like', "%{$filters['search']}%")
                        ->orWhere('code', 'like', '%'.Group::codeFromName($filters['search']).'%')
                        ->orWhere('description', 'like', "%{$filters['search']}%");
                })
            )
            ->when(
                isset($filters['is_active']),
                fn ($q) => $q->where('is_active', filter_var($filters['is_active'], FILTER_VALIDATE_BOOLEAN))
            )
            ->when(
                isset($filters['user_id']),
                fn ($q) => $q->whereHas('users', fn ($u) => $u->whereKey($filters['user_id']))
            )
            ->when(
                isset($filters['program_id']),
                fn ($q) => $q->whereHas('programs', fn ($p) => $p->whereKey($filters['program_id']))
            )
            ->orderBy('name')
            ->paginate((int) ($filters['per_page'] ?? 15));
    }

    /**
     * @param  array{name: string, description?: string|null, is_active?: bool, user_ids?: array<int, int>, program_ids?: array<int, int>}  $data
     */
    public function create(array $data): Group
    {
        return DB::transaction(function () use ($data) {
            $group = Group::create([
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'is_active' => $data['is_active'] ?? true,
            ]);

            $this->syncRelations($group, $data);

            return $this->loadDetails($group);
        });
    }

    /**
     * @param  array{name?: string, description?: string|null, is_active?: bool, user_ids?: array<int, int>, program_ids?: array<int, int>}  $data
     */
    public function update(Group $group, array $data): Group
    {
        return DB::transaction(function () use ($group, $data) {
            $group->update(array_intersect_key($data, array_flip(['name', 'description', 'is_active'])));

            $this->syncRelations($group, $data);

            return $this->loadDetails($group->fresh());
        });
    }

    public function delete(Group $group): void
    {
        $group->delete();
    }

    /**
     * Substitui os utilizadores do grupo pela lista enviada (lista vazia remove todos).
     *
     * @param  array<int, int>  $userIds
     */
    public function syncUsers(Group $group, array $userIds): Group
    {
        $group->users()->sync($userIds);

        return $this->loadDetails($group);
    }

    /**
     * Acrescenta utilizadores ao grupo sem remover os que já lá estão.
     *
     * @param  array<int, int>  $userIds
     */
    public function attachUsers(Group $group, array $userIds): Group
    {
        $group->users()->syncWithoutDetaching($userIds);

        return $this->loadDetails($group);
    }

    public function detachUser(Group $group, User $user): Group
    {
        if (! $group->users()->detach($user->id)) {
            throw new DomainException('O utilizador não pertence a este grupo.');
        }

        return $this->loadDetails($group);
    }

    /**
     * Substitui os programas do grupo pela lista enviada (lista vazia remove todos).
     *
     * @param  array<int, int>  $programIds
     */
    public function syncPrograms(Group $group, array $programIds): Group
    {
        $group->programs()->sync($programIds);

        return $this->loadDetails($group);
    }

    /**
     * Acrescenta programas ao grupo sem remover os que já lá estão.
     *
     * @param  array<int, int>  $programIds
     */
    public function attachPrograms(Group $group, array $programIds): Group
    {
        $group->programs()->syncWithoutDetaching($programIds);

        return $this->loadDetails($group);
    }

    public function detachProgram(Group $group, Program $program): Group
    {
        if (! $group->programs()->detach($program->id)) {
            throw new DomainException('O programa não está associado a este grupo.');
        }

        return $this->loadDetails($group);
    }

    /**
     * Substitui os grupos de um utilizador pela lista enviada (lista vazia remove todos).
     *
     * @param  array<int, int>  $groupIds
     */
    public function syncUserGroups(User $user, array $groupIds): User
    {
        $user->groups()->sync($groupIds);

        return $user->load('groups');
    }

    public function loadDetails(Group $group): Group
    {
        return $group->load(['users', 'programs'])->loadCount(['users', 'programs']);
    }

    /**
     * Só sincroniza as listas enviadas no pedido; uma lista vazia remove todas as associações.
     *
     * @param  array{user_ids?: array<int, int>, program_ids?: array<int, int>}  $data
     */
    private function syncRelations(Group $group, array $data): void
    {
        if (array_key_exists('user_ids', $data)) {
            $group->users()->sync($data['user_ids']);
        }

        if (array_key_exists('program_ids', $data)) {
            $group->programs()->sync($data['program_ids']);
        }
    }
}
