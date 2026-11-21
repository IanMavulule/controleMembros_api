<?php

namespace App\Services;

use App\Enums\PersonTypeEnum;
use App\Models\Person;
use App\Models\User;
use App\Traits\NormalizesPhone;
use App\Traits\SearchesFullName;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;

class PersonService
{
    use NormalizesPhone, SearchesFullName;

    /**
     * @param  array{search?: string, type?: string, gender?: string, location_id?: int|string, class_group_id?: int|string, has_phone?: bool|string, per_page?: int|string}  $filters
     */
    public function list(array $filters = []): LengthAwarePaginator
    {
        return $this->filteredQuery($filters)->paginate((int) ($filters['per_page'] ?? 15));
    }

    /**
     * Query partilhada pela listagem e pela exportação.
     *
     * @param  array{search?: string, type?: string, gender?: string, location_id?: int|string, class_group_id?: int|string, has_phone?: bool|string}  $filters
     * @return Builder<Person>
     */
    public function filteredQuery(array $filters = []): Builder
    {
        return Person::query()
            ->with(['location', 'classGroup'])
            ->withCount('worships')
            ->when(
                filled($filters['search'] ?? null),
                fn ($q) => $this->applyPersonSearch($q, $filters['search'])
            )
            ->when(isset($filters['type']), fn ($q) => $q->where('type', $filters['type']))
            ->when(isset($filters['gender']), fn ($q) => $q->where('gender', $filters['gender']))
            ->when(isset($filters['location_id']), fn ($q) => $q->where('location_id', $filters['location_id']))
            ->when(isset($filters['class_group_id']), fn ($q) => $q->where('class_group_id', $filters['class_group_id']))
            ->when(
                filter_var($filters['has_phone'] ?? false, FILTER_VALIDATE_BOOLEAN),
                fn ($q) => $q->whereNotNull('phone')
            )
            ->orderBy('name')
            ->orderBy('surname');
    }

    /**
     * Pesquisa por nome completo (cada palavra no nome ou apelido) ou pelo número.
     * O número só entra com 3+ dígitos, para "Maria" não coincidir com todos os números.
     *
     * @param  Builder<Person>  $query
     */
    public function applyPersonSearch(Builder $query, string $search, string $prefix = ''): void
    {
        $digits = preg_replace('/\D/', '', $search);
        $phone = $prefix ? "{$prefix}.phone" : 'phone';

        $query->where(function (Builder $sub) use ($search, $digits, $prefix, $phone) {
            $sub->where(fn (Builder $names) => $this->applyFullNameSearch($names, $search, $prefix, 'surname'));

            if (strlen($digits) >= 3) {
                $sub->orWhere($phone, 'like', "%{$digits}%");
            }
        });
    }

    /**
     * Procura uma pessoa pelo número, aceitando as variações comuns (84..., 258..., +258...).
     */
    public function findByPhone(string $phone): ?Person
    {
        return Person::where('phone', self::normalizePhone($phone))->first();
    }

    /**
     * @param  array{name: string, surname: string, gender: string, phone?: string|null, class_group_id?: int|null, location_id?: int|null, type?: string}  $data
     */
    public function create(array $data, User $creator): Person
    {
        $type = PersonTypeEnum::from($data['type'] ?? PersonTypeEnum::Visitor->value);

        $person = Person::create([
            'name' => $data['name'],
            'surname' => $data['surname'],
            'gender' => $data['gender'],
            'phone' => $data['phone'] ?? null,
            'location_id' => $data['location_id'] ?? null,
            'class_group_id' => $data['class_group_id'] ?? null,
            'type' => $type->value,
            'became_member_at' => $type === PersonTypeEnum::Member ? now() : null,
            'created_by' => $creator->id,
        ]);

        return $this->loadDetails($person);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Person $person, array $data): Person
    {
        if (isset($data['type'])) {
            $type = PersonTypeEnum::from($data['type']);

            if ($type !== $person->type) {
                $data['became_member_at'] = $type === PersonTypeEnum::Member ? now() : null;
            }
        }

        $person->update($data);

        return $this->loadDetails($person->fresh());
    }

    public function delete(Person $person): void
    {
        $person->delete();
    }

    /**
     * Promove o visitante a membro quando atinge o número mínimo de cultos diferentes.
     * Nunca despromove: um membro mantém-se membro.
     *
     * @return bool true se a pessoa acabou de ser promovida
     */
    public function refreshMembership(Person $person): bool
    {
        if ($person->isMember()) {
            return false;
        }

        if ($person->worships()->count() < Person::MEMBERSHIP_THRESHOLD) {
            return false;
        }

        $person->update([
            'type' => PersonTypeEnum::Member->value,
            'became_member_at' => now(),
        ]);

        return true;
    }

    public function loadDetails(Person $person): Person
    {
        return $person->load(['location', 'classGroup', 'creator'])->loadCount('worships');
    }
}
