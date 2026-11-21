<?php

namespace App\Services;

use App\Models\Location;
use DomainException;
use Illuminate\Pagination\LengthAwarePaginator;

class LocationService
{
    /**
     * @param  array{search?: string, per_page?: int|string}  $filters
     */
    public function list(array $filters = []): LengthAwarePaginator
    {
        return Location::query()
            ->withCount('people')
            ->when(
                isset($filters['search']),
                fn ($q) => $q->where(function ($sub) use ($filters) {
                    $sub->where('name', 'like', "%{$filters['search']}%")
                        ->orWhere('code', 'like', '%'.Location::codeFromName($filters['search']).'%');
                })
            )
            ->orderBy('name')
            ->paginate((int) ($filters['per_page'] ?? 50));
    }

    /**
     * @param  array{name: string}  $data
     */
    public function create(array $data): Location
    {
        return Location::create($data);
    }

    /**
     * @param  array{name?: string}  $data
     */
    public function update(Location $location, array $data): Location
    {
        $location->update($data);

        return $location->fresh();
    }

    public function delete(Location $location): void
    {
        if ($location->people()->exists()) {
            throw new DomainException('Não é possível eliminar uma zona/bairro com pessoas registadas.');
        }

        $location->delete();
    }
}
