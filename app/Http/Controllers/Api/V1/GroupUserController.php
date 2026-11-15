<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Group\GroupUsersRequest;
use App\Http\Resources\GroupResource;
use App\Models\Group;
use App\Models\User;
use App\Services\GroupService;
use App\Traits\ApiResponse;
use DomainException;
use Illuminate\Http\JsonResponse;

class GroupUserController extends Controller
{
    use ApiResponse;

    public function __construct(private readonly GroupService $groupService) {}

    /**
     * Substitui os utilizadores do grupo pela lista enviada.
     */
    public function sync(GroupUsersRequest $request, Group $group): JsonResponse
    {
        $group = $this->groupService->syncUsers($group, $request->validated('user_ids'));

        return $this->success(new GroupResource($group), 'Utilizadores do grupo sincronizados com sucesso.');
    }

    /**
     * Acrescenta utilizadores ao grupo, mantendo os existentes.
     */
    public function attach(GroupUsersRequest $request, Group $group): JsonResponse
    {
        $group = $this->groupService->attachUsers($group, $request->validated('user_ids'));

        return $this->success(new GroupResource($group), 'Utilizadores adicionados ao grupo com sucesso.');
    }

    public function detach(Group $group, User $user): JsonResponse
    {
        try {
            $group = $this->groupService->detachUser($group, $user);
        } catch (DomainException $e) {
            return $this->notFound($e->getMessage());
        }

        return $this->success(new GroupResource($group), 'Utilizador removido do grupo com sucesso.');
    }
}
