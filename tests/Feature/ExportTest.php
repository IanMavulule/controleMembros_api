<?php

use App\Exports\PeopleExport;
use App\Exports\WorshipsExport;
use App\Models\Group;
use App\Models\Person;
use App\Models\Program;
use App\Models\User;
use App\Models\Worship;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\IOFactory;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
    $this->worker = User::factory()->create();

    $this->sunday = Program::factory()->create(['name' => 'Culto de Domingo']);
    $this->youth = Program::factory()->create(['name' => 'Jovens']);

    $group = Group::factory()->create();
    $group->users()->attach($this->worker);
    $group->programs()->attach($this->sunday);

    $this->member = Person::factory()->member()->create(['name' => 'Ana', 'surname' => 'Cossa']);
    $this->visitor = Person::factory()->create(['name' => 'Bruno', 'surname' => 'Langa']);

    $this->sundayWorship = Worship::factory()->for($this->sunday)->create(['date' => '2026-11-08', 'name' => 'Culto de Domingo']);
    $this->youthWorship = Worship::factory()->for($this->youth)->create(['date' => '2026-11-07', 'name' => 'Noite Jovem']);
    $this->sundayWorship->people()->attach([$this->member->id, $this->visitor->id]);
});

/**
 * @return array<int, array<int, mixed>>
 */
function exportedRows(WorshipsExport|PeopleExport $export): array
{
    return $export->collection()->map(fn ($row) => $export->map($row))->all();
}

test('guests cannot export', function () {
    $this->get(route('exports.worships'), ['Accept' => 'application/json'])->assertUnauthorized();
});

test('worships export includes counts and respects the worker access', function () {
    Excel::fake();

    $this->actingAs($this->worker, 'sanctum')->get(route('exports.worships'))->assertOk();

    Excel::assertDownloaded('cultos-'.now()->format('Y-m-d').'.xlsx', function (WorshipsExport $export) {
        $rows = exportedRows($export);

        expect($rows)->toHaveCount(1)
            ->and($rows[0][0])->toBe('08/11/2026')
            ->and($rows[0][1])->toBe('Domingo')
            ->and($rows[0][2])->toBe('Culto de Domingo')
            ->and($rows[0][7])->toBe('Em curso')
            ->and(array_slice($rows[0], 8, 3))->toBe([2, 1, 1]);

        return true;
    });
});

test('admin exports the worships of one program for a period', function () {
    Excel::fake();

    $this->actingAs($this->admin, 'sanctum')
        ->get(route('exports.worships', ['program_id' => $this->youth->id, 'date_from' => '2026-11-01', 'date_to' => '2026-11-30']))
        ->assertOk();

    Excel::assertDownloaded('cultos-jovens-2026-11-01_a_2026-11-30.xlsx', function (WorshipsExport $export) {
        $rows = exportedRows($export);

        return count($rows) === 1 && $rows[0][3] === 'Noite Jovem';
    });
});

test('attendance export lists the people of a worship and honours filters', function () {
    Excel::fake();

    $this->actingAs($this->worker, 'sanctum')
        ->get(route('exports.attendances', [$this->sundayWorship, 'type' => 'member']))
        ->assertOk();

    Excel::assertDownloaded('presentes-culto-de-domingo-2026-11-08.xlsx', function (PeopleExport $export) {
        $rows = exportedRows($export);

        expect($rows)->toHaveCount(1)
            ->and($rows[0][0])->toBe('Ana')
            ->and($rows[0][4])->toBe('Membro')
            ->and($rows[0][5])->toBe($this->member->classGroup->name);

        return true;
    });
});

test('attendance spreadsheet starts with the worship details above the table', function () {
    $this->sundayWorship->update(['title' => 'A fé que move montanhas', 'start_time' => '09:00', 'end_time' => '11:30']);

    $response = $this->actingAs($this->worker, 'sanctum')
        ->get(route('exports.attendances', [$this->sundayWorship, 'type' => 'member']));
    $response->assertOk();

    $sheet = IOFactory::load($response->baseResponse->getFile()->getPathname())->getActiveSheet();
    $info = collect(range(1, 9))->mapWithKeys(fn ($row) => [
        (string) $sheet->getCell("A{$row}")->getValue() => (string) $sheet->getCell("B{$row}")->getValue(),
    ]);

    expect($sheet->getTitle())->toBe('Presentes')
        ->and($sheet->getCell('A1')->getValue())->toBe('Lista de presentes — Culto de Domingo')
        ->and($info['Programa'])->toBe('Culto de Domingo')
        ->and($info['Data'])->toBe('Domingo, 08/11/2026')
        ->and($info['Horário'])->toBe('09:00 – 11:30')
        ->and($info['Estado'])->toBe('Finalizado')
        ->and($info['Tema'])->toBe('A fé que move montanhas')
        ->and($info['Totais'])->toBe('2 presente(s) · 1 membro(s) · 1 visitante(s)')
        ->and($info['Filtros'])->toBe('Tipo: Membro');

    // Bloco de 9 linhas + 1 em branco: a tabela começa na linha 11.
    expect($sheet->getCell('A10')->getValue())->toBeNull()
        ->and($sheet->getCell('A11')->getValue())->toBe('Nome')
        ->and($sheet->getCell('A12')->getValue())->toBe('Ana')
        ->and($sheet->getCell('A13')->getValue())->toBeNull()
        ->and($sheet->getAutoFilter()->getRange())->toStartWith('A11:')
        ->and($sheet->getFreezePane())->toBe('A12');
});

test('worker cannot export the attendance of a worship outside their programs', function () {
    $this->actingAs($this->worker, 'sanctum')
        ->get(route('exports.attendances', $this->youthWorship), ['Accept' => 'application/json'])
        ->assertForbidden();
});

test('people export filters by type without attendance or membership columns', function () {
    Excel::fake();

    $this->actingAs($this->worker, 'sanctum')->get(route('exports.people', ['type' => 'visitor']))->assertOk();

    Excel::assertDownloaded('visitantes-'.now()->format('Y-m-d').'.xlsx', function (PeopleExport $export) {
        $rows = exportedRows($export);

        expect($rows)->toHaveCount(1)
            ->and($rows[0][0])->toBe('Bruno')
            ->and($rows[0][4])->toBe('Visitante')
            ->and($export->headings())->toBe(['Nome', 'Apelido', 'Celular', 'Género', 'Tipo', 'Turma', 'Zona / bairro', 'Registado em']);

        return true;
    });
});

test('the real download is an xlsx file', function () {
    $response = $this->actingAs($this->admin, 'sanctum')->get(route('exports.worships'));

    $response->assertOk()
        ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    expect($response->headers->get('content-disposition'))->toContain('cultos-');
});
