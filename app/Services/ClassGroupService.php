<?php

namespace App\Services;

use App\Models\ClassGroup;
use DomainException;
use Illuminate\Database\Eloquent\Collection;

class ClassGroupService
{
    /**
     * Lista sem paginação, com o número de pessoas de cada turma.
     *
     * @param  array{search?: string}  $filters
     * @return Collection<int, ClassGroup>
     */
    public function list(array $filters = []): Collection
    {
        return ClassGroup::query()
            ->withCount('people')
            ->when(
                isset($filters['search']),
                fn ($q) => $q->where(function ($sub) use ($filters) {
                    $sub->where('name', 'like', "%{$filters['search']}%")
                        ->orWhere('code', 'like', '%'.ClassGroup::codeFromName($filters['search']).'%');
                })
            )
            ->orderBy('name')
            ->get();
    }

    /**
     * @param  array{name: string}  $data
     */
    public function create(array $data): ClassGroup
    {
        return ClassGroup::create(['name' => $data['name']])->loadCount('people');
    }

    /**
     * @param  array{name?: string}  $data
     */
    public function update(ClassGroup $classGroup, array $data): ClassGroup
    {
        $classGroup->update($data);

        return $classGroup->fresh()->loadCount('people');
    }

    public function delete(ClassGroup $classGroup): void
    {
        if ($classGroup->people()->exists()) {
            throw new DomainException('Não é possível eliminar uma turma com pessoas associadas.');
        }

        $classGroup->delete();
    }
}
