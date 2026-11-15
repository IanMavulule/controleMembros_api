<?php

use App\Models\Group;
use App\Models\Program;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
});

test('guests cannot access groups', function () {
    $this->getJson(route('groups.index'))->assertUnauthorized();
});

test('workers cannot manage groups', function () {
    $worker = User::factory()->create();
    $group = Group::factory()->create();

    $this->actingAs($worker, 'sanctum')->getJson(route('groups.index'))->assertForbidden();
    $this->actingAs($worker, 'sanctum')->postJson(route('groups.store'), ['name' => 'Intercessão'])->assertForbidden();
    $this->actingAs($worker, 'sanctum')->deleteJson(route('groups.destroy', $group))->assertForbidden();
});

test('admin creates a group with generated code, users and programs', function () {
    $worker = User::factory()->create();
    $program = Program::factory()->create();

    $this->actingAs($this->admin, 'sanctum')
        ->postJson(route('groups.store'), [
            'name' => '  Grupo   de Intercessão ',
            'description' => 'Obreiros da intercessão',
            'user_ids' => [$worker->id],
            'program_ids' => [$program->id],
        ])
        ->assertCreated()
        ->assertJsonPath('data.name', 'Grupo de Intercessão')
        ->assertJsonPath('data.code', 'grupo-de-intercessao')
        ->assertJsonPath('data.users_count', 1)
        ->assertJsonPath('data.programs_count', 1)
        ->assertJsonPath('data.users.0.id', $worker->id)
        ->assertJsonPath('data.programs.0.id', $program->id);
});

test('group creation rejects names that only differ in spacing, case or accents', function (string $similarName) {
    Group::factory()->create(['name' => 'Grupo de Intercessão']);

    $this->actingAs($this->admin, 'sanctum')
        ->postJson(route('groups.store'), ['name' => $similarName])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['name' => 'Já existe um grupo com este nome: "Grupo de Intercessão".']);

    expect(Group::count())->toBe(1);
})->with([
    'extra inner spaces' => 'Grupo  de   Intercessão',
    'different case' => 'GRUPO DE INTERCESSÃO',
    'without accents' => 'Grupo de Intercessao',
]);

test('a group may share its name with a program', function () {
    Program::factory()->create(['name' => 'Louvor']);

    $this->actingAs($this->admin, 'sanctum')
        ->postJson(route('groups.store'), ['name' => 'Louvor'])
        ->assertCreated();
});

test('group creation validates input', function () {
    $this->actingAs($this->admin, 'sanctum')
        ->postJson(route('groups.store'), [
            'name' => '!!!',
            'user_ids' => [999],
            'program_ids' => 'nao-e-lista',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['name' => 'O nome do grupo deve conter letras ou números.'])
        ->assertJsonValidationErrors(['user_ids.0' => 'Utilizador não encontrado.'])
        ->assertJsonValidationErrors('program_ids');
});

test('admin lists groups with filters and counts', function () {
    $worker = User::factory()->create();
    $program = Program::factory()->create();
    $group = Group::factory()->create(['name' => 'Grupo de Intercessão']);
    $group->users()->attach($worker);
    $group->programs()->attach($program);
    Group::factory()->create(['name' => 'Recepção']);
    Group::factory()->inactive()->create(['name' => 'Intercessão Antiga']);

    $this->actingAs($this->admin, 'sanctum')
        ->getJson(route('groups.index', ['search' => 'intercessao', 'is_active' => 'true']))
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $group->id)
        ->assertJsonPath('data.0.users_count', 1)
        ->assertJsonPath('data.0.programs_count', 1);

    $this->actingAs($this->admin, 'sanctum')
        ->getJson(route('groups.index', ['user_id' => $worker->id]))
        ->assertOk()
        ->assertJsonCount(1, 'data');

    $this->actingAs($this->admin, 'sanctum')
        ->getJson(route('groups.index', ['program_id' => $program->id]))
        ->assertOk()
        ->assertJsonCount(1, 'data');
});

