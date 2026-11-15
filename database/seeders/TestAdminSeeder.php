<?php

namespace Database\Seeders;

use App\Enums\RoleEnum;
use App\Models\User;
use App\Services\UserService;
use Illuminate\Database\Seeder;

/**
 * Cria um administrador de teste para desenvolvimento local.
 * Login: phone + password abaixo. Só corre em ambiente local/testing.
 *
 * php artisan db:seed --class=TestAdminSeeder
 */
class TestAdminSeeder extends Seeder
{
    public const PHONE = '+258840000002';

    public const PASSWORD = 'AdminTeste123';

    public function run(UserService $userService): void
    {
        if (! app()->environment(['local', 'testing'])) {
            $this->command?->error('O TestAdminSeeder só pode correr em ambiente local.');

            return;
        }

        $admin = User::firstOrNew(['phone' => self::PHONE]);

        $admin->fill([
            'role' => RoleEnum::Admin->value,
            'name' => 'Admin',
            'lastname' => 'Teste',
            'email' => 'admin.teste@controlemembros.test',
            'password' => self::PASSWORD,
            'is_active' => true,
        ]);

        if (! $admin->exists) {
            $admin->user_code = $userService->generateUserCode(RoleEnum::Admin);
        }

        $admin->save();

        $this->command?->info("Admin de teste pronto: {$admin->phone} ({$admin->user_code}). Password em TestAdminSeeder::PASSWORD.");
    }
}
