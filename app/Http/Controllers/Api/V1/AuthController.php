<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ChangePasswordRequest;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\SetupAdminRequest;
use App\Http\Requests\Auth\UpdateMeRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\UserService;
use App\Traits\ApiResponse;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\PersonalAccessToken;

class AuthController extends Controller
{
    use ApiResponse;

    private const TOKEN_TTL_HOURS = 8;

    public function __construct(private readonly UserService $userService) {}

    /**
     * Cria a conta inicial do administrador (disponível apenas enquanto não existir nenhum utilizador).
     */
    public function setupAdmin(SetupAdminRequest $request): JsonResponse
    {
        try {
            $admin = $this->userService->createFirstAdmin($request->validated());
        } catch (DomainException) {
            return $this->error('Configuração inicial já concluída. Este endpoint não está mais disponível.', 403);
        }

        return $this->created(
            $this->issueToken($admin),
            'Conta de administrador criada com sucesso.'
        );
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $user = User::where('phone', $request->validated('phone'))->first();

        if (! $user || ! Hash::check($request->validated('password'), $user->password)) {
            return $this->error('Credenciais inválidas.', 401);
        }

        if (! $user->is_active) {
            return $this->forbidden('A sua conta está desactivada. Contacte o administrador.');
        }

        $user->tokens()->delete();

        return $this->success($this->issueToken($user), 'Login efectuado com sucesso.');
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return $this->success(message: 'Sessão terminada com sucesso.');
    }

    public function me(Request $request): JsonResponse
    {
        return $this->success(new UserResource($request->user()));
    }

    public function updateMe(UpdateMeRequest $request): JsonResponse
    {
        $user = $request->user();
        $user->update($request->validated());

        return $this->success(new UserResource($user->refresh()), 'Perfil actualizado com sucesso.');
    }

    public function changePassword(ChangePasswordRequest $request): JsonResponse
    {
        $user = $request->user();
        $user->update(['password' => Hash::make($request->validated('password'))]);

        $current = $user->currentAccessToken();
        $currentId = $current instanceof PersonalAccessToken ? $current->getKey() : null;
        $user->tokens()->when($currentId, fn ($tokens) => $tokens->whereKeyNot($currentId))->delete();

        return $this->success(null, 'Palavra-passe alterada com sucesso.');
    }

    /**
     * @return array{user: UserResource, token: string, expires_at: string}
     */
    private function issueToken(User $user): array
    {
        $expiresAt = now()->addHours(self::TOKEN_TTL_HOURS);

        return [
            'user' => new UserResource($user),
            'token' => $user->createToken('api-token', ['*'], $expiresAt)->plainTextToken,
            'expires_at' => $expiresAt->toDateTimeString(),
        ];
    }
}
