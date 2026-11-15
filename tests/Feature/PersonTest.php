<?php

use App\Models\ClassGroup;
use App\Models\Location;
use App\Models\Person;
use App\Models\User;
use App\Models\Worship;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
    $this->worker = User::factory()->create();
});

$payload = fn (array $overrides = []): array => [
    'name' => ' Maria ',
    'surname' => 'Cossa',
    'gender' => 'female',
    'class_group_id' => ClassGroup::factory()->create()->id,
    'phone' => '84 111 2233',
    ...$overrides,
];

test('guests cannot access people', function () {
    $this->getJson(route('people.index'))->assertUnauthorized();
});

test('worker registers a person as visitor with normalized phone', function () use ($payload) {
    $this->actingAs($this->worker, 'sanctum')
        ->postJson(route('people.store'), $payload())
        ->assertCreated()
        ->assertJsonPath('data.name', 'Maria')
        ->assertJsonPath('data.phone', '+258841112233')
        ->assertJsonPath('data.type', 'visitor')
        ->assertJsonPath('data.type_label', 'Visitante')
        ->assertJsonPath('data.gender_label', 'Feminino')
        ->assertJsonPath('data.attendances_count', 0)
        ->assertJsonPath('data.attendances_to_membership', 5)
        ->assertJsonPath('data.created_by.id', $this->worker->id);
});

test('registering a person with an existing phone is rejected and names the owner', function () use ($payload) {
    $existing = Person::factory()->create(['phone' => '+258841112233', 'name' => 'Maria', 'surname' => 'Cossa']);

    $this->actingAs($this->worker, 'sanctum')
        ->postJson(route('people.store'), $payload(['phone' => '258841112233', 'name' => 'Outra']))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['phone' => "Já existe uma pessoa com este número: Maria Cossa (ID {$existing->id})."]);

    expect(Person::count())->toBe(1);
});

test('person registration validates input', function () {
    $this->actingAs($this->worker, 'sanctum')
        ->postJson(route('people.store'), ['gender' => 'x', 'phone' => '123', 'location_id' => 999])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['name', 'surname', 'gender', 'phone', 'location_id'])
        ->assertJsonMissingValidationErrors('class_group_id');

    $this->actingAs($this->worker, 'sanctum')
        ->postJson(route('people.store'), ['class_group_id' => 999])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['class_group_id' => 'Turma não encontrada.']);
});

test('a person can be registered without a class and the class removed later', function () use ($payload) {
    $personId = $this->actingAs($this->worker, 'sanctum')
        ->postJson(route('people.store'), $payload(['class_group_id' => null]))
        ->assertCreated()
        ->assertJsonPath('data.class_group_id', null)
        ->assertJsonPath('data.class_group', null)
        ->json('data.id');

    $classGroup = ClassGroup::factory()->create();

    $this->actingAs($this->worker, 'sanctum')
        ->putJson(route('people.update', $personId), ['class_group_id' => $classGroup->id])
        ->assertOk()
        ->assertJsonPath('data.class_group.id', $classGroup->id);

    $this->actingAs($this->worker, 'sanctum')
        ->putJson(route('people.update', $personId), ['class_group_id' => null])
        ->assertOk()
        ->assertJsonPath('data.class_group_id', null);
});

test('people can be filtered and moved between classes', function () {
    [$youth, $adults] = ClassGroup::factory()->count(2)->create();
    $person = Person::factory()->for($youth)->create();
    Person::factory()->for($adults)->create();

    $this->actingAs($this->worker, 'sanctum')
        ->getJson(route('people.index', ['class_group_id' => $youth->id]))
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.class_group.id', $youth->id);

    $this->actingAs($this->worker, 'sanctum')
        ->putJson(route('people.update', $person), ['class_group_id' => $adults->id])
        ->assertOk()
        ->assertJsonPath('data.class_group.id', $adults->id);
});

test('only admins may set the person type', function () use ($payload) {
    $this->actingAs($this->worker, 'sanctum')
        ->postJson(route('people.store'), $payload(['type' => 'member']))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['type' => 'Apenas administradores podem definir o tipo da pessoa.']);

    $this->actingAs($this->admin, 'sanctum')
        ->postJson(route('people.store'), $payload(['type' => 'member']))
        ->assertCreated()
        ->assertJsonPath('data.type', 'member')
        ->assertJsonPath('data.attendances_to_membership', 0);

    expect(Person::sole()->became_member_at)->not->toBeNull();
});

