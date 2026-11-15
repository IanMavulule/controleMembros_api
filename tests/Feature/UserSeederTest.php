<?php

use App\Models\User;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('seeder creates the first administrator from configuration', function () {
    config([
        'auth.first_admin.email' => 'root@example.com',
        'auth.first_admin.phone' => '843799864',
        'auth.first_admin.password' => 'Inicial123',
    ]);

    $this->seed(UserSeeder::class);

    $admin = User::sole();
    expect($admin->isAdmin())->toBeTrue()
        ->and($admin->phone)->toBe('+258843799864')
        ->and($admin->user_code)->toStartWith('ADM-')
        ->and(Hash::check('Inicial123', $admin->password))->toBeTrue();
});

test('seeder does nothing when users already exist', function () {
    User::factory()->create();
    config([
        'auth.first_admin.email' => 'root@example.com',
        'auth.first_admin.phone' => '+258843799864',
    ]);

    $this->seed(UserSeeder::class);

    expect(User::count())->toBe(1);
});

test('seeder does nothing without a valid admin phone', function (?string $phone) {
    config([
        'auth.first_admin.email' => 'root@example.com',
        'auth.first_admin.phone' => $phone,
    ]);

    $this->seed(UserSeeder::class);

    expect(User::count())->toBe(0);
})->with([
    'missing' => null,
    'invalid' => '12345',
]);

test('seeder does nothing without an admin email', function () {
    config([
        'auth.first_admin.email' => null,
        'auth.first_admin.phone' => '+258843799864',
    ]);

    $this->seed(UserSeeder::class);

    expect(User::count())->toBe(0);
});
