<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Group\StoreGroupRequest;
use App\Http\Requests\Group\UpdateGroupRequest;
use App\Http\Resources\GroupResource;
use App\Models\Group;
use App\Services\GroupService;
use App\Traits\ApiResponse;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GroupController extends Controller
{
    use ApiResponse;

    public function __construct(private readonly GroupService $groupService) {}

    public function index(Request $request): JsonResponse
    {
        $groups = $this->groupService->list(
            $request->only(['search', 'is_active', 'user_id', 'program_id', 'per_page'])
        );

        return GroupResource::collection($groups)
            ->additional(['success' => true, 'message' => 'Sucesso'])
            ->response();
    }

    public function store(StoreGroupRequest $request): JsonResponse
    {
        try {
            $group = $this->groupService->create($request->validated());
        } catch (UniqueConstraintViolationException) {
            return $this->duplicateNameError();
        }

        return $this->created(new GroupResource($group), 'Grupo criado com sucesso.');
    }

    public function show(Group $group): JsonResponse
    {
        return $this->success(new GroupResource($this->groupService->loadDetails($group)));
    }

    public function update(UpdateGroupRequest $request, Group $group): JsonResponse
    {
        try {
            $updated = $this->groupService->update($group, $request->validated());
        } catch (UniqueConstraintViolationException) {
            return $this->duplicateNameError();
        }

        return $this->success(new GroupResource($updated), 'Grupo actualizado com sucesso.');
    }

    public function destroy(Group $group): JsonResponse
    {
        $this->groupService->delete($group);

        return $this->success(message: 'Grupo eliminado com sucesso.');
    }

    /**
     * Resposta para o caso raro de dois pedidos simultâneos passarem a validação
     * com o mesmo nome: o índice único do code trava o segundo.
     */
    private function duplicateNameError(): JsonResponse
    {
        return $this->error('Já existe um grupo com este nome.', 422, [
            'name' => ['Já existe um grupo com este nome.'],
        ]);
    }
}
