<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\User\GenerateTestPasswordRequest;
use App\Http\Requests\User\ResetPasswordRequest;
use App\Http\Requests\User\StoreUserRequest;
use App\Http\Requests\User\UpdateUserRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\UserService;
use App\Traits\ApiResponse;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserController extends Controller
{
    use ApiResponse;

    public function __construct(private readonly UserService $userService) {}

    public function index(Request $request): JsonResponse
    {
        $users = $this->userService->list(
            $request->only(['search', 'role', 'is_active', 'per_page'])
        );

        return UserResource::collection($users)
            ->additional([
                'success' => true,
                'message' => 'Sucesso',
                'test_credentials_enabled' => $request->user()->isAdmin() && $this->userService->canUseTestCredentials(),
            ])
            ->response();
    }

    public function store(StoreUserRequest $request): JsonResponse
    {
        $user = $this->userService->create($request->validated());
        $warning = $this->userService->getLastWarning();
        $credentials = $this->userService->pullTestCredentials();
        $message = $credentials !== null
            ? 'Utilizador criado. As credenciais estão disponíveis para os testes.'
            : ($warning ?? 'Utilizador criado. As credenciais foram enviadas por email.');

        return (new UserResource($user))
            ->additional([
                'success' => true,
                'message' => $message,
                'email_delivery' => ['status' => $credentials !== null ? 'skipped' : ($warning === null ? 'sent' : 'failed')],
                'warning' => $warning,
                ...($credentials !== null ? ['credentials' => $credentials] : []),
            ])
            ->response()
            ->setStatusCode(201)
            ->header('Cache-Control', 'no-store, private')
            ->header('Pragma', 'no-cache');
    }

    public function show(User $user): JsonResponse
    {
        return $this->success(new UserResource($user));
    }

    public function update(UpdateUserRequest $request, User $user): JsonResponse
    {
        try {
            $updated = $this->userService->update($user, $request->validated(), $request->user());
        } catch (DomainException $e) {
            return $this->error($e->getMessage(), 422);
        }

        return $this->success(new UserResource($updated), 'Utilizador actualizado com sucesso.');
    }

    public function destroy(Request $request, User $user): JsonResponse
    {
        try {
            $this->userService->delete($user, $request->user());
        } catch (DomainException $e) {
            return $this->error($e->getMessage(), 422);
        }

        return $this->success(message: 'Utilizador eliminado com sucesso.');
    }

    public function resetPassword(ResetPasswordRequest $request, User $user): JsonResponse
    {
        $this->userService->resetPassword($user, $request->validated('password'));

        return $this->success(message: 'Password redefinida com sucesso. O utilizador deve iniciar sessão novamente.');
    }

    public function generateTestPassword(GenerateTestPasswordRequest $request, User $user): JsonResponse
    {
        $credentials = $this->userService->generateTestPassword($user);

        return $this->success(['credentials' => $credentials], 'Nova palavra-passe gerada para testes.')
            ->header('Cache-Control', 'no-store, private')
            ->header('Pragma', 'no-cache');
    }
}
