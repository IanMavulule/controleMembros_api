<?php

use App\Models\Location;
use App\Models\Person;
use App\Models\Province;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
    $this->worker = User::factory()->create();
    $this->maputo = Province::factory()->create(['name' => 'Maputo Província']);
    $this->gaza = Province::factory()->create(['name' => 'Gaza']);
});

test('admin creates a location with generated code', function () {
    $this->actingAs($this->admin, 'sanctum')
        ->postJson(route('locations.store'), ['province_id' => $this->maputo->id, 'name' => ' Matola  Rio '])
        ->assertCreated()
        ->assertJsonPath('data.name', 'Matola Rio')
        ->assertJsonPath('data.code', 'matola-rio')
        ->assertJsonPath('data.province.id', $this->maputo->id);
});

test('location names are unique only inside the same province', function () {
    Location::factory()->for($this->maputo)->create(['name' => 'Centro']);

    $this->actingAs($this->admin, 'sanctum')
        ->postJson(route('locations.store'), ['province_id' => $this->maputo->id, 'name' => 'CENTRO'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['name' => 'Já existe um local nesta província com este nome: "Centro".']);

    $this->actingAs($this->admin, 'sanctum')
        ->postJson(route('locations.store'), ['province_id' => $this->gaza->id, 'name' => 'Centro'])
        ->assertCreated();
});

test('workers list, view and create locations but cannot edit or delete them', function () {
    $location = Location::factory()->for($this->maputo)->create();
    Location::factory()->for($this->gaza)->create();

    $this->actingAs($this->worker, 'sanctum')
        ->getJson(route('locations.index', ['province_id' => $this->maputo->id]))
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $location->id);

    $this->actingAs($this->worker, 'sanctum')->getJson(route('locations.show', $location))->assertOk();

    $this->actingAs($this->worker, 'sanctum')
        ->postJson(route('locations.store'), ['province_id' => $this->maputo->id, 'name' => 'Bairro Novo'])
        ->assertCreated()
        ->assertJsonPath('data.code', 'bairro-novo')
        ->assertJsonPath('data.province.id', $this->maputo->id);

    $this->actingAs($this->worker, 'sanctum')
        ->putJson(route('locations.update', $location), ['name' => 'Outro'])
        ->assertForbidden();
    $this->actingAs($this->worker, 'sanctum')
        ->deleteJson(route('locations.destroy', $location))
        ->assertForbidden();
});

test('workers cannot duplicate a location of the same province', function () {
    Location::factory()->for($this->maputo)->create(['name' => 'Matola Rio']);

    $this->actingAs($this->worker, 'sanctum')
        ->postJson(route('locations.store'), ['province_id' => $this->maputo->id, 'name' => 'matola  rio'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['name' => 'Já existe um local nesta província com este nome: "Matola Rio".']);
});

test('a location with people cannot be deleted', function () {
    $location = Location::factory()->for($this->maputo)->create();
    Person::factory()->create(['location_id' => $location->id]);

    $this->actingAs($this->admin, 'sanctum')
        ->deleteJson(route('locations.destroy', $location))
        ->assertUnprocessable()
        ->assertJsonPath('message', 'Não é possível eliminar um local com pessoas registadas.');
});

test('a province with locations cannot be deleted', function () {
    Location::factory()->for($this->maputo)->create();

    $this->actingAs($this->admin, 'sanctum')
        ->deleteJson(route('provinces.destroy', $this->maputo))
        ->assertUnprocessable()
        ->assertJsonPath('message', 'Não é possível eliminar uma província com locais registados.');
});
