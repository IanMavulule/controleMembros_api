<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Group\GroupProgramsRequest;
use App\Http\Resources\GroupResource;
use App\Models\Group;
use App\Models\Program;
use App\Services\GroupService;
use App\Traits\ApiResponse;
use DomainException;
use Illuminate\Http\JsonResponse;

class GroupProgramController extends Controller
{
    use ApiResponse;

    public function __construct(private readonly GroupService $groupService) {}

    /**
     * Substitui os programas do grupo pela lista enviada.
     */
    public function sync(GroupProgramsRequest $request, Group $group): JsonResponse
    {
        $group = $this->groupService->syncPrograms($group, $request->validated('program_ids'));

        return $this->success(new GroupResource($group), 'Programas do grupo sincronizados com sucesso.');
    }

    /**
     * Acrescenta programas ao grupo, mantendo os existentes.
     */
    public function attach(GroupProgramsRequest $request, Group $group): JsonResponse
    {
        $group = $this->groupService->attachPrograms($group, $request->validated('program_ids'));

        return $this->success(new GroupResource($group), 'Programas adicionados ao grupo com sucesso.');
    }

    public function detach(Group $group, Program $program): JsonResponse
    {
        try {
            $group = $this->groupService->detachProgram($group, $program);
        } catch (DomainException $e) {
            return $this->notFound($e->getMessage());
        }

        return $this->success(new GroupResource($group), 'Programa removido do grupo com sucesso.');
    }
}
