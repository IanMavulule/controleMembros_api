<?php

namespace App\Exports;

use App\Enums\GenderEnum;
use App\Enums\PersonTypeEnum;
use App\Models\ClassGroup;
use App\Models\Person;
use App\Models\Worship;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Maatwebsite\Excel\Concerns\WithCustomStartCell;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Presentes de um culto, com os dados do culto num bloco de título acima da tabela.
 */
class AttendancesExport extends PeopleExport implements WithCustomStartCell
{
    private const WEEKDAYS = ['Domingo', 'Segunda-feira', 'Terça-feira', 'Quarta-feira', 'Quinta-feira', 'Sexta-feira', 'Sábado'];

    /**
     * Linhas do bloco de informação: [rótulo, valor]. A primeira é o título.
     *
     * @var array<int, array{0: string, 1: string}>
     */
    private array $infoLines;

    /**
     * @param  BelongsToMany<Person, Worship>  $query
     * @param  array{total: int, visitors: int, members: int}  $summary  Totais do culto inteiro.
     * @param  array{search?: string, type?: string, gender?: string, class_group_id?: int|string}  $filters
     */
    public function __construct(
        private readonly Worship $worship,
        BelongsToMany $query,
        private readonly array $summary,
        private readonly array $filters = [],
    ) {
        parent::__construct($query, 'Presentes', withRegisteredAt: true);

        $this->infoLines = $this->buildInfoLines();
    }

    public function startCell(): string
    {
        return 'A'.$this->headingRow();
    }

    /**
     * O cabeçalho da tabela fica depois do bloco de informação e de uma linha em branco.
     */
    protected function headingRow(): int
    {
        return count($this->infoLines) + 2;
    }

    protected function decorateSheet(Worksheet $sheet): void
    {
        $lastColumn = Coordinate::stringFromColumnIndex(count($this->headings()));

        foreach ($this->infoLines as $index => [$label, $value]) {
            $row = $index + 1;

            if ($index === 0) {
                $sheet->setCellValue("A{$row}", $value);
                $sheet->mergeCells("A{$row}:{$lastColumn}{$row}");
                $sheet->getStyle("A{$row}")->getFont()->setBold(true)->setSize(14);
                $sheet->getStyle("A{$row}:{$lastColumn}{$row}")->getFill()
                    ->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('F4F5F7');

                continue;
            }

            $sheet->setCellValueExplicit("A{$row}", $label, 's');
            $sheet->setCellValueExplicit("B{$row}", $value, 's');
            $sheet->mergeCells("B{$row}:{$lastColumn}{$row}");
            $sheet->getStyle("A{$row}")->getFont()->setBold(true);
        }
    }

    /**
     * @return array<int, array{0: string, 1: string}>
     */
    private function buildInfoLines(): array
    {
        $worship = $this->worship->loadMissing('program');
        $date = $worship->date;
        $start = substr($worship->start_time, 0, 5);
        $end = $worship->end_time ? substr($worship->end_time, 0, 5) : null;

        $lines = [
            ['', "Lista de presentes — {$worship->name}"],
            ['Programa', (string) $worship->program?->name],
            ['Data', self::WEEKDAYS[$date->dayOfWeek].', '.$date->format('d/m/Y')],
            ['Horário', $end ? "{$start} – {$end}" : "{$start} (em curso)"],
            ['Estado', $worship->isFinished() ? 'Finalizado' : 'Em curso'],
        ];

        if (filled($worship->title)) {
            $lines[] = ['Tema', $worship->title];
        }

        $lines[] = ['Totais', sprintf(
            '%d presente(s) · %d membro(s) · %d visitante(s)',
            $this->summary['total'],
            $this->summary['members'],
            $this->summary['visitors'],
        )];

        if ($filters = $this->describeFilters()) {
            $lines[] = ['Filtros', $filters];
        }

        $lines[] = ['Exportado em', now()->format('d/m/Y H:i')];

        return $lines;
    }

    private function describeFilters(): string
    {
        $parts = [];

        if (filled($this->filters['type'] ?? null) && $type = PersonTypeEnum::tryFrom($this->filters['type'])) {
            $parts[] = 'Tipo: '.$type->label();
        }

        if (filled($this->filters['gender'] ?? null) && $gender = GenderEnum::tryFrom($this->filters['gender'])) {
            $parts[] = 'Género: '.$gender->label();
        }

        if (filled($this->filters['class_group_id'] ?? null)) {
            $parts[] = 'Turma: '.(ClassGroup::find($this->filters['class_group_id'])?->name ?? '—');
        }

        if (filled($this->filters['search'] ?? null)) {
            $parts[] = "Pesquisa: \"{$this->filters['search']}\"";
        }

        return implode(' · ', $parts);
    }
}
