<?php

namespace Database\Seeders;

use App\Models\User;
use App\Services\UserService;
use App\Traits\NormalizesPhone;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class UserSeeder extends Seeder
{
    use NormalizesPhone;

    /**
     * Cria o primeiro administrador a partir das variáveis FIRST_ADMIN_* do .env.
     * O login é feito com FIRST_ADMIN_PHONE (+258XXXXXXXXX).
     * Se FIRST_ADMIN_PASSWORD estiver vazio, é gerada uma password e mostrada uma única vez.
     */
    public function run(UserService $userService): void
    {
        if (User::exists()) {
            $this->command?->warn('Já existem utilizadores. O primeiro administrador não foi criado.');

            return;
        }

        $config = config('auth.first_admin');
        $phone = self::normalizePhone($config['phone']);

        if (blank($config['email']) || blank($phone) || ! preg_match(self::PHONE_REGEX, $phone)) {
            $this->command?->error('Defina FIRST_ADMIN_EMAIL e FIRST_ADMIN_PHONE (+258XXXXXXXXX) no .env para criar o primeiro administrador.');

            return;
        }

        $password = $config['password'] ?: Str::password(12);

        $admin = $userService->createFirstAdmin([
            'name' => $config['name'],
            'lastname' => $config['lastname'],
            'phone' => $phone,
            'email' => $config['email'],
            'password' => $password,
        ]);

        $this->command?->info("Administrador criado: {$admin->phone} ({$admin->user_code})");

        if (blank($config['password'])) {
            $this->command?->warn("Password gerada (guarde-a e altere após o primeiro login): {$password}");
        }
    }
}
