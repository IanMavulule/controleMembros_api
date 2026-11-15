<?php

namespace App\Services;

use App\Enums\PersonTypeEnum;
use App\Models\User;
use App\Models\Worship;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;

class WorshipService
{
    /**
     * Lista os cultos visíveis ao utilizador, do mais recente para o mais antigo.
     *
     * @param  array{search?: string, program_id?: int|string, date?: string, date_from?: string, date_to?: string, per_page?: int|string}  $filters
     */
    public function list(User $user, array $filters = []): LengthAwarePaginator
    {
        return $this->filteredQuery($user, $filters)->paginate((int) ($filters['per_page'] ?? 15));
    }

    /**
     * Query partilhada pela listagem e pela exportação (mesmos filtros e regras de acesso).
     * Inclui as contagens de presentes, membros e visitantes.
     *
     * @param  array{search?: string, program_id?: int|string, date?: string, date_from?: string, date_to?: string}  $filters
     * @return Builder<Worship>
     */
    public function filteredQuery(User $user, array $filters = []): Builder
    {
        return Worship::query()
            ->accessibleBy($user)
            ->with(['program', 'creator'])
            ->withCount([
                'people as attendances_count',
                'people as members_count' => fn ($q) => $q->where('people.type', PersonTypeEnum::Member->value),
                'people as visitors_count' => fn ($q) => $q->where('people.type', PersonTypeEnum::Visitor->value),
            ])
            ->when(
                isset($filters['search']),
                fn ($q) => $q->where(function ($sub) use ($filters) {
                    $sub->where('name', 'like', "%{$filters['search']}%")
                        ->orWhere('title', 'like', "%{$filters['search']}%");
                })
            )
            ->when(isset($filters['program_id']), fn ($q) => $q->where('program_id', $filters['program_id']))
            ->when(isset($filters['date']), fn ($q) => $q->whereDate('date', $filters['date']))
            ->when(isset($filters['date_from']), fn ($q) => $q->whereDate('date', '>=', $filters['date_from']))
            ->when(isset($filters['date_to']), fn ($q) => $q->whereDate('date', '<=', $filters['date_to']))
            ->orderByDesc('date')
            ->orderByDesc('start_time');
    }

    /**
     * @param  array{program_id: int, name: string, title?: string|null, date: string, start_time: string, end_time?: string|null}  $data
     */
    public function create(array $data, User $creator): Worship
    {
        $worship = Worship::create([
            ...$data,
            'created_by' => $creator->id,
        ]);

        return $worship->load(['program', 'creator']);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Worship $worship, array $data): Worship
    {
        $worship->update($data);

        return $worship->fresh(['program', 'creator']);
    }

    /**
     * Regista a hora de fim do culto. Chamar de novo corrige a hora registada.
     */
    public function finish(Worship $worship, string $endTime): Worship
    {
        $worship->update(['end_time' => $endTime]);

        return $worship->fresh(['program', 'creator']);
    }

    public function delete(Worship $worship): void
    {
        $worship->delete();
    }
}