test('lookup finds a person by any phone variation', function () {
    $person = Person::factory()->create(['phone' => '+258841112233']);

    $this->actingAs($this->worker, 'sanctum')
        ->getJson(route('people.lookup', ['phone' => '841112233']))
        ->assertOk()
        ->assertJsonPath('data.id', $person->id);

    $this->actingAs($this->worker, 'sanctum')
        ->getJson(route('people.lookup', ['phone' => '849999999']))
        ->assertNotFound()
        ->assertJsonPath('message', 'Não existe nenhuma pessoa com este número.');
});

test('people list filters by type, search and location', function () {
    $location = Location::factory()->create();
    Person::factory()->create(['name' => 'Maria', 'location_id' => $location->id]);
    Person::factory()->member()->create(['name' => 'João']);
    Person::factory()->create(['name' => 'Ana', 'phone' => '+258821234567']);

    $this->actingAs($this->worker, 'sanctum')
        ->getJson(route('people.index', ['type' => 'member']))
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.name', 'João');

    $this->actingAs($this->worker, 'sanctum')
        ->getJson(route('people.index', ['search' => '82 123']))
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.name', 'Ana');

    $this->actingAs($this->worker, 'sanctum')
        ->getJson(route('people.index', ['province_id' => $location->province_id]))
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.name', 'Maria');
});

test('search matches each word of the full name and ignores phones for text-only terms', function () {
    Person::factory()->create(['name' => 'Maria', 'surname' => 'Cossa', 'phone' => '+258841112233']);
    Person::factory()->create(['name' => 'Maria', 'surname' => 'Langa']);
    Person::factory()->create(['name' => 'João', 'surname' => 'Cossa']);

    $this->actingAs($this->worker, 'sanctum')
        ->getJson(route('people.index', ['search' => 'maria cos']))
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.surname', 'Cossa');

    $this->actingAs($this->worker, 'sanctum')
        ->getJson(route('people.index', ['search' => 'Cossa']))
        ->assertJsonCount(2, 'data');

    $this->actingAs($this->worker, 'sanctum')
        ->getJson(route('people.index', ['search' => 'Ninguém']))
        ->assertJsonCount(0, 'data');

    $this->actingAs($this->worker, 'sanctum')
        ->getJson(route('people.index', ['search' => '84 111']))
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.name', 'Maria');
});

test('show includes the attendances count', function () {
    $person = Person::factory()->create();
    $person->worships()->attach(Worship::factory()->count(2)->create());

    $this->actingAs($this->worker, 'sanctum')
        ->getJson(route('people.show', $person))
        ->assertOk()
        ->assertJsonPath('data.attendances_count', 2)
        ->assertJsonPath('data.attendances_to_membership', 3);
});

test('updating keeps the phone unique', function () {
    Person::factory()->create(['phone' => '+258841112233']);
    $person = Person::factory()->create();

    $this->actingAs($this->worker, 'sanctum')
        ->putJson(route('people.update', $person), ['phone' => '841112233'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('phone');

    $this->actingAs($this->worker, 'sanctum')
        ->putJson(route('people.update', $person), ['phone' => $person->phone, 'surname' => 'Novo'])
        ->assertOk()
        ->assertJsonPath('data.surname', 'Novo');
});

test('admin changes the type and the membership date follows', function () {
    $person = Person::factory()->create();

    $this->actingAs($this->worker, 'sanctum')
        ->putJson(route('people.update', $person), ['type' => 'member'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('type');

    $this->actingAs($this->admin, 'sanctum')
        ->putJson(route('people.update', $person), ['type' => 'member'])
        ->assertOk()
        ->assertJsonPath('data.type', 'member');
    expect($person->fresh()->became_member_at)->not->toBeNull();

    $this->actingAs($this->admin, 'sanctum')
        ->putJson(route('people.update', $person), ['type' => 'visitor'])
        ->assertOk();
    expect($person->fresh()->became_member_at)->toBeNull();
});

test('only admins delete people and attendances go with them', function () {
    $person = Person::factory()->create();
    $worship = Worship::factory()->create();
    $person->worships()->attach($worship);

    $this->actingAs($this->worker, 'sanctum')
        ->deleteJson(route('people.destroy', $person))
        ->assertForbidden();

    $this->actingAs($this->admin, 'sanctum')
        ->deleteJson(route('people.destroy', $person))
        ->assertOk();

    $this->assertModelMissing($person);
    $this->assertDatabaseMissing('person_worship', ['person_id' => $person->id]);
});
