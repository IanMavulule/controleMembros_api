<?php

namespace App\Http\Controllers\Api\V1;

use App\Exports\AttendancesExport;
use App\Exports\PeopleExport;
use App\Exports\WorshipsExport;
use App\Http\Controllers\Controller;
use App\Models\Program;
use App\Models\Worship;
use App\Services\AttendanceService;
use App\Services\PersonService;
use App\Services\WorshipService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Exportações em Excel. Usam as mesmas queries (filtros e acesso) das listagens.
 */
class ExportController extends Controller
{
    public function __construct(
        private readonly WorshipService $worshipService,
        private readonly PersonService $personService,
        private readonly AttendanceService $attendanceService,
    ) {}

    /**
     * Cultos visíveis ao utilizador; com program_id torna-se a lista de cultos de um programa.
     */
    public function worships(Request $request): BinaryFileResponse
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'program_id' => ['nullable', 'integer'],
            'date' => ['nullable', 'date_format:Y-m-d'],
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d'],
        ]);
        $filters = array_filter($filters, fn ($value) => filled($value));

        $program = isset($filters['program_id']) ? Program::find($filters['program_id']) : null;
        $name = $program ? "cultos-{$program->code}" : 'cultos';

        return Excel::download(
            new WorshipsExport($this->worshipService->filteredQuery($request->user(), $filters)),
            $this->fileName($name, $filters),
        );
    }

    /**
     * Presentes de um culto (mesmos filtros da lista de presenças).
     */
    public function attendances(Request $request, Worship $worship): BinaryFileResponse
    {
        Gate::authorize('view', $worship);

        $filters = array_filter($request->only(['search', 'type', 'gender', 'class_group_id']), fn ($value) => filled($value));
        $date = $worship->date->format('Y-m-d');

        return Excel::download(
            new AttendancesExport(
                $worship,
                $this->attendanceService->filteredQuery($worship, $filters),
                $this->attendanceService->summary($worship),
                $filters,
            ),
            "presentes-{$worship->program->code}-{$date}.xlsx",
        );
    }

    public function people(Request $request): BinaryFileResponse
    {
        $filters = array_filter(
            $request->only(['search', 'type', 'gender', 'location_id', 'province_id', 'class_group_id']),
            fn ($value) => filled($value)
        );
        $name = match ($filters['type'] ?? null) {
            'member' => 'membros',
            'visitor' => 'visitantes',
            default => 'pessoas',
        };

        return Excel::download(new PeopleExport($this->personService->filteredQuery($filters)), $this->fileName($name, []));
    }

    /**
     * @param  array<string, string>  $filters
     */
    private function fileName(string $name, array $filters): string
    {
        $period = match (true) {
            isset($filters['date_from'], $filters['date_to']) => "{$filters['date_from']}_a_{$filters['date_to']}",
            isset($filters['date_from']) => "desde-{$filters['date_from']}",
            isset($filters['date_to']) => "ate-{$filters['date_to']}",
            isset($filters['date']) => $filters['date'],
            default => now()->format('Y-m-d'),
        };

        return "{$name}-{$period}.xlsx";
    }
}
