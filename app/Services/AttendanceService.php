<?php

namespace App\Services;

use App\Enums\PersonTypeEnum;
use App\Models\Person;
use App\Models\User;
use App\Models\Worship;
use DomainException;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class AttendanceService
{
    public function __construct(private readonly PersonService $personService) {}

    /**
     * Pessoas presentes no culto, pela ordem de registo.
     *
     * @param  array{search?: string, type?: string, gender?: string, class_group_id?: int|string, per_page?: int|string}  $filters
     */
    public function list(Worship $worship, array $filters = []): LengthAwarePaginator
    {
        return $this->filteredQuery($worship, $filters)->paginate((int) ($filters['per_page'] ?? 50));
    }

    /**
     * Query partilhada pela listagem e pela exportação dos presentes.
     *
     * @param  array{search?: string, type?: string, gender?: string, class_group_id?: int|string}  $filters
     * @return BelongsToMany<Person, Worship>
     */
    public function filteredQuery(Worship $worship, array $filters = []): BelongsToMany
    {
        return $worship->people()
            ->with(['location', 'classGroup'])
            ->withCount('worships')
            ->when(
                filled($filters['search'] ?? null),
                fn ($q) => $this->personService->applyPersonSearch($q, $filters['search'], 'people')
            )
            ->when(isset($filters['type']), fn ($q) => $q->where('people.type', $filters['type']))
            ->when(isset($filters['gender']), fn ($q) => $q->where('people.gender', $filters['gender']))
            ->when(isset($filters['class_group_id']), fn ($q) => $q->where('people.class_group_id', $filters['class_group_id']))
            ->orderBy('person_worship.created_at');
    }

    /**
     * Totais do culto por tipo de pessoa.
     *
     * @return array{total: int, visitors: int, members: int}
     */
    public function summary(Worship $worship): array
    {
        $counts = $worship->people()
            ->selectRaw('people.type, count(*) as aggregate')
            ->groupBy('people.type')
            ->pluck('aggregate', 'people.type');

        $visitors = (int) ($counts[PersonTypeEnum::Visitor->value] ?? 0);
        $members = (int) ($counts[PersonTypeEnum::Member->value] ?? 0);

        return ['total' => $visitors + $members, 'visitors' => $visitors, 'members' => $members];
    }

    /**
     * Regista a presença: reaproveita a pessoa com o mesmo número ou cria-a,
     * associa-a ao culto e promove-a a membro se atingir o número de cultos.
     *
     * @param  array{person_id?: int|null, phone?: string|null, name?: string|null, surname?: string|null, gender?: string|null, location_id?: int|null, class_group_id?: int|null}  $data
     * @return array{person: Person, person_created: bool, promoted: bool}
     */
    public function register(Worship $worship, array $data, User $registrar): array
    {
        return DB::transaction(function () use ($worship, $data, $registrar) {
            [$person, $personCreated] = $this->findOrCreatePerson($data, $registrar);

            if ($worship->people()->whereKey($person->id)->exists()) {
                throw new DomainException("{$person->full_name} já está registado(a) neste culto.");
            }

            try {
                $worship->people()->attach($person->id, ['created_by' => $registrar->id]);
            } catch (UniqueConstraintViolationException) {
                throw new DomainException("{$person->full_name} já está registado(a) neste culto.");
            }

            $promoted = $this->personService->refreshMembership($person);

            return [
                'person' => $this->personService->loadDetails($person->fresh()),
                'person_created' => $personCreated,
                'promoted' => $promoted,
            ];
        });
    }

    /**
     * Procura primeiro pelo número; só cria se não existir. Se outro pedido criar a mesma
     * pessoa em simultâneo, o índice único trava este e reaproveita-se o registo existente.
     *
     * @param  array{person_id?: int|null, phone?: string|null, name?: string|null, surname?: string|null, gender?: string|null, location_id?: int|null, class_group_id?: int|null}  $data
     * @return array{0: Person, 1: bool}
     */
    private function findOrCreatePerson(array $data, User $registrar): array
    {
        $phone = $data['phone'] ?? null;
        $existing = match (true) {
            filled($data['person_id'] ?? null) => Person::find($data['person_id']),
            filled($phone) => Person::where('phone', $phone)->first(),
            default => null,
        };

        if ($existing) {
            return [$existing, false];
        }

        try {
            $person = $this->personService->create([
                'name' => $data['name'],
                'surname' => $data['surname'],
                'gender' => $data['gender'],
                'phone' => $phone,
                'location_id' => $data['location_id'] ?? null,
                'class_group_id' => $data['class_group_id'] ?? null,
            ], $registrar);

            return [$person, true];
        } catch (UniqueConstraintViolationException) {
            return [Person::where('phone', $phone)->firstOrFail(), false];
        }
    }

    /**
     * Remove a presença. Não despromove: quem já é membro continua membro.
     */
    public function remove(Worship $worship, Person $person): void
    {
        if (! $worship->people()->detach($person->id)) {
            throw new DomainException('Esta pessoa não está registada neste culto.');
        }
    }
}