test('group listing includes each group programs and users', function () {
    $first = Group::factory()->create(['name' => 'Intercessão']);
    $first->programs()->attach([
        Program::factory()->create(['name' => 'Vigília'])->id,
        Program::factory()->create(['name' => 'Culto de Domingo'])->id,
    ]);
    $first->users()->attach([
        User::factory()->create(['name' => 'Zacarias'])->id,
        User::factory()->create(['name' => 'Ana'])->id,
    ]);
    Group::factory()->create(['name' => 'Recepção']);

    $this->actingAs($this->admin, 'sanctum')
        ->getJson(route('groups.index'))
        ->assertOk()
        ->assertJsonPath('data.0.name', 'Intercessão')
        ->assertJsonCount(2, 'data.0.programs')
        ->assertJsonPath('data.0.programs.0.name', 'Culto de Domingo')
        ->assertJsonPath('data.0.programs.1.name', 'Vigília')
        ->assertJsonCount(2, 'data.0.users')
        ->assertJsonPath('data.0.users.0.name', 'Ana')
        ->assertJsonPath('data.0.users.1.name', 'Zacarias')
        ->assertJsonPath('data.0.users_count', 2)
        ->assertJsonPath('data.1.name', 'Recepção')
        ->assertJsonCount(0, 'data.1.programs')
        ->assertJsonCount(0, 'data.1.users');
});

test('admin shows a group with its users and programs', function () {
    $group = Group::factory()->create();
    $group->users()->attach(User::factory()->count(2)->create());

    $this->actingAs($this->admin, 'sanctum')
        ->getJson(route('groups.show', $group))
        ->assertOk()
        ->assertJsonPath('data.id', $group->id)
        ->assertJsonCount(2, 'data.users')
        ->assertJsonCount(0, 'data.programs');
});

test('update only syncs the relation lists that are sent', function () {
    [$first, $second] = User::factory()->count(2)->create();
    $program = Program::factory()->create();
    $group = Group::factory()->create();
    $group->users()->attach($first);
    $group->programs()->attach($program);

    $this->actingAs($this->admin, 'sanctum')
        ->putJson(route('groups.update', $group), ['user_ids' => [$second->id]])
        ->assertOk()
        ->assertJsonPath('data.users.0.id', $second->id)
        ->assertJsonPath('data.programs_count', 1);

    $this->actingAs($this->admin, 'sanctum')
        ->putJson(route('groups.update', $group), ['program_ids' => []])
        ->assertOk()
        ->assertJsonPath('data.programs_count', 0)
        ->assertJsonPath('data.users_count', 1);
});

test('renaming a group regenerates its code and rejects equivalent names', function () {
    Group::factory()->create(['name' => 'Recepção']);
    $group = Group::factory()->create(['name' => 'Louvor']);

    $this->actingAs($this->admin, 'sanctum')
        ->putJson(route('groups.update', $group), ['name' => 'recepcao'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('name');

    $this->actingAs($this->admin, 'sanctum')
        ->putJson(route('groups.update', $group), ['name' => 'Louvor  e Adoração'])
        ->assertOk()
        ->assertJsonPath('data.code', 'louvor-e-adoracao');
});

test('deleting a group removes its associations but keeps users and programs', function () {
    $worker = User::factory()->create();
    $program = Program::factory()->create();
    $group = Group::factory()->create();
    $group->users()->attach($worker);
    $group->programs()->attach($program);

    $this->actingAs($this->admin, 'sanctum')
        ->deleteJson(route('groups.destroy', $group))
        ->assertOk();

    $this->assertModelMissing($group);
    $this->assertDatabaseMissing('group_user', ['group_id' => $group->id]);
    $this->assertDatabaseMissing('group_program', ['group_id' => $group->id]);
    $this->assertModelExists($worker);
    $this->assertModelExists($program);
});
