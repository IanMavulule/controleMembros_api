<?php

namespace App\Services;

use App\Enums\PersonTypeEnum;
use App\Models\Person;
use App\Models\Program;
use App\Models\Worship;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

/**
 * Estatísticas do dashboard administrativo, agregadas na base de dados.
 * Os filtros de período aplicam-se à data do culto.
 */
class DashboardService
{
    /**
     * Visão geral: pessoas, programas, cultos e presenças, com o resumo por programa.
     *
     * @param  array{date_from?: string, date_to?: string, program_id?: int|string}  $filters
     * @return array<string, mixed>
     */
    public function overview(array $filters): array
    {
        $people = Person::query()
            ->selectRaw('count(*) as total')
            ->selectRaw('sum(case when type = ? then 1 else 0 end) as members', [PersonTypeEnum::Member->value])
            ->selectRaw('sum(case when type = ? then 1 else 0 end) as visitors', [PersonTypeEnum::Visitor->value])
            ->first();

        $newPeople = Person::query()
            ->when($filters['date_from'] ?? null, fn ($q, $from) => $q->whereDate('created_at', '>=', $from))
            ->when($filters['date_to'] ?? null, fn ($q, $to) => $q->whereDate('created_at', '<=', $to))
            ->count();

        $newMembers = Person::query()
            ->whereNotNull('became_member_at')
            ->when($filters['date_from'] ?? null, fn ($q, $from) => $q->whereDate('became_member_at', '>=', $from))
            ->when($filters['date_to'] ?? null, fn ($q, $to) => $q->whereDate('became_member_at', '<=', $to))
            ->count();

        return [
            'people' => [
                'total' => (int) $people->total,
                'members' => (int) $people->members,
                'visitors' => (int) $people->visitors,
                'new_in_period' => $newPeople,
                'new_members_in_period' => $newMembers,
            ],
            'programs' => [
                'total' => Program::count(),
                'active' => Program::where('is_active', true)->count(),
            ],
            'attendance' => $this->attendanceTotals($filters),
            'by_program' => $this->programsBreakdown($filters),
        ];
    }

    /**
     * Estatísticas de um programa e a lista paginada dos seus cultos.
     *
     * @param  array{date_from?: string, date_to?: string, search?: string, per_page?: int|string}  $filters
     * @return array{program: Program, stats: array<string, mixed>, worships: LengthAwarePaginator}
     */
    public function program(Program $program, array $filters): array
    {
        $scoped = [...$filters, 'program_id' => $program->id];

        $worships = Worship::query()
            ->where('program_id', $program->id)
            ->withCount([
                'people as attendances_count',
                'people as members_count' => fn ($q) => $q->where('people.type', PersonTypeEnum::Member->value),
                'people as visitors_count' => fn ($q) => $q->where('people.type', PersonTypeEnum::Visitor->value),
            ])
            ->when($filters['date_from'] ?? null, fn ($q, $from) => $q->where('date', '>=', $from))
            ->when($filters['date_to'] ?? null, fn ($q, $to) => $q->where('date', '<=', $to))
            ->when(
                filled($filters['search'] ?? null),
                fn ($q) => $q->where(fn ($sub) => $sub
                    ->where('name', 'like', "%{$filters['search']}%")
                    ->orWhere('title', 'like', "%{$filters['search']}%"))
            )
            ->orderByDesc('date')
            ->orderByDesc('start_time')
            ->paginate((int) ($filters['per_page'] ?? 15));

        $highest = Worship::query()
            ->where('program_id', $program->id)
            ->withCount('people')
            ->when($filters['date_from'] ?? null, fn ($q, $from) => $q->where('date', '>=', $from))
            ->when($filters['date_to'] ?? null, fn ($q, $to) => $q->where('date', '<=', $to))
            ->orderByDesc('people_count')
            ->first();

        return [
            'program' => $program->loadCount('groups'),
            'stats' => [
                ...$this->attendanceTotals($scoped),
                'highest_attendance' => $highest ? [
                    'worship_id' => $highest->id,
                    'name' => $highest->name,
                    'date' => $highest->date?->format('Y-m-d'),
                    'count' => (int) $highest->people_count,
                ] : null,
            ],
            'worships' => $worships,
        ];
    }

    /**
     * Estatísticas de um culto: tipo, género, turma e quem veio pela primeira vez.
     *
     * @return array<string, mixed>
     */
    public function worship(Worship $worship): array
    {
        $attendees = DB::table('person_worship')
            ->join('people', 'people.id', '=', 'person_worship.person_id')
            ->where('person_worship.worship_id', $worship->id);

        $byType = (clone $attendees)
            ->select('people.type', DB::raw('count(*) as total'))
            ->groupBy('people.type')
            ->pluck('total', 'type');

        $byGender = (clone $attendees)
            ->select('people.gender', DB::raw('count(*) as total'))
            ->groupBy('people.gender')
            ->pluck('total', 'gender');

        // Inclui quem não tem turma (id null → "Sem turma").
        $byClass = (clone $attendees)
            ->leftJoin('class_groups', 'class_groups.id', '=', 'people.class_group_id')
            ->select('class_groups.id', 'class_groups.name', DB::raw('count(*) as total'))
            ->groupBy('class_groups.id', 'class_groups.name')
            ->orderByDesc('total')
            ->get()
            ->map(fn ($row) => [
                'id' => $row->id,
                'name' => $row->name ?? 'Sem turma',
                'total' => (int) $row->total,
            ]);

        return [
            'total' => (int) $byType->sum(),
            'members' => (int) ($byType[PersonTypeEnum::Member->value] ?? 0),
            'visitors' => (int) ($byType[PersonTypeEnum::Visitor->value] ?? 0),
            'first_time' => $this->firstTimeCount($worship),
            'by_gender' => [
                'male' => (int) ($byGender['male'] ?? 0),
                'female' => (int) ($byGender['female'] ?? 0),
            ],
            'by_class_group' => $byClass->values(),
        ];
    }

