<?php

use App\Models\Group;
use App\Models\Program;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
    $this->group = Group::factory()->create();
});

test('workers cannot manage group associations', function () {
    $worker = User::factory()->create();

    $this->actingAs($worker, 'sanctum')
        ->postJson(route('groups.users.attach', $this->group), ['user_ids' => [$worker->id]])
        ->assertForbidden();

    $this->actingAs($worker, 'sanctum')
        ->putJson(route('users.groups.sync', $worker), ['group_ids' => [$this->group->id]])
        ->assertForbidden();
});

test('attaching users keeps the existing members', function () {
    [$first, $second] = User::factory()->count(2)->create();
    $this->group->users()->attach($first);

    $this->actingAs($this->admin, 'sanctum')
        ->postJson(route('groups.users.attach', $this->group), ['user_ids' => [$first->id, $second->id]])
        ->assertOk()
        ->assertJsonPath('data.users_count', 2);
});

test('attaching requires at least one user', function () {
    $this->actingAs($this->admin, 'sanctum')
        ->postJson(route('groups.users.attach', $this->group), ['user_ids' => []])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('user_ids');
});

test('syncing users replaces the members and accepts an empty list', function () {
    [$first, $second] = User::factory()->count(2)->create();
    $this->group->users()->attach($first);

    $this->actingAs($this->admin, 'sanctum')
        ->putJson(route('groups.users.sync', $this->group), ['user_ids' => [$second->id]])
        ->assertOk()
        ->assertJsonPath('data.users_count', 1)
        ->assertJsonPath('data.users.0.id', $second->id);

    $this->actingAs($this->admin, 'sanctum')
        ->putJson(route('groups.users.sync', $this->group), ['user_ids' => []])
        ->assertOk()
        ->assertJsonPath('data.users_count', 0);
});

test('detaching a user removes only that member', function () {
    [$first, $second] = User::factory()->count(2)->create();
    $this->group->users()->attach([$first->id, $second->id]);

    $this->actingAs($this->admin, 'sanctum')
        ->deleteJson(route('groups.users.detach', [$this->group, $first]))
        ->assertOk()
        ->assertJsonPath('data.users_count', 1)
        ->assertJsonPath('data.users.0.id', $second->id);
});

test('detaching a user that is not a member returns not found', function () {
    $outsider = User::factory()->create();

    $this->actingAs($this->admin, 'sanctum')
        ->deleteJson(route('groups.users.detach', [$this->group, $outsider]))
        ->assertNotFound()
        ->assertJsonPath('message', 'O utilizador não pertence a este grupo.');
});

test('adding programs keeps the existing ones and validates ids', function () {
    [$first, $second] = Program::factory()->count(2)->create();
    $this->group->programs()->attach($first);

    $this->actingAs($this->admin, 'sanctum')
        ->postJson(route('groups.programs.attach', $this->group), ['program_ids' => [$second->id]])
        ->assertOk()
        ->assertJsonPath('data.programs_count', 2);

    $this->actingAs($this->admin, 'sanctum')
        ->postJson(route('groups.programs.attach', $this->group), ['program_ids' => [999]])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['program_ids.0' => 'Programa não encontrado.']);
});

test('syncing programs replaces them', function () {
    [$first, $second] = Program::factory()->count(2)->create();
    $this->group->programs()->attach($first);

    $this->actingAs($this->admin, 'sanctum')
        ->putJson(route('groups.programs.sync', $this->group), ['program_ids' => [$second->id]])
        ->assertOk()
        ->assertJsonPath('data.programs_count', 1)
        ->assertJsonPath('data.programs.0.id', $second->id);
});

test('detaching a program removes it from the group', function () {
    $program = Program::factory()->create();
    $this->group->programs()->attach($program);

    $this->actingAs($this->admin, 'sanctum')
        ->deleteJson(route('groups.programs.detach', [$this->group, $program]))
        ->assertOk()
        ->assertJsonPath('data.programs_count', 0);

    $this->actingAs($this->admin, 'sanctum')
        ->deleteJson(route('groups.programs.detach', [$this->group, $program]))
        ->assertNotFound();
});

test('syncing the groups of a user replaces them', function () {
    $worker = User::factory()->create();
    $other = Group::factory()->create();
    $this->group->users()->attach($worker);

    $this->actingAs($this->admin, 'sanctum')
        ->putJson(route('users.groups.sync', $worker), ['group_ids' => [$other->id]])
        ->assertOk()
        ->assertJsonCount(1, 'data.groups')
        ->assertJsonPath('data.groups.0.id', $other->id);

    expect($worker->groups()->pluck('groups.id')->all())->toBe([$other->id]);

    $this->actingAs($this->admin, 'sanctum')
        ->putJson(route('users.groups.sync', $worker), ['group_ids' => []])
        ->assertOk()
        ->assertJsonCount(0, 'data.groups');
});

test('syncing the groups of a user requires the list', function () {
    $worker = User::factory()->create();

    $this->actingAs($this->admin, 'sanctum')
        ->putJson(route('users.groups.sync', $worker), [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('group_ids');
});
