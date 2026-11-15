<?php

namespace App\Exports\Concerns;

use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Cabeçalho da tabela a negrito em fundo preto, fixo ao deslocar e com filtros automáticos.
 * Usar com ShouldAutoSize, WithStyles e WithEvents. Exports com um bloco de informação
 * acima da tabela redefinem headingRow() (e usam WithCustomStartCell).
 */
trait StyledSheet
{
    /**
     * Linha onde fica o cabeçalho da tabela.
     */
    protected function headingRow(): int
    {
        return 1;
    }

    /**
     * Escreve conteúdo extra na folha antes de aplicar o cabeçalho fixo e os filtros.
     */
    protected function decorateSheet(Worksheet $sheet): void {}

    /**
     * @return array<int|string, array<string, mixed>>
     */
    public function styles(Worksheet $sheet): array
    {
        return [
            $this->headingRow() => [
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1C1C1C']],
            ],
        ];
    }

    /**
     * @return array<class-string, callable>
     */
    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $row = $this->headingRow();

                $this->decorateSheet($sheet);
                $sheet->freezePane('A'.($row + 1));
                $sheet->setAutoFilter('A'.$row.':'.$sheet->getHighestColumn().$sheet->getHighestRow());
            },
        ];
    }
}
