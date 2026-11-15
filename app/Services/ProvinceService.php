<?php

namespace App\Services;

use App\Models\Province;
use DomainException;
use Illuminate\Database\Eloquent\Collection;

class ProvinceService
{
    /**
     * Lista sem paginação: são poucas e servem para preencher selects.
     *
     * @param  array{search?: string}  $filters
     * @return Collection<int, Province>
     */
    public function list(array $filters = []): Collection
    {
        return Province::query()
            ->when(
                isset($filters['search']),
                fn ($q) => $q->where(function ($sub) use ($filters) {
                    $sub->where('name', 'like', "%{$filters['search']}%")
                        ->orWhere('code', 'like', '%'.Province::codeFromName($filters['search']).'%');
                })
            )
            ->orderBy('name')
            ->get();
    }

    /**
     * @param  array{name: string}  $data
     */
    public function create(array $data): Province
    {
        return Province::create(['name' => $data['name']]);
    }

    /**
     * @param  array{name?: string}  $data
     */
    public function update(Province $province, array $data): Province
    {
        $province->update($data);

        return $province->fresh();
    }

    public function delete(Province $province): void
    {
        if ($province->locations()->exists()) {
            throw new DomainException('Não é possível eliminar uma província com locais registados.');
        }

        $province->delete();
    }
}
