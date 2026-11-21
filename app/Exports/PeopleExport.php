<?php

namespace App\Exports;

use App\Exports\Concerns\StyledSheet;
use App\Models\Person;
use App\Models\Worship;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;

/**
 * Lista de pessoas: geral (PersonService::filteredQuery) ou presentes de um culto
 * (AttendanceService::filteredQuery), que acrescenta a hora do registo.
 *
 * @implements WithMapping<Person>
 */
class PeopleExport implements FromCollection, ShouldAutoSize, WithEvents, WithHeadings, WithMapping, WithStyles, WithTitle
{
    use StyledSheet;

    /**
     * @param  Builder<Person>|BelongsToMany<Person, Worship>  $query
     */
    public function __construct(
        private readonly Builder|BelongsToMany $query,
        private readonly string $sheetTitle = 'Pessoas',
        private readonly bool $withRegisteredAt = false,
    ) {}

    public function collection(): Collection
    {
        return $this->query->get();
    }

    public function title(): string
    {
        return $this->sheetTitle;
    }

    /**
     * @return array<int, string>
     */
    public function headings(): array
    {
        return [
            'Nome', 'Apelido', 'Celular', 'Género', 'Tipo', 'Turma', 'Zona / bairro',
            ...($this->withRegisteredAt ? ['Registado no culto'] : ['Registado em']),
        ];
    }

    /**
     * @param  Person  $person
     * @return array<int, mixed>
     */
    public function map($person): array
    {
        return [
            $person->name,
            $person->surname,
            $person->phone ?? '',
            $person->gender->label(),
            $person->type->label(),
            $person->classGroup?->name ?? 'Sem turma',
            $person->location?->name ?? '',
            $this->withRegisteredAt
                ? $person->pivot?->created_at?->format('d/m/Y H:i')
                : $person->created_at?->format('d/m/Y H:i'),
        ];
    }
}
