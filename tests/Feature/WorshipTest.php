<?php

use App\Models\Group;
use App\Models\Program;
use App\Models\User;
use App\Models\Worship;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
    $this->worker = User::factory()->create();

    $this->myProgram = Program::factory()->create(['name' => 'Culto de Jovens']);
    $this->otherProgram = Program::factory()->create(['name' => 'Culto de Senhoras']);

    $group = Group::factory()->create();
    $group->users()->attach($this->worker);
    $group->programs()->attach($this->myProgram);
});

$payload = fn (int $programId, array $overrides = []): array => [
    'program_id' => $programId,
    'name' => 'Culto de Domingo',
    'title' => 'A fé que move montanhas',
    'date' => '2026-11-22',
    'start_time' => '09:00',
    ...$overrides,
];

test('guests cannot access worships', function () {
    $this->getJson(route('worships.index'))->assertUnauthorized();
});

test('admin creates a worship in any program', function () use ($payload) {
    $this->actingAs($this->admin, 'sanctum')
        ->postJson(route('worships.store'), $payload($this->otherProgram->id, ['name' => '  Culto   de Domingo ']))
        ->assertCreated()
        ->assertJsonPath('data.name', 'Culto de Domingo')
        ->assertJsonPath('data.program.id', $this->otherProgram->id)
        ->assertJsonPath('data.date', '2026-11-22')
        ->assertJsonPath('data.start_time', '09:00')
        ->assertJsonPath('data.end_time', null)
        ->assertJsonPath('data.created_by.id', $this->admin->id);
});

test('worker creates a worship in a program of their groups', function () use ($payload) {
    $this->actingAs($this->worker, 'sanctum')
        ->postJson(route('worships.store'), $payload($this->myProgram->id))
        ->assertCreated()
        ->assertJsonPath('data.created_by.id', $this->worker->id);
});

test('worker cannot create a worship in a program outside their groups', function () use ($payload) {
    $this->actingAs($this->worker, 'sanctum')
        ->postJson(route('worships.store'), $payload($this->otherProgram->id))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['program_id' => 'Programa não encontrado ou sem acesso.']);

    expect(Worship::count())->toBe(0);
});

test('worships cannot be created in inactive programs', function () use ($payload) {
    $inactive = Program::factory()->inactive()->create();

    $this->actingAs($this->admin, 'sanctum')
        ->postJson(route('worships.store'), $payload($inactive->id))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['program_id' => 'O programa está inactivo.']);
});

