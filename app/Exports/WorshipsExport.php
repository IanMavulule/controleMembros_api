<?php

namespace App\Exports;

use App\Exports\Concerns\StyledSheet;
use App\Models\Worship;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;

/**
 * Lista de cultos com totais de presentes, membros e visitantes.
 *
 * @implements WithMapping<Worship>
 */
class WorshipsExport implements FromCollection, ShouldAutoSize, WithEvents, WithHeadings, WithMapping, WithStyles, WithTitle
{
    use StyledSheet;

    private const WEEKDAYS = ['Domingo', 'Segunda', 'Terça', 'Quarta', 'Quinta', 'Sexta', 'Sábado'];

    /**
     * @param  Builder<Worship>  $query  Já filtrada e com as contagens (WorshipService::filteredQuery).
     */
    public function __construct(private readonly Builder $query) {}

    public function collection(): Collection
    {
        return $this->query->get();
    }

    public function title(): string
    {
        return 'Cultos';
    }

    /**
     * @return array<int, string>
     */
    public function headings(): array
    {
        return ['Data', 'Dia', 'Programa', 'Culto', 'Tema', 'Início', 'Fim', 'Estado', 'Presentes', 'Membros', 'Visitantes', 'Criado por'];
    }

    /**
     * @param  Worship  $worship
     * @return array<int, mixed>
     */
    public function map($worship): array
    {
        return [
            $worship->date->format('d/m/Y'),
            self::WEEKDAYS[$worship->date->dayOfWeek],
            $worship->program?->name,
            $worship->name,
            $worship->title,
            substr($worship->start_time, 0, 5),
            $worship->end_time ? substr($worship->end_time, 0, 5) : '',
            $worship->isFinished() ? 'Finalizado' : 'Em curso',
            (int) $worship->attendances_count,
            (int) $worship->members_count,
            (int) $worship->visitors_count,
            $worship->creator?->full_name,
        ];
    }
}
