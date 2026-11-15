<?php

namespace Database\Seeders;

use App\Enums\RoleEnum;
use App\Models\User;
use App\Services\UserService;
use Illuminate\Database\Seeder;

/**
 * Cria um obreiro (role worker) de teste para desenvolvimento local.
 * Login: phone + password abaixo. Só corre em ambiente local/testing.
 *
 * php artisan db:seed --class=TestWorkerSeeder
 */
class TestWorkerSeeder extends Seeder
{
    public const PHONE = '+258840000001';

    public const PASSWORD = 'Obreiro123';

    public function run(UserService $userService): void
    {
        if (! app()->environment(['local', 'testing'])) {
            $this->command?->error('O TestWorkerSeeder só pode correr em ambiente local.');

            return;
        }

        $worker = User::firstOrNew(['phone' => self::PHONE]);

        $worker->fill([
            'role' => RoleEnum::Worker->value,
            'name' => 'Obreiro',
            'lastname' => 'Teste',
            'email' => 'obreiro.teste@controlemembros.test',
            'password' => self::PASSWORD,
            'is_active' => true,
        ]);

        if (! $worker->exists) {
            $worker->user_code = $userService->generateUserCode(RoleEnum::Worker);
        }

        $worker->save();

        $this->command?->info("Worker de teste pronto: {$worker->phone} ({$worker->user_code}). Password em TestWorkerSeeder::PASSWORD.");
    }
}
