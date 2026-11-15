<?php

use App\Models\ClassGroup;
use App\Models\Group;
use App\Models\Location;
use App\Models\Person;
use App\Models\Program;
use App\Models\User;
use App\Models\Worship;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
    $this->worker = User::factory()->create();

    $this->myProgram = Program::factory()->create();
    $this->otherProgram = Program::factory()->create();

    $group = Group::factory()->create();
    $group->users()->attach($this->worker);
    $group->programs()->attach($this->myProgram);

    $this->worship = Worship::factory()->for($this->myProgram)->create();
});

$newPerson = fn (array $overrides = []): array => [
    'phone' => '+258841112233',
    'name' => 'Maria',
    'surname' => 'Cossa',
    'gender' => 'female',
    'class_group_id' => ClassGroup::factory()->create()->id,
    ...$overrides,
];

test('a new phone creates the person as visitor and registers the attendance', function () use ($newPerson) {
    $location = Location::factory()->create();
    $classGroup = ClassGroup::factory()->create(['name' => 'Turma de Jovens']);

    $this->actingAs($this->worker, 'sanctum')
        ->postJson(route('worships.attendances.store', $this->worship), $newPerson([
            'location_id' => $location->id,
            'class_group_id' => $classGroup->id,
        ]))
        ->assertCreated()
        ->assertJsonPath('message', 'Pessoa registada e presença confirmada.')
        ->assertJsonPath('person_created', true)
        ->assertJsonPath('promoted_to_member', false)
        ->assertJsonPath('data.full_name', 'Maria Cossa')
        ->assertJsonPath('data.type', 'visitor')
        ->assertJsonPath('data.attendances_count', 1)
        ->assertJsonPath('data.attendances_to_membership', 4)
        ->assertJsonPath('data.location.id', $location->id)
        ->assertJsonPath('data.class_group.name', 'Turma de Jovens');

    $person = Person::sole();
    expect($person->created_by)->toBe($this->worker->id);
    $this->assertDatabaseHas('person_worship', [
        'person_id' => $person->id,
        'worship_id' => $this->worship->id,
        'created_by' => $this->worker->id,
    ]);
});

test('an existing phone reuses the person instead of creating another', function () {
    $person = Person::factory()->create(['phone' => '+258841112233', 'name' => 'Maria']);

    $this->actingAs($this->worker, 'sanctum')
        ->postJson(route('worships.attendances.store', $this->worship), ['phone' => '84 111 2233'])
        ->assertCreated()
        ->assertJsonPath('message', 'Presença registada.')
        ->assertJsonPath('person_created', false)
        ->assertJsonPath('data.id', $person->id);

    expect(Person::count())->toBe(1);
});

test('data sent for an existing phone does not overwrite the person', function () use ($newPerson) {
    Person::factory()->create(['phone' => '+258841112233', 'name' => 'Maria', 'surname' => 'Cossa']);

    $this->actingAs($this->worker, 'sanctum')
        ->postJson(route('worships.attendances.store', $this->worship), $newPerson(['name' => 'Outro', 'surname' => 'Nome']))
        ->assertCreated()
        ->assertJsonPath('data.full_name', 'Maria Cossa');
});

