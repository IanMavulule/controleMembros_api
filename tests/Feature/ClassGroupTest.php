<?php

use App\Models\ClassGroup;
use App\Models\Person;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
    $this->worker = User::factory()->create();
});

test('guests cannot list classes', function () {
    $this->getJson(route('class-groups.index'))->assertUnauthorized();
});

test('admin creates a class with generated code', function () {
    $this->actingAs($this->admin, 'sanctum')
        ->postJson(route('class-groups.store'), ['name' => '  Turma   dos Jovens '])
        ->assertCreated()
        ->assertJsonPath('message', 'Turma criada com sucesso.')
        ->assertJsonPath('data.name', 'Turma dos Jovens')
        ->assertJsonPath('data.code', 'turma-dos-jovens')
        ->assertJsonPath('data.people_count', 0);
});

test('class names are unique ignoring spacing, case and accents', function (string $similarName) {
    ClassGroup::factory()->create(['name' => 'Turma de Adoração']);

    $this->actingAs($this->admin, 'sanctum')
        ->postJson(route('class-groups.store'), ['name' => $similarName])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['name' => 'Já existe uma turma com este nome: "Turma de Adoração".']);

    expect(ClassGroup::count())->toBe(1);
})->with([
    'extra spaces' => 'Turma  de   Adoração',
    'different case' => 'TURMA DE ADORAÇÃO',
    'without accents' => 'Turma de Adoracao',
]);

test('class name must contain letters or numbers', function () {
    $this->actingAs($this->admin, 'sanctum')
        ->postJson(route('class-groups.store'), ['name' => '---'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['name' => 'O nome da turma deve conter letras ou números.']);
});

test('any authenticated user lists classes with people count', function () {
    $youth = ClassGroup::factory()->create(['name' => 'Jovens']);
    ClassGroup::factory()->create(['name' => 'Adultos']);
    Person::factory()->count(2)->for($youth)->create();

    $this->actingAs($this->worker, 'sanctum')
        ->getJson(route('class-groups.index'))
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.name', 'Adultos')
        ->assertJsonPath('data.1.people_count', 2);

    $this->actingAs($this->worker, 'sanctum')
        ->getJson(route('class-groups.index', ['search' => 'jovens']))
        ->assertJsonCount(1, 'data');

    $this->actingAs($this->worker, 'sanctum')
        ->getJson(route('class-groups.show', $youth))
        ->assertOk()
        ->assertJsonPath('data.people_count', 2);
});

test('workers cannot manage classes', function () {
    $classGroup = ClassGroup::factory()->create();

    $this->actingAs($this->worker, 'sanctum')->postJson(route('class-groups.store'), ['name' => 'Nova'])->assertForbidden();
    $this->actingAs($this->worker, 'sanctum')->putJson(route('class-groups.update', $classGroup), ['name' => 'Outra'])->assertForbidden();
    $this->actingAs($this->worker, 'sanctum')->deleteJson(route('class-groups.destroy', $classGroup))->assertForbidden();
});

test('admin renames a class and rejects equivalent names', function () {
    ClassGroup::factory()->create(['name' => 'Adultos']);
    $classGroup = ClassGroup::factory()->create(['name' => 'Jovens']);

    $this->actingAs($this->admin, 'sanctum')
        ->putJson(route('class-groups.update', $classGroup), ['name' => 'ADULTOS'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('name');

    $this->actingAs($this->admin, 'sanctum')
        ->putJson(route('class-groups.update', $classGroup), ['name' => 'Jovens Adolescentes'])
        ->assertOk()
        ->assertJsonPath('data.code', 'jovens-adolescentes');
});

test('a class with people cannot be deleted, an empty one can', function () {
    $withPeople = ClassGroup::factory()->create();
    Person::factory()->for($withPeople)->create();
    $empty = ClassGroup::factory()->create();

    $this->actingAs($this->admin, 'sanctum')
        ->deleteJson(route('class-groups.destroy', $withPeople))
        ->assertUnprocessable()
        ->assertJsonPath('message', 'Não é possível eliminar uma turma com pessoas associadas.');

    $this->actingAs($this->admin, 'sanctum')
        ->deleteJson(route('class-groups.destroy', $empty))
        ->assertOk();

    $this->assertModelMissing($empty);
});
