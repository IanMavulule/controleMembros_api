<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\User\SyncUserGroupsRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\GroupService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;

class UserGroupController extends Controller
{
    use ApiResponse;

    public function __construct(private readonly GroupService $groupService) {}

    /**
     * Substitui os grupos do utilizador pela lista enviada.
     */
    public function sync(SyncUserGroupsRequest $request, User $user): JsonResponse
    {
        $user = $this->groupService->syncUserGroups($user, $request->validated('group_ids'));

        return $this->success(new UserResource($user), 'Grupos do utilizador sincronizados com sucesso.');
    }
}