test('a new phone requires name, surname and gender but not class', function () use ($newPerson) {
    $this->actingAs($this->worker, 'sanctum')
        ->postJson(route('worships.attendances.store', $this->worship), ['phone' => '+258849999999'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors([
            'name' => 'Número não registado: indique o nome para registar a pessoa.',
            'surname' => 'Número não registado: indique o apelido para registar a pessoa.',
            'gender' => 'Número não registado: indique o género para registar a pessoa.',
        ])
        ->assertJsonMissingValidationErrors('class_group_id');

    expect(Person::count())->toBe(0);

    $this->actingAs($this->worker, 'sanctum')
        ->postJson(route('worships.attendances.store', $this->worship), $newPerson(['class_group_id' => null]))
        ->assertCreated()
        ->assertJsonPath('data.class_group', null);
});

test('the phone must follow the +258 format', function () use ($newPerson) {
    $this->actingAs($this->worker, 'sanctum')
        ->postJson(route('worships.attendances.store', $this->worship), $newPerson(['phone' => '12345']))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['phone' => 'O número de celular deve estar no formato +258XXXXXXXXX.']);
});

test('a person cannot be registered twice in the same worship', function () use ($newPerson) {
    $this->actingAs($this->worker, 'sanctum')
        ->postJson(route('worships.attendances.store', $this->worship), $newPerson())
        ->assertCreated();

    $this->actingAs($this->worker, 'sanctum')
        ->postJson(route('worships.attendances.store', $this->worship), ['phone' => '+258841112233'])
        ->assertUnprocessable()
        ->assertJsonPath('message', 'Maria Cossa já está registado(a) neste culto.');

    expect($this->worship->people()->count())->toBe(1);
});

test('a visitor becomes member at the fifth different worship of any program', function () {
    $person = Person::factory()->create(['phone' => '+258841112233']);
    $previous = Worship::factory()->count(4)->sequence(
        ['program_id' => $this->myProgram->id],
        ['program_id' => $this->otherProgram->id],
    )->create();
    $person->worships()->attach($previous);

    $this->actingAs($this->worker, 'sanctum')
        ->postJson(route('worships.attendances.store', $this->worship), ['phone' => '+258841112233'])
        ->assertCreated()
        ->assertJsonPath('promoted_to_member', true)
        ->assertJsonPath('message', "Presença registada. {$person->full_name} passou a ser membro!")
        ->assertJsonPath('data.type', 'member')
        ->assertJsonPath('data.attendances_count', 5)
        ->assertJsonPath('data.attendances_to_membership', 0);

    expect($person->fresh()->became_member_at)->not->toBeNull();
});

test('a visitor with four worships stays visitor', function () {
    $person = Person::factory()->create(['phone' => '+258841112233']);
    $person->worships()->attach(Worship::factory()->count(3)->create());

    $this->actingAs($this->worker, 'sanctum')
        ->postJson(route('worships.attendances.store', $this->worship), ['phone' => '+258841112233'])
        ->assertCreated()
        ->assertJsonPath('promoted_to_member', false)
        ->assertJsonPath('data.type', 'visitor')
        ->assertJsonPath('data.attendances_to_membership', 1);
});

test('a member keeps the original membership date on new attendances', function () {
    $person = Person::factory()->member()->create(['phone' => '+258841112233', 'became_member_at' => '2026-01-10 10:00:00']);

    $this->actingAs($this->worker, 'sanctum')
        ->postJson(route('worships.attendances.store', $this->worship), ['phone' => '+258841112233'])
        ->assertCreated()
        ->assertJsonPath('promoted_to_member', false)
        ->assertJsonPath('data.became_member_at', '2026-01-10 10:00:00');
});

test('attendances cannot be added to or removed from a finished worship', function () use ($newPerson) {
    $person = Person::factory()->create();
    $finished = Worship::factory()->for($this->myProgram)->finished()->create();
    $finished->people()->attach($person);

    foreach ([$this->worker, $this->admin] as $user) {
        $this->actingAs($user, 'sanctum')
            ->postJson(route('worships.attendances.store', $finished), $newPerson())
            ->assertStatus(409)
            ->assertJsonPath('message', 'Este culto já foi finalizado e não pode ser alterado.');

        $this->actingAs($user, 'sanctum')
            ->deleteJson(route('worships.attendances.destroy', [$finished, $person]))
            ->assertStatus(409);
    }

    expect(Person::count())->toBe(1)
        ->and($finished->people()->count())->toBe(1);

    $this->actingAs($this->worker, 'sanctum')
        ->getJson(route('worships.attendances.index', $finished))
        ->assertOk()
        ->assertJsonCount(1, 'data');
});

test('worker cannot register attendance in a worship outside their programs', function () use ($newPerson) {
    $other = Worship::factory()->for($this->otherProgram)->create();

    $this->actingAs($this->worker, 'sanctum')
        ->postJson(route('worships.attendances.store', $other), $newPerson())
        ->assertForbidden()
        ->assertJsonPath('message', 'Não tem acesso a este culto.');

    expect(Person::count())->toBe(0);
});

test('attendance list shows the people and the visitor/member summary', function () {
    $visitors = Person::factory()->count(2)->create();
    $member = Person::factory()->member()->create();
    $this->worship->people()->attach([...$visitors->pluck('id'), $member->id]);

    $this->actingAs($this->worker, 'sanctum')
        ->getJson(route('worships.attendances.index', $this->worship))
        ->assertOk()
        ->assertJsonCount(3, 'data')
        ->assertJsonPath('summary', ['total' => 3, 'visitors' => 2, 'members' => 1]);

    $this->actingAs($this->worker, 'sanctum')
        ->getJson(route('worships.attendances.index', [$this->worship, 'type' => 'member']))
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $member->id);
});

test('attendance list search matches the full name', function () {
    $maria = Person::factory()->create(['name' => 'Maria', 'surname' => 'Cossa']);
    $joao = Person::factory()->create(['name' => 'João', 'surname' => 'Cossa']);
    $this->worship->people()->attach([$maria->id, $joao->id]);

    $this->actingAs($this->worker, 'sanctum')
        ->getJson(route('worships.attendances.index', [$this->worship, 'search' => 'maria cossa']))
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $maria->id);
});

test('worker cannot list attendances of a worship outside their programs', function () {
    $other = Worship::factory()->for($this->otherProgram)->create();

    $this->actingAs($this->worker, 'sanctum')
        ->getJson(route('worships.attendances.index', $other))
        ->assertForbidden();
});

test('removing an attendance keeps the person and does not demote members', function () {
    $person = Person::factory()->member()->create();
    $this->worship->people()->attach($person);

    $this->actingAs($this->worker, 'sanctum')
        ->deleteJson(route('worships.attendances.destroy', [$this->worship, $person]))
        ->assertOk();

    $this->assertModelExists($person);
    expect($this->worship->people()->count())->toBe(0)
        ->and($person->fresh()->isMember())->toBeTrue();

    $this->actingAs($this->worker, 'sanctum')
        ->deleteJson(route('worships.attendances.destroy', [$this->worship, $person]))
        ->assertNotFound()
        ->assertJsonPath('message', 'Esta pessoa não está registada neste culto.');
});
