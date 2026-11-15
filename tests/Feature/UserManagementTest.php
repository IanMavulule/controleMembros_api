<?php

use App\Models\User;
use App\Services\EmailService;
use App\Services\UserService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
});

test('non admin users cannot manage users', function () {
    $worker = User::factory()->create();

    $this->actingAs($worker, 'sanctum')
        ->getJson(route('users.index'))
        ->assertForbidden();
});

test('admin lists users with filters', function () {
    User::factory()->create(['name' => 'Carlos', 'lastname' => 'Mondlane']);
    User::factory()->create(['name' => 'Beatriz']);

    $this->actingAs($this->admin, 'sanctum')
        ->getJson(route('users.index', ['search' => 'Carlos Mond']))
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.name', 'Carlos');
});

test('admin creates a user and receives test credentials when enabled', function () {
    config(['auth.show_test_credentials' => true]);

    $response = $this->actingAs($this->admin, 'sanctum')->postJson(route('users.store'), [
        'name' => 'Paulo',
        'lastname' => 'Machava',
        'phone' => '845555555',
        'email' => 'paulo@example.com',
        'role' => 'worker',
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.role', 'worker')
        ->assertJsonPath('email_delivery.status', 'skipped')
        ->assertJsonPath('data.phone', '+258845555555')
        ->assertJsonPath('credentials.phone', '+258845555555');

    $user = User::where('phone', '+258845555555')->first();
    expect($user->user_code)->toStartWith('WRK-')
        ->and($response->json('credentials.password'))->toMatch('/^Paulo\d{4}$/')
        ->and(Hash::check($response->json('credentials.password'), $user->password))->toBeTrue();
});

test('generated passwords are the first name without accents plus four digits', function (string $name, string $pattern) {
    $password = app(UserService::class)->generatePassword($name);

    expect($password)->toMatch($pattern);
})->with([
    'simple' => ['Paulo', '/^Paulo\d{4}$/'],
    'accents and lowercase' => ['joão', '/^Joao\d{4}$/'],
    'compound name uses the first' => ['  Maria   José ', '/^Maria\d{4}$/'],
    'uppercase' => ['ANA', '/^Ana\d{4}$/'],
    'no letters' => ['123', '/^Membro\d{4}$/'],
]);

test('the welcome email carries the generated password', function () {
    $this->mock(EmailService::class)
        ->shouldReceive('sendWelcomeEmail')
        ->once()
        ->withArgs(fn ($email, $name, $phone, $password) => $email === 'paulo@example.com'
            && $phone === '+258845555555'
            && preg_match('/^Paulo\d{4}$/', $password) === 1);

    $this->actingAs($this->admin, 'sanctum')->postJson(route('users.store'), [
        'name' => 'Paulo',
        'lastname' => 'Machava',
        'phone' => '845555555',
        'email' => 'paulo@example.com',
        'role' => 'worker',
    ])->assertCreated()->assertJsonPath('email_delivery.status', 'sent');
});

test('user creation reports failed email delivery when mail is not configured', function () {
    $this->actingAs($this->admin, 'sanctum')->postJson(route('users.store'), [
        'name' => 'Paulo',
        'lastname' => 'Machava',
        'phone' => '845555555',
        'email' => 'paulo@example.com',
        'role' => 'worker',
    ])
        ->assertCreated()
        ->assertJsonPath('email_delivery.status', 'failed')
        ->assertJsonMissingPath('credentials');
});

test('user creation validates input', function () {
    $this->actingAs($this->admin, 'sanctum')
        ->postJson(route('users.store'), ['email' => $this->admin->email, 'role' => 'invalido'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['name', 'lastname', 'phone', 'email', 'role']);
});

test('user creation rejects a duplicated phone', function () {
    $this->actingAs($this->admin, 'sanctum')
        ->postJson(route('users.store'), [
            'name' => 'Paulo',
            'lastname' => 'Machava',
            'phone' => $this->admin->phone,
            'email' => 'paulo@example.com',
            'role' => 'worker',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('phone');
});

test('changing the role regenerates the user code', function () {
    $user = User::factory()->create();

    $this->actingAs($this->admin, 'sanctum')
        ->putJson(route('users.update', $user), ['role' => 'admin'])
        ->assertOk()
        ->assertJsonPath('data.role', 'admin');

    expect($user->fresh()->user_code)->toStartWith('ADM-');
});

test('deactivating a user revokes their tokens', function () {
    $user = User::factory()->create();
    $user->createToken('api-token');

    $this->actingAs($this->admin, 'sanctum')
        ->putJson(route('users.update', $user), ['is_active' => false])
        ->assertOk()
        ->assertJsonPath('data.is_active', false);

    expect($user->tokens()->count())->toBe(0);
});

test('admin cannot deactivate or delete own account', function () {
    $this->actingAs($this->admin, 'sanctum')
        ->putJson(route('users.update', $this->admin), ['is_active' => false])
        ->assertUnprocessable();

    $this->actingAs($this->admin, 'sanctum')
        ->deleteJson(route('users.destroy', $this->admin))
        ->assertUnprocessable();
});

test('admin deletes another user', function () {
    $user = User::factory()->create();

    $this->actingAs($this->admin, 'sanctum')
        ->deleteJson(route('users.destroy', $user))
        ->assertOk();

    $this->assertModelMissing($user);
});

test('admin resets a user password and revokes their tokens', function () {
    $user = User::factory()->create();
    $user->createToken('api-token');

    $this->actingAs($this->admin, 'sanctum')
        ->patchJson(route('users.reset-password', $user), [
            'password' => 'Redefinida123',
            'password_confirmation' => 'Redefinida123',
        ])
        ->assertOk();

    expect(Hash::check('Redefinida123', $user->fresh()->password))->toBeTrue()
        ->and($user->tokens()->count())->toBe(0);
});

test('test password generation is forbidden when test credentials are disabled', function () {
    $user = User::factory()->create();

    $this->actingAs($this->admin, 'sanctum')
        ->patchJson(route('users.test-password', $user))
        ->assertForbidden();
});
