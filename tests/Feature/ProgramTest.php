<?php

use App\Models\Group;
use App\Models\Program;
use App\Models\User;
use App\Models\Worship;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
});

test('guests cannot access programs', function () {
    $this->getJson(route('programs.index'))->assertUnauthorized();
});

test('workers cannot create, update or delete programs', function () {
    $worker = User::factory()->create();
    $program = Program::factory()->create();

    $this->actingAs($worker, 'sanctum')->postJson(route('programs.store'), ['name' => 'Culto de Jovens'])->assertForbidden();
    $this->actingAs($worker, 'sanctum')->putJson(route('programs.update', $program), ['name' => 'Outro'])->assertForbidden();
    $this->actingAs($worker, 'sanctum')->deleteJson(route('programs.destroy', $program))->assertForbidden();
});

test('workers only see the programs of their active groups', function () {
    $worker = User::factory()->create();
    $mine = Program::factory()->create(['name' => 'Culto de Jovens']);
    $viaInactiveGroup = Program::factory()->create(['name' => 'Vigília']);
    $other = Program::factory()->create(['name' => 'Culto de Senhoras']);

    $activeGroup = Group::factory()->create();
    $activeGroup->users()->attach($worker);
    $activeGroup->programs()->attach($mine);

    $inactiveGroup = Group::factory()->inactive()->create();
    $inactiveGroup->users()->attach($worker);
    $inactiveGroup->programs()->attach($viaInactiveGroup);

    $this->actingAs($worker, 'sanctum')
        ->getJson(route('programs.index'))
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $mine->id);

    $this->actingAs($worker, 'sanctum')->getJson(route('programs.show', $mine))->assertOk();
    $this->actingAs($worker, 'sanctum')
        ->getJson(route('programs.show', $other))
        ->assertForbidden()
        ->assertJsonPath('message', 'Não tem acesso a este programa.');
});

test('a program with worships cannot be deleted', function () {
    $program = Program::factory()->create();
    Worship::factory()->for($program)->create();

    $this->actingAs($this->admin, 'sanctum')
        ->deleteJson(route('programs.destroy', $program))
        ->assertUnprocessable()
        ->assertJsonPath('message', 'Não é possível eliminar um programa com cultos registados. Desactive-o em vez disso.');

    $this->assertModelExists($program);
});

test('admin lists programs with search and status filters', function () {
    Program::factory()->create(['name' => 'Culto de Jovens']);
    Program::factory()->create(['name' => 'Culto de Senhoras']);
    Program::factory()->inactive()->create(['name' => 'Jovens Antigo']);

    $this->actingAs($this->admin, 'sanctum')
        ->getJson(route('programs.index', ['search' => 'Jovens', 'is_active' => 'true']))
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.name', 'Culto de Jovens');
});

test('admin creates a program', function () {
    $this->actingAs($this->admin, 'sanctum')
        ->postJson(route('programs.store'), [
            'name' => 'Culto de Jovens',
            'description' => 'Programa semanal dos jovens',
        ])
        ->assertCreated()
        ->assertJsonPath('data.name', 'Culto de Jovens')
        ->assertJsonPath('data.is_active', true);

    $this->assertDatabaseHas('programs', ['name' => 'Culto de Jovens', 'is_active' => true]);
});

test('program creation generates the code and squishes the display name', function () {
    $this->actingAs($this->admin, 'sanctum')
        ->postJson(route('programs.store'), ['name' => '  Mesa   Profética  '])
        ->assertCreated()
        ->assertJsonPath('data.name', 'Mesa Profética')
        ->assertJsonPath('data.code', 'mesa-profetica');
});

test('program creation rejects names that only differ in spacing, case or accents', function (string $similarName) {
    Program::factory()->create(['name' => 'Mesa Profética']);

    $this->actingAs($this->admin, 'sanctum')
        ->postJson(route('programs.store'), ['name' => $similarName])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['name' => 'Já existe um programa com este nome: "Mesa Profética".']);

    expect(Program::count())->toBe(1);
})->with([
    'extra inner spaces' => 'Mesa   Profética',
    'surrounding spaces' => ' Mesa Profética ',
    'different case' => 'MESA PROFÉTICA',
    'without accents' => 'Mesa Profetica',
]);

test('program creation validates input', function () {
    $this->actingAs($this->admin, 'sanctum')
        ->postJson(route('programs.store'), ['name' => '!!! ???', 'is_active' => 'talvez'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['name' => 'O nome do programa deve conter letras ou números.'])
        ->assertJsonValidationErrors('is_active');

    $this->actingAs($this->admin, 'sanctum')
        ->postJson(route('programs.store'), [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('name');
});

test('search ignores accents and extra spaces', function () {
    Program::factory()->create(['name' => 'Mesa Profética']);
    Program::factory()->create(['name' => 'Culto de Jovens']);

    $this->actingAs($this->admin, 'sanctum')
        ->getJson(route('programs.index', ['search' => 'mesa   profetica']))
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.name', 'Mesa Profética');
});

test('admin shows a program', function () {
    $program = Program::factory()->create();

    $this->actingAs($this->admin, 'sanctum')
        ->getJson(route('programs.show', $program))
        ->assertOk()
        ->assertJsonPath('data.id', $program->id);
});

test('admin updates a program keeping its own name valid', function () {
    $program = Program::factory()->create(['name' => 'Culto de Jovens']);

    $this->actingAs($this->admin, 'sanctum')
        ->putJson(route('programs.update', $program), ['name' => 'Culto de Jovens', 'is_active' => false])
        ->assertOk()
        ->assertJsonPath('data.is_active', false);
});

test('renaming a program regenerates its code', function () {
    $program = Program::factory()->create(['name' => 'Culto de Jovens']);

    $this->actingAs($this->admin, 'sanctum')
        ->putJson(route('programs.update', $program), ['name' => 'Culto  da Juventude'])
        ->assertOk()
        ->assertJsonPath('data.name', 'Culto da Juventude')
        ->assertJsonPath('data.code', 'culto-da-juventude');
});

test('admin cannot rename a program to an equivalent existing name', function () {
    Program::factory()->create(['name' => 'Culto de Jovens']);
    $program = Program::factory()->create(['name' => 'Culto de Senhoras']);

    $this->actingAs($this->admin, 'sanctum')
        ->putJson(route('programs.update', $program), ['name' => 'culto  de jovens'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('name');
});

test('admin deletes a program', function () {
    $program = Program::factory()->create();

    $this->actingAs($this->admin, 'sanctum')
        ->deleteJson(route('programs.destroy', $program))
        ->assertOk();

    $this->assertModelMissing($program);
});