    /**
     * @param  array{date_from?: string, date_to?: string, program_id?: int|string}  $filters
     * @return array{worships: int, attendances: int, unique_people: int, average_per_worship: float, member_attendances: int, visitor_attendances: int}
     */
    private function attendanceTotals(array $filters): array
    {
        $worships = $this->filteredWorships(DB::table('worships'), $filters)->count();

        $row = $this->filteredWorships(
            DB::table('person_worship')
                ->join('worships', 'worships.id', '=', 'person_worship.worship_id')
                ->join('people', 'people.id', '=', 'person_worship.person_id'),
            $filters
        )
            ->selectRaw('count(*) as attendances')
            ->selectRaw('count(distinct person_worship.person_id) as unique_people')
            ->selectRaw('sum(case when people.type = ? then 1 else 0 end) as member_attendances', [PersonTypeEnum::Member->value])
            ->selectRaw('sum(case when people.type = ? then 1 else 0 end) as visitor_attendances', [PersonTypeEnum::Visitor->value])
            ->first();

        $attendances = (int) $row->attendances;

        return [
            'worships' => $worships,
            'attendances' => $attendances,
            'unique_people' => (int) $row->unique_people,
            'average_per_worship' => $worships ? round($attendances / $worships, 1) : 0.0,
            'member_attendances' => (int) $row->member_attendances,
            'visitor_attendances' => (int) $row->visitor_attendances,
        ];
    }

    /**
     * Todos os programas (mesmo sem cultos no período) com cultos, presenças e média.
     *
     * @param  array{date_from?: string, date_to?: string, program_id?: int|string}  $filters
     * @return array<int, array<string, mixed>>
     */
    private function programsBreakdown(array $filters): array
    {
        $totals = $this->filteredWorships(
            DB::table('worships')->leftJoin('person_worship', 'person_worship.worship_id', '=', 'worships.id'),
            $filters
        )
            ->select('worships.program_id')
            ->selectRaw('count(distinct worships.id) as worships')
            ->selectRaw('count(person_worship.id) as attendances')
            ->selectRaw('max(worships.date) as last_worship_date')
            ->groupBy('worships.program_id')
            ->get()
            ->keyBy('program_id');

        return Program::query()
            ->when($filters['program_id'] ?? null, fn ($q, $id) => $q->whereKey($id))
            ->orderBy('name')
            ->get()
            ->map(function (Program $program) use ($totals) {
                $row = $totals->get($program->id);
                $worships = (int) ($row->worships ?? 0);
                $attendances = (int) ($row->attendances ?? 0);

                return [
                    'id' => $program->id,
                    'name' => $program->name,
                    'is_active' => $program->is_active,
                    'worships' => $worships,
                    'attendances' => $attendances,
                    'average_per_worship' => $worships ? round($attendances / $worships, 1) : 0.0,
                    'last_worship_date' => $row->last_worship_date ?? null,
                ];
            })
            ->sortByDesc('attendances')
            ->values()
            ->all();
    }

    /**
     * Pessoas para quem este foi o primeiro culto (nenhuma presença num culto anterior).
     */
    private function firstTimeCount(Worship $worship): int
    {
        $date = $worship->date->format('Y-m-d');

        return DB::table('person_worship as current')
            ->where('current.worship_id', $worship->id)
            ->whereNotExists(function (QueryBuilder $earlier) use ($worship, $date) {
                $earlier->select(DB::raw(1))
                    ->from('person_worship as previous')
                    ->join('worships as previous_worship', 'previous_worship.id', '=', 'previous.worship_id')
                    ->whereColumn('previous.person_id', 'current.person_id')
                    ->where('previous.worship_id', '!=', $worship->id)
                    ->where(fn (QueryBuilder $q) => $q
                        ->where('previous_worship.date', '<', $date)
                        ->orWhere(fn (QueryBuilder $same) => $same
                            ->where('previous_worship.date', $date)
                            ->where('previous_worship.start_time', '<', $worship->start_time)));
            })
            ->count();
    }

    /**
     * @param  array{date_from?: string, date_to?: string, program_id?: int|string}  $filters
     */
    private function filteredWorships(QueryBuilder $query, array $filters): QueryBuilder
    {
        return $query
            ->when($filters['date_from'] ?? null, fn ($q, $from) => $q->where('worships.date', '>=', $from))
            ->when($filters['date_to'] ?? null, fn ($q, $to) => $q->where('worships.date', '<=', $to))
            ->when($filters['program_id'] ?? null, fn ($q, $id) => $q->where('worships.program_id', $id));
    }
}
