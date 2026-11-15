<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Program\StoreProgramRequest;
use App\Http\Requests\Program\UpdateProgramRequest;
use App\Http\Resources\ProgramResource;
use App\Models\Program;
use App\Services\ProgramService;
use App\Traits\ApiResponse;
use DomainException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProgramController extends Controller
{
    use ApiResponse;

    public function __construct(private readonly ProgramService $programService) {}

    public function index(Request $request): JsonResponse
    {
        $programs = $this->programService->list(
            $request->user(),
            $request->only(['search', 'is_active', 'per_page'])
        );

        return ProgramResource::collection($programs)
            ->additional(['success' => true, 'message' => 'Sucesso'])
            ->response();
    }

    public function store(StoreProgramRequest $request): JsonResponse
    {
        try {
            $program = $this->programService->create($request->validated());
        } catch (UniqueConstraintViolationException) {
            return $this->duplicateNameError();
        }

        return $this->created(new ProgramResource($program), 'Programa criado com sucesso.');
    }

    public function show(Request $request, Program $program): JsonResponse
    {
        if (! $request->user()->canAccessProgram($program->id)) {
            return $this->forbidden('Não tem acesso a este programa.');
        }

        return $this->success(new ProgramResource($program));
    }

    public function update(UpdateProgramRequest $request, Program $program): JsonResponse
    {
        try {
            $updated = $this->programService->update($program, $request->validated());
        } catch (UniqueConstraintViolationException) {
            return $this->duplicateNameError();
        }

        return $this->success(new ProgramResource($updated), 'Programa actualizado com sucesso.');
    }

    public function destroy(Program $program): JsonResponse
    {
        try {
            $this->programService->delete($program);
        } catch (DomainException $e) {
            return $this->error($e->getMessage(), 422);
        }

        return $this->success(message: 'Programa eliminado com sucesso.');
    }

    /**
     * Resposta para o caso raro de dois pedidos simultâneos passarem a validação
     * com o mesmo nome: o índice único do code trava o segundo.
     */
    private function duplicateNameError(): JsonResponse
    {
        return $this->error('Já existe um programa com este nome.', 422, [
            'name' => ['Já existe um programa com este nome.'],
        ]);
    }
}
