<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\ClassGroup\StoreClassGroupRequest;
use App\Http\Requests\ClassGroup\UpdateClassGroupRequest;
use App\Http\Resources\ClassGroupResource;
use App\Models\ClassGroup;
use App\Services\ClassGroupService;
use App\Traits\ApiResponse;
use DomainException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ClassGroupController extends Controller
{
    use ApiResponse;

    public function __construct(private readonly ClassGroupService $classGroupService) {}

    public function index(Request $request): JsonResponse
    {
        return $this->success(ClassGroupResource::collection(
            $this->classGroupService->list($request->only(['search']))
        ));
    }

    public function store(StoreClassGroupRequest $request): JsonResponse
    {
        try {
            $classGroup = $this->classGroupService->create($request->validated());
        } catch (UniqueConstraintViolationException) {
            return $this->duplicateNameError();
        }

        return $this->created(new ClassGroupResource($classGroup), 'Turma criada com sucesso.');
    }

    public function show(ClassGroup $classGroup): JsonResponse
    {
        return $this->success(new ClassGroupResource($classGroup->loadCount('people')));
    }

    public function update(UpdateClassGroupRequest $request, ClassGroup $classGroup): JsonResponse
    {
        try {
            $updated = $this->classGroupService->update($classGroup, $request->validated());
        } catch (UniqueConstraintViolationException) {
            return $this->duplicateNameError();
        }

        return $this->success(new ClassGroupResource($updated), 'Turma actualizada com sucesso.');
    }

    public function destroy(ClassGroup $classGroup): JsonResponse
    {
        try {
            $this->classGroupService->delete($classGroup);
        } catch (DomainException $e) {
            return $this->error($e->getMessage(), 422);
        }

        return $this->success(message: 'Turma eliminada com sucesso.');
    }

    private function duplicateNameError(): JsonResponse
    {
        return $this->error('Já existe uma turma com este nome.', 422, [
            'name' => ['Já existe uma turma com este nome.'],
        ]);
    }
}
