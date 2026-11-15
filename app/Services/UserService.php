<?php

namespace App\Services;

use App\Enums\RoleEnum;
use App\Models\User;
use App\Traits\SearchesFullName;
use DomainException;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class UserService
{
    use SearchesFullName;

    private ?string $lastWarning = null;

    /**
     * @var array{phone: string, password: string}|null
     */
    private ?array $lastCredentials = null;

    public function __construct(private readonly EmailService $emailService) {}

    /**
     * @param  array{search?: string, role?: string, is_active?: bool|string, per_page?: int|string}  $filters
     */
    public function list(array $filters = []): LengthAwarePaginator
    {
        return User::query()
            ->when(
                isset($filters['search']),
                fn ($q) => $q->where(function ($sub) use ($filters) {
                    $sub->where(fn ($n) => $this->applyFullNameSearch($n, $filters['search']))
                        ->orWhere('email', 'like', "%{$filters['search']}%")
                        ->orWhere('phone', 'like', "%{$filters['search']}%")
                        ->orWhere('user_code', 'like', "%{$filters['search']}%");
                })
            )
            ->when(
                isset($filters['role']),
                fn ($q) => $q->where('role', $filters['role'])
            )
            ->when(
                isset($filters['is_active']),
                fn ($q) => $q->where('is_active', filter_var($filters['is_active'], FILTER_VALIDATE_BOOLEAN))
            )
            ->orderBy('name')
            ->paginate((int) ($filters['per_page'] ?? 15));
    }

    /**
     * Cria o primeiro administrador do sistema (configuração inicial).
     *
     * @param  array{name: string, lastname: string, phone: string, email: string, password: string}  $data
     */
    public function createFirstAdmin(array $data): User
    {
        if (User::exists()) {
            throw new DomainException('Configuração inicial já concluída.');
        }

        return User::create([
            'user_code' => $this->generateUserCode(RoleEnum::Admin),
            'role' => RoleEnum::Admin->value,
            'name' => $data['name'],
            'lastname' => $data['lastname'],
            'phone' => $data['phone'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'is_active' => true,
        ]);
    }

    /**
     * Cria um utilizador com password gerada, enviada por email
     * (ou devolvida como credencial de teste em ambiente local).
     *
     * @param  array{name: string, lastname: string, phone: string, email: string, role: string}  $data
     */
    public function create(array $data): User
    {
        $this->lastWarning = null;
        $this->lastCredentials = null;
        $role = RoleEnum::from($data['role']);
        $password = $this->generatePassword($data['name']);

        $user = User::create([
            'user_code' => $this->generateUserCode($role),
            'role' => $role->value,
            'name' => $data['name'],
            'lastname' => $data['lastname'],
            'phone' => $data['phone'],
            'email' => $data['email'],
            'password' => Hash::make($password),
            'is_active' => true,
        ]);

        if ($this->canUseTestCredentials()) {
            $this->lastCredentials = ['phone' => $user->phone, 'password' => $password];

            return $user;
        }

        try {
            $this->emailService->sendWelcomeEmail($user->email, $user->full_name, $user->phone, $password);
            Log::info('Email de boas-vindas aceite pelo serviço de email.', ['user_id' => $user->id]);
        } catch (Throwable $e) {
            $this->lastWarning = 'Utilizador criado, mas não foi possível enviar as credenciais por email. Redefina a password do utilizador para recuperar o acesso. Não é necessário criar o utilizador novamente.';
            Log::warning('Falha ao enviar email de boas-vindas.', [
                'user_id' => $user->id,
                'mailer' => config('mail.default'),
                'exception_type' => $e::class,
            ]);
        }

        return $user;
    }

    public function getLastWarning(): ?string
    {
        return $this->lastWarning;
    }

    public function canUseTestCredentials(): bool
    {
        return app()->environment(['local', 'testing']) && config('auth.show_test_credentials', false);
    }

    /**
     * @return array{phone: string, password: string}|null
     */
    public function pullTestCredentials(): ?array
    {
        $credentials = $this->canUseTestCredentials() ? $this->lastCredentials : null;
        $this->lastCredentials = null;

        return $credentials;
    }

    /**
     * @return array{phone: string, password: string}
     */
    public function generateTestPassword(User $user): array
    {
        abort_unless($this->canUseTestCredentials(), 403);
        $password = $this->generatePassword($user->name);
        $this->resetPassword($user, $password);

        return ['phone' => $user->phone, 'password' => $password];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(User $user, array $data, User $authUser): User
    {
        if ($user->id === $authUser->id && (
            (isset($data['role']) && $data['role'] !== $user->role->value)
            || (array_key_exists('is_active', $data) && ! $data['is_active'])
        )) {
            throw new DomainException('Não pode alterar o seu próprio perfil de acesso nem desactivar a sua conta.');
        }

        $updateData = array_intersect_key($data, array_flip(['name', 'lastname', 'phone', 'email', 'role', 'is_active']));

        if (isset($data['role']) && $data['role'] !== $user->role->value) {
            $updateData['user_code'] = $this->generateUserCode(RoleEnum::from($data['role']));
        }

        $user->update($updateData);

        if (array_key_exists('is_active', $updateData) && ! $updateData['is_active']) {
            $user->tokens()->delete();
        }

        return $user->fresh();
    }

    public function delete(User $user, User $authUser): void
    {
        if ($user->id === $authUser->id) {
            throw new DomainException('Não pode eliminar a sua própria conta.');
        }

        $user->tokens()->delete();
        $user->delete();
    }

    public function resetPassword(User $user, string $newPassword): void
    {
        $user->update(['password' => Hash::make($newPassword)]);
        $user->tokens()->delete();
    }

    /**
     * Password fácil de ditar e escrever: primeiro nome + 4 dígitos aleatórios.
     * "Maria José" → "Maria4821", "João" → "Joao0937".
     */
    public function generatePassword(string $name): string
    {
        $firstName = Str::of($name)->ascii()->squish()->before(' ')->replaceMatches('/[^A-Za-z]/', '')->lower()->ucfirst();
        $base = $firstName->isEmpty() ? 'Membro' : (string) $firstName;

        return $base.str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT);
    }

    public function generateUserCode(RoleEnum $role): string
    {
        do {
            $code = $role->codePrefix().'-'.strtoupper(Str::random(6));
        } while (User::where('user_code', $code)->exists());

        return $code;
    }
}
