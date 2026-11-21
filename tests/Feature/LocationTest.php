<?php

use App\Models\Location;
use App\Models\Person;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
    $this->worker = User::factory()->create();
});

test('admin creates a location with generated code', function () {
    $this->actingAs($this->admin, 'sanctum')
        ->postJson(route('locations.store'), ['name' => ' Matola  Rio '])
        ->assertCreated()
        ->assertJsonPath('data.name', 'Matola Rio')
        ->assertJsonPath('data.code', 'matola-rio')
        ->assertJsonMissingPath('data.province');
});

test('location names are unique across the whole table', function () {
    Location::factory()->create(['name' => 'Centro']);

    $this->actingAs($this->admin, 'sanctum')
        ->postJson(route('locations.store'), ['name' => 'CENTRO'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['name' => 'Já existe uma zona/bairro com este nome: "Centro".']);
});

test('workers list, view and create locations but cannot edit or delete them', function () {
    $location = Location::factory()->create(['name' => 'Polana Caniço']);
    Location::factory()->create(['name' => 'Malhangalene']);

    $this->actingAs($this->worker, 'sanctum')
        ->getJson(route('locations.index', ['search' => 'polana']))
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $location->id);

    $this->actingAs($this->worker, 'sanctum')->getJson(route('locations.show', $location))->assertOk();

    $this->actingAs($this->worker, 'sanctum')
        ->postJson(route('locations.store'), ['name' => 'Bairro Novo'])
        ->assertCreated()
        ->assertJsonPath('data.code', 'bairro-novo');

    $this->actingAs($this->worker, 'sanctum')
        ->putJson(route('locations.update', $location), ['name' => 'Outro'])
        ->assertForbidden();
    $this->actingAs($this->worker, 'sanctum')
        ->deleteJson(route('locations.destroy', $location))
        ->assertForbidden();
});

test('admin renames a location keeping the name unique', function () {
    $location = Location::factory()->create(['name' => 'Matola Rio']);
    Location::factory()->create(['name' => 'Machava']);

    $this->actingAs($this->admin, 'sanctum')
        ->putJson(route('locations.update', $location), ['name' => 'machava'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['name' => 'Já existe uma zona/bairro com este nome: "Machava".']);

    $this->actingAs($this->admin, 'sanctum')
        ->putJson(route('locations.update', $location), ['name' => 'Matola  Rio'])
        ->assertOk()
        ->assertJsonPath('data.name', 'Matola Rio');
});

test('workers cannot duplicate an existing location', function () {
    Location::factory()->create(['name' => 'Matola Rio']);

    $this->actingAs($this->worker, 'sanctum')
        ->postJson(route('locations.store'), ['name' => 'matola  rio'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['name' => 'Já existe uma zona/bairro com este nome: "Matola Rio".']);
});

test('a location with people cannot be deleted', function () {
    $location = Location::factory()->create();
    Person::factory()->create(['location_id' => $location->id]);

    $this->actingAs($this->admin, 'sanctum')
        ->deleteJson(route('locations.destroy', $location))
        ->assertUnprocessable()
        ->assertJsonPath('message', 'Não é possível eliminar uma zona/bairro com pessoas registadas.');
});

test('the provinces endpoints no longer exist', function () {
    $this->actingAs($this->admin, 'sanctum')->getJson('/api/v1/provinces')->assertNotFound();
});
