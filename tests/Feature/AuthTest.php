<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

$adminPayload = fn (): array => [
    'name' => 'Ana',
    'lastname' => 'Silva',
    'phone' => '+258841234567',
    'email' => 'ana@example.com',
    'password' => 'Segura123',
    'password_confirmation' => 'Segura123',
];

test('setup admin creates the first administrator and returns a token', function () use ($adminPayload) {
    $response = $this->postJson(route('setup.admin'), $adminPayload());

    $response->assertCreated()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.user.role', 'admin')
        ->assertJsonStructure(['data' => ['user' => ['user_code'], 'token', 'expires_at']]);

    expect(User::first()->user_code)->toStartWith('ADM-');
});

test('setup admin is blocked once a user exists', function () use ($adminPayload) {
    User::factory()->admin()->create();

    $this->postJson(route('setup.admin'), $adminPayload())->assertForbidden();
    $this->postJson(route('setup.admin'))
        ->assertForbidden()
        ->assertJsonPath('message', 'Configuração inicial já concluída. Este endpoint não está mais disponível.');

    expect(User::count())->toBe(1);
});

test('setup admin rejects a phone outside the +258 format', function () use ($adminPayload) {
    $this->postJson(route('setup.admin'), [...$adminPayload(), 'phone' => '+351912345678'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['phone' => 'O número de celular deve estar no formato +258XXXXXXXXX.']);
});

test('login returns a token for a valid phone and password', function () {
    $user = User::factory()->create(['phone' => '+258843799864']);

    $this->postJson(route('auth.login'), ['phone' => '+258843799864', 'password' => 'password'])
        ->assertOk()
        ->assertJsonPath('data.user.id', $user->id)
        ->assertJsonStructure(['data' => ['token', 'expires_at']]);
});

test('login normalizes common phone variations', function (string $typedPhone) {
    User::factory()->create(['phone' => '+258843799864']);

    $this->postJson(route('auth.login'), ['phone' => $typedPhone, 'password' => 'password'])
        ->assertOk();
})->with([
    'without country code' => '843799864',
    'without plus sign' => '258843799864',
    'with 00 prefix' => '00258843799864',
    'with spaces' => '+258 84 379 9864',
]);

test('login rejects a phone in an invalid format', function () {
    $this->postJson(route('auth.login'), ['phone' => '12345', 'password' => 'password'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('phone');
});

test('login no longer accepts email', function () {
    $user = User::factory()->create();

    $this->postJson(route('auth.login'), ['email' => $user->email, 'password' => 'password'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('phone');
});

test('login rejects invalid credentials', function () {
    User::factory()->create(['phone' => '+258843799864']);

    $this->postJson(route('auth.login'), ['phone' => '+258843799864', 'password' => 'errada'])
        ->assertUnauthorized()
        ->assertJsonPath('success', false);
});

test('login rejects inactive users', function () {
    User::factory()->inactive()->create(['phone' => '+258843799864']);

    $this->postJson(route('auth.login'), ['phone' => '+258843799864', 'password' => 'password'])
        ->assertForbidden();
});

test('me requires authentication', function () {
    $this->getJson(route('auth.me'))
        ->assertUnauthorized()
        ->assertJsonPath('message', 'Sessão expirada. Faça login novamente.');
});

test('me returns the authenticated user and logout revokes the token', function () {
    $user = User::factory()->create();
    $token = $user->createToken('api-token')->plainTextToken;

    $this->withToken($token)->getJson(route('auth.me'))
        ->assertOk()
        ->assertJsonPath('data.email', $user->email);

    $this->withToken($token)->postJson(route('auth.logout'))->assertOk();

    expect($user->tokens()->count())->toBe(0);
});

test('inactive user with an existing token is blocked', function () {
    $user = User::factory()->inactive()->create();
    $token = $user->createToken('api-token')->plainTextToken;

    $this->withToken($token)->getJson(route('auth.me'))->assertForbidden();
});

test('change password requires the current password', function () {
    $user = User::factory()->create();
    $token = $user->createToken('api-token')->plainTextToken;

    $this->withToken($token)->patchJson(route('auth.change-password'), [
        'current_password' => 'errada',
        'password' => 'NovaSenha123',
        'password_confirmation' => 'NovaSenha123',
    ])->assertUnprocessable()->assertJsonValidationErrors('current_password');

    $this->withToken($token)->patchJson(route('auth.change-password'), [
        'current_password' => 'password',
        'password' => 'NovaSenha123',
        'password_confirmation' => 'NovaSenha123',
    ])->assertOk();

    expect(Hash::check('NovaSenha123', $user->fresh()->password))->toBeTrue();
});

test('non admin cannot change own name through update me', function () {
    $user = User::factory()->create(['name' => 'Original']);

    $this->actingAs($user, 'sanctum')
        ->putJson(route('auth.update-me'), ['name' => 'Outro', 'phone' => '829999999'])
        ->assertOk()
        ->assertJsonPath('data.name', 'Original')
        ->assertJsonPath('data.phone', '+258829999999');
});

test('update me rejects a phone already used by another user', function () {
    User::factory()->create(['phone' => '+258821111111']);
    $user = User::factory()->create();

    $this->actingAs($user, 'sanctum')
        ->putJson(route('auth.update-me'), ['phone' => '+258821111111'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['phone' => 'Este número de celular já está registado.']);
});
