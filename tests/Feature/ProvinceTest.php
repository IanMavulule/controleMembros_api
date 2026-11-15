<?php

use App\Models\Province;
use App\Models\User;
use Database\Seeders\ProvinceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
    $this->worker = User::factory()->create();
});

test('the seeder creates the 11 provinces of Mozambique and can run twice', function () {
    $this->seed(ProvinceSeeder::class);
    $this->seed(ProvinceSeeder::class);

    expect(Province::count())->toBe(11)
        ->and(Province::where('code', 'zambezia')->value('name'))->toBe('Zambézia')
        ->and(Province::where('code', 'maputo-provincia')->exists())->toBeTrue();
});

test('guests cannot list provinces', function () {
    $this->getJson(route('provinces.index'))->assertUnauthorized();
});

test('any authenticated user lists provinces ordered by name without pagination', function () {
    $this->seed(ProvinceSeeder::class);

    $this->actingAs($this->worker, 'sanctum')
        ->getJson(route('provinces.index'))
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonCount(11, 'data')
        ->assertJsonPath('data.0.name', 'Cabo Delgado')
        ->assertJsonMissingPath('meta');
});

test('province search ignores accents', function () {
    $this->seed(ProvinceSeeder::class);

    $this->actingAs($this->worker, 'sanctum')
        ->getJson(route('provinces.index', ['search' => 'zambezia']))
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.name', 'Zambézia');
});

test('workers can view but not manage provinces', function () {
    $province = Province::factory()->create();

    $this->actingAs($this->worker, 'sanctum')->getJson(route('provinces.show', $province))->assertOk();
    $this->actingAs($this->worker, 'sanctum')->postJson(route('provinces.store'), ['name' => 'Nova'])->assertForbidden();
    $this->actingAs($this->worker, 'sanctum')->putJson(route('provinces.update', $province), ['name' => 'Outra'])->assertForbidden();
    $this->actingAs($this->worker, 'sanctum')->deleteJson(route('provinces.destroy', $province))->assertForbidden();
});

test('admin creates a province with generated code', function () {
    $this->actingAs($this->admin, 'sanctum')
        ->postJson(route('provinces.store'), ['name' => '  Maputo   Província '])
        ->assertCreated()
        ->assertJsonPath('data.name', 'Maputo Província')
        ->assertJsonPath('data.code', 'maputo-provincia');
});

test('province names are unique ignoring spacing, case and accents', function () {
    Province::factory()->create(['name' => 'Zambézia']);

    $this->actingAs($this->admin, 'sanctum')
        ->postJson(route('provinces.store'), ['name' => 'ZAMBEZIA'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['name' => 'Já existe uma província com este nome: "Zambézia".']);

    $this->actingAs($this->admin, 'sanctum')
        ->postJson(route('provinces.store'), ['name' => '???'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['name' => 'O nome da província deve conter letras ou números.']);
});

test('admin renames and deletes a province', function () {
    $province = Province::factory()->create(['name' => 'Maputo']);

    $this->actingAs($this->admin, 'sanctum')
        ->putJson(route('provinces.update', $province), ['name' => 'Maputo Cidade'])
        ->assertOk()
        ->assertJsonPath('data.code', 'maputo-cidade');

    $this->actingAs($this->admin, 'sanctum')
        ->deleteJson(route('provinces.destroy', $province))
        ->assertOk();

    $this->assertModelMissing($province);
});
