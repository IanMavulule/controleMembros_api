<?php

namespace App\Services;

use App\Models\Program;
use App\Models\User;
use DomainException;
use Illuminate\Pagination\LengthAwarePaginator;

class ProgramService
{
    /**
     * Lista os programas visíveis ao utilizador (admin: todos; worker: os dos seus grupos activos).
     *
     * @param  array{search?: string, is_active?: bool|string, per_page?: int|string}  $filters
     */
    public function list(User $user, array $filters = []): LengthAwarePaginator
    {
        return $user->accessiblePrograms()
            ->when(
                isset($filters['search']),
                fn ($q) => $q->where(function ($sub) use ($filters) {
                    $sub->where('name', 'like', "%{$filters['search']}%")
                        ->orWhere('code', 'like', '%'.Program::codeFromName($filters['search']).'%')
                        ->orWhere('description', 'like', "%{$filters['search']}%");
                })
            )
            ->when(
                isset($filters['is_active']),
                fn ($q) => $q->where('is_active', filter_var($filters['is_active'], FILTER_VALIDATE_BOOLEAN))
            )
            ->orderBy('name')
            ->paginate((int) ($filters['per_page'] ?? 15));
    }

    /**
     * @param  array{name: string, description?: string|null, is_active?: bool}  $data
     */
    public function create(array $data): Program
    {
        return Program::create([
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'is_active' => $data['is_active'] ?? true,
        ]);
    }

    /**
     * @param  array{name?: string, description?: string|null, is_active?: bool}  $data
     */
    public function update(Program $program, array $data): Program
    {
        $program->update($data);

        return $program->fresh();
    }

    public function delete(Program $program): void
    {
        if ($program->worships()->exists()) {
            throw new DomainException('Não é possível eliminar um programa com cultos registados. Desactive-o em vez disso.');
        }

        $program->delete();
    }
}