test('worship creation validates date and start time', function () use ($payload) {
    $this->actingAs($this->admin, 'sanctum')
        ->postJson(route('worships.store'), $payload($this->myProgram->id, [
            'date' => '22/11/2026',
            'start_time' => '10h',
        ]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors([
            'date' => 'A data deve estar no formato AAAA-MM-DD.',
            'start_time' => 'A hora de início deve estar no formato HH:MM.',
        ]);

    $this->actingAs($this->admin, 'sanctum')
        ->postJson(route('worships.store'), [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['program_id', 'name', 'date', 'start_time']);
});

test('end time sent on creation is ignored because it is only known later', function () use ($payload) {
    $this->actingAs($this->admin, 'sanctum')
        ->postJson(route('worships.store'), $payload($this->myProgram->id, ['end_time' => '11:30']))
        ->assertCreated()
        ->assertJsonPath('data.end_time', null);

    expect(Worship::sole()->end_time)->toBeNull();
});

test('worker finishes a worship of their programs with a valid end time', function () use ($payload) {
    $worshipId = $this->actingAs($this->worker, 'sanctum')
        ->postJson(route('worships.store'), $payload($this->myProgram->id, ['start_time' => '18:00']))
        ->assertCreated()
        ->json('data.id');

    $this->actingAs($this->worker, 'sanctum')
        ->patchJson(route('worships.finish', $worshipId), ['end_time' => '20:15'])
        ->assertOk()
        ->assertJsonPath('message', 'Culto finalizado com sucesso.')
        ->assertJsonPath('data.start_time', '18:00')
        ->assertJsonPath('data.end_time', '20:15');
});

test('finishing requires a valid end time after the start', function () {
    $worship = Worship::factory()->for($this->myProgram)->create(['start_time' => '18:00']);

    $this->actingAs($this->worker, 'sanctum')
        ->patchJson(route('worships.finish', $worship), [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['end_time' => 'Indique a hora de fim do culto.']);

    $this->actingAs($this->worker, 'sanctum')
        ->patchJson(route('worships.finish', $worship), ['end_time' => '8pm'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['end_time' => 'A hora de fim deve estar no formato HH:MM.']);

    $this->actingAs($this->worker, 'sanctum')
        ->patchJson(route('worships.finish', $worship), ['end_time' => '18:00'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['end_time' => 'A hora de fim deve ser posterior à hora de início.']);

    expect($worship->fresh()->end_time)->toBeNull();
});

test('a finished worship cannot be finished again, not even by an admin', function () {
    $worship = Worship::factory()->for($this->myProgram)->create(['start_time' => '18:00', 'end_time' => '20:00']);

    foreach ([$this->worker, $this->admin] as $user) {
        $this->actingAs($user, 'sanctum')
            ->patchJson(route('worships.finish', $worship), ['end_time' => '21:45'])
            ->assertStatus(409)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Este culto já foi finalizado e não pode ser alterado.');
    }

    expect(substr($worship->fresh()->end_time, 0, 5))->toBe('20:00');
});

test('worker cannot finish a worship outside their programs', function () {
    $worship = Worship::factory()->for($this->otherProgram)->create();

    $this->actingAs($this->worker, 'sanctum')
        ->patchJson(route('worships.finish', $worship), ['end_time' => '23:00'])
        ->assertForbidden()
        ->assertJsonPath('message', 'Não tem acesso a este culto.');
});

test('the regular update no longer changes the end time', function () {
    $worship = Worship::factory()->for($this->myProgram)->create(['start_time' => '18:00']);

    $this->actingAs($this->admin, 'sanctum')
        ->putJson(route('worships.update', $worship), ['end_time' => '20:00', 'title' => 'Novo tema'])
        ->assertOk()
        ->assertJsonPath('data.title', 'Novo tema')
        ->assertJsonPath('data.end_time', null);
});

test('worker only lists worships of their programs', function () {
    $mine = Worship::factory()->for($this->myProgram)->create();
    Worship::factory()->for($this->otherProgram)->create();

    $this->actingAs($this->worker, 'sanctum')
        ->getJson(route('worships.index'))
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $mine->id);

    $this->actingAs($this->admin, 'sanctum')
        ->getJson(route('worships.index'))
        ->assertOk()
        ->assertJsonCount(2, 'data');
});

test('worker loses access when the group is deactivated', function () {
    Worship::factory()->for($this->myProgram)->create();
    $this->worker->groups()->update(['is_active' => false]);

    $this->actingAs($this->worker, 'sanctum')
        ->getJson(route('worships.index'))
        ->assertOk()
        ->assertJsonCount(0, 'data');
});

test('worships are listed newest first and can be filtered', function () {
    Worship::factory()->for($this->myProgram)->create(['date' => '2026-11-01', 'name' => 'Culto de Oração']);
    Worship::factory()->for($this->myProgram)->create(['date' => '2026-11-15', 'name' => 'Culto de Domingo']);
    Worship::factory()->for($this->otherProgram)->create(['date' => '2026-11-08', 'name' => 'Vigília']);

    $this->actingAs($this->admin, 'sanctum')
        ->getJson(route('worships.index'))
        ->assertJsonPath('data.0.date', '2026-11-15')
        ->assertJsonPath('data.2.date', '2026-11-01');

    $this->actingAs($this->admin, 'sanctum')
        ->getJson(route('worships.index', ['program_id' => $this->otherProgram->id]))
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.name', 'Vigília');

    $this->actingAs($this->admin, 'sanctum')
        ->getJson(route('worships.index', ['date_from' => '2026-11-05', 'date_to' => '2026-11-10']))
        ->assertJsonCount(1, 'data');

    $this->actingAs($this->admin, 'sanctum')
        ->getJson(route('worships.index', ['date' => '2026-11-15']))
        ->assertJsonCount(1, 'data');

    $this->actingAs($this->admin, 'sanctum')
        ->getJson(route('worships.index', ['search' => 'oração']))
        ->assertJsonCount(1, 'data');
});

test('worker can view only worships of their programs', function () {
    $mine = Worship::factory()->for($this->myProgram)->create();
    $other = Worship::factory()->for($this->otherProgram)->create();

    $this->actingAs($this->worker, 'sanctum')
        ->getJson(route('worships.show', $mine))
        ->assertOk()
        ->assertJsonPath('data.id', $mine->id);

    $this->actingAs($this->worker, 'sanctum')
        ->getJson(route('worships.show', $other))
        ->assertForbidden()
        ->assertJsonPath('success', false)
        ->assertJsonPath('message', 'Não tem acesso a este culto.');
});

test('worker updates worships of their programs only', function () {
    $mine = Worship::factory()->for($this->myProgram)->create();
    $other = Worship::factory()->for($this->otherProgram)->create();

    $this->actingAs($this->worker, 'sanctum')
        ->putJson(route('worships.update', $mine), ['title' => 'Novo tema'])
        ->assertOk()
        ->assertJsonPath('data.title', 'Novo tema');

    $this->actingAs($this->worker, 'sanctum')
        ->putJson(route('worships.update', $other), ['title' => 'Novo tema'])
        ->assertForbidden();

    $this->actingAs($this->worker, 'sanctum')
        ->putJson(route('worships.update', $mine), ['program_id' => $this->otherProgram->id])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('program_id');
});

test('a finished worship cannot be edited', function () {
    $worship = Worship::factory()->for($this->myProgram)->finished()->create(['title' => 'Original']);

    $this->actingAs($this->admin, 'sanctum')
        ->putJson(route('worships.update', $worship), ['title' => 'Outro'])
        ->assertStatus(409)
        ->assertJsonPath('message', 'Este culto já foi finalizado e não pode ser alterado.');

    expect($worship->fresh()->title)->toBe('Original');
});

test('access is checked before the finished state', function () {
    $worship = Worship::factory()->for($this->otherProgram)->finished()->create();

    $this->actingAs($this->worker, 'sanctum')
        ->putJson(route('worships.update', $worship), ['title' => 'Outro'])
        ->assertForbidden()
        ->assertJsonPath('message', 'Não tem acesso a este culto.');
});

test('a finished worship can still be viewed and deleted by an admin', function () {
    $worship = Worship::factory()->for($this->myProgram)->finished()->create();

    $this->actingAs($this->worker, 'sanctum')->getJson(route('worships.show', $worship))->assertOk();
    $this->actingAs($this->admin, 'sanctum')->deleteJson(route('worships.destroy', $worship))->assertOk();
});

test('unknown resources return a friendly not found message', function () {
    $this->actingAs($this->admin, 'sanctum')
        ->getJson(route('worships.show', 999999))
        ->assertNotFound()
        ->assertJsonPath('success', false)
        ->assertJsonPath('message', 'Recurso não encontrado.');
});

test('only admins delete worships', function () {
    $worship = Worship::factory()->for($this->myProgram)->create();

    $this->actingAs($this->worker, 'sanctum')
        ->deleteJson(route('worships.destroy', $worship))
        ->assertForbidden()
        ->assertJsonPath('message', 'Apenas administradores podem eliminar cultos.');

    $this->actingAs($this->admin, 'sanctum')
        ->deleteJson(route('worships.destroy', $worship))
        ->assertOk();

    $this->assertModelMissing($worship);
});
