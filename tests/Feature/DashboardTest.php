<?php

use App\Models\ClassGroup;
use App\Models\Person;
use App\Models\Program;
use App\Models\User;
use App\Models\Worship;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();

    $this->sunday = Program::factory()->create(['name' => 'Culto de Domingo']);
    $this->youth = Program::factory()->create(['name' => 'Jovens']);
    Program::factory()->inactive()->create(['name' => 'Antigo']);

    $this->member = Person::factory()->member()->create(['gender' => 'female']);
    $this->visitor = Person::factory()->create(['gender' => 'male']);
    $this->otherVisitor = Person::factory()->create(['gender' => 'female']);

    $this->first = Worship::factory()->for($this->sunday)->create(['date' => '2026-11-01', 'start_time' => '09:00']);
    $this->second = Worship::factory()->for($this->sunday)->create(['date' => '2026-11-08', 'start_time' => '09:00']);
    $this->youthWorship = Worship::factory()->for($this->youth)->create(['date' => '2026-10-20', 'start_time' => '18:00']);

    $this->first->people()->attach([$this->member->id, $this->visitor->id]);
    $this->second->people()->attach([$this->member->id, $this->visitor->id, $this->otherVisitor->id]);
    $this->youthWorship->people()->attach([$this->member->id]);
});

test('only admins access the dashboard', function () {
    $worker = User::factory()->create();

    $this->actingAs($worker, 'sanctum')->getJson(route('dashboard.overview'))->assertForbidden();
    $this->actingAs($worker, 'sanctum')->getJson(route('dashboard.program', $this->sunday))->assertForbidden();
    $this->actingAs($worker, 'sanctum')->getJson(route('dashboard.worship', $this->first))->assertForbidden();
});

test('overview returns people, programs, attendance and per program totals', function () {
    $response = $this->actingAs($this->admin, 'sanctum')
        ->getJson(route('dashboard.overview'))
        ->assertOk()
        ->assertJsonPath('data.people.total', 3)
        ->assertJsonPath('data.people.members', 1)
        ->assertJsonPath('data.people.visitors', 2)
        ->assertJsonPath('data.programs.total', 3)
        ->assertJsonPath('data.programs.active', 2)
        ->assertJsonPath('data.attendance.worships', 3)
        ->assertJsonPath('data.attendance.attendances', 6)
        ->assertJsonPath('data.attendance.unique_people', 3)
        ->assertJsonPath('data.attendance.member_attendances', 3)
        ->assertJsonPath('data.attendance.visitor_attendances', 3)
        ->assertJsonPath('data.attendance.average_per_worship', 2);

    $byProgram = collect($response->json('data.by_program'))->keyBy('name');
    expect($byProgram)->toHaveCount(3)
        ->and($byProgram['Culto de Domingo'])->toMatchArray(['worships' => 2, 'attendances' => 5, 'average_per_worship' => 2.5, 'last_worship_date' => '2026-11-08'])
        ->and($byProgram['Jovens'])->toMatchArray(['worships' => 1, 'attendances' => 1])
        ->and($byProgram['Antigo'])->toMatchArray(['worships' => 0, 'attendances' => 0, 'last_worship_date' => null]);
});

test('overview filters by period and program', function () {
    $this->actingAs($this->admin, 'sanctum')
        ->getJson(route('dashboard.overview', ['date_from' => '2026-11-01', 'date_to' => '2026-11-30']))
        ->assertOk()
        ->assertJsonPath('data.attendance.worships', 2)
        ->assertJsonPath('data.attendance.attendances', 5);

    $this->actingAs($this->admin, 'sanctum')
        ->getJson(route('dashboard.overview', ['program_id' => $this->youth->id]))
        ->assertOk()
        ->assertJsonPath('data.attendance.worships', 1)
        ->assertJsonCount(1, 'data.by_program')
        ->assertJsonPath('data.by_program.0.name', 'Jovens');
});

test('overview validates the filters', function () {
    $this->actingAs($this->admin, 'sanctum')
        ->getJson(route('dashboard.overview', ['date_from' => '2026-11-10', 'date_to' => '2026-11-01']))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['date_to' => 'A data final deve ser igual ou posterior à data inicial.']);
});

test('program dashboard lists its worships with counts and stats', function () {
    $this->actingAs($this->admin, 'sanctum')
        ->getJson(route('dashboard.program', $this->sunday))
        ->assertOk()
        ->assertJsonPath('program.name', 'Culto de Domingo')
        ->assertJsonPath('stats.worships', 2)
        ->assertJsonPath('stats.attendances', 5)
        ->assertJsonPath('stats.unique_people', 3)
        ->assertJsonPath('stats.highest_attendance.worship_id', $this->second->id)
        ->assertJsonPath('stats.highest_attendance.count', 3)
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.id', $this->second->id)
        ->assertJsonPath('data.0.attendances_count', 3)
        ->assertJsonPath('data.0.members_count', 1)
        ->assertJsonPath('data.0.visitors_count', 2);
});

test('program dashboard filters worships by period', function () {
    $this->actingAs($this->admin, 'sanctum')
        ->getJson(route('dashboard.program', [$this->sunday, 'date_to' => '2026-11-05']))
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('stats.worships', 1)
        ->assertJsonPath('stats.attendances', 2);
});

test('worship dashboard breaks attendance down and counts first timers', function () {
    $youthClass = ClassGroup::factory()->create(['name' => 'Jovens']);
    $this->otherVisitor->update(['class_group_id' => $youthClass->id]);

    $this->actingAs($this->admin, 'sanctum')
        ->getJson(route('dashboard.worship', $this->second))
        ->assertOk()
        ->assertJsonPath('data.worship.id', $this->second->id)
        ->assertJsonPath('data.stats.total', 3)
        ->assertJsonPath('data.stats.members', 1)
        ->assertJsonPath('data.stats.visitors', 2)
        ->assertJsonPath('data.stats.first_time', 1)
        ->assertJsonPath('data.stats.by_gender', ['male' => 1, 'female' => 2])
        ->assertJsonCount(3, 'data.stats.by_class_group');

    $this->member->update(['class_group_id' => null]);

    $byClass = collect($this->actingAs($this->admin, 'sanctum')
        ->getJson(route('dashboard.worship', $this->second))
        ->json('data.stats.by_class_group'));
    expect($byClass->firstWhere('id', null))->toMatchArray(['name' => 'Sem turma', 'total' => 1]);

    $this->actingAs($this->admin, 'sanctum')
        ->getJson(route('dashboard.worship', $this->youthWorship))
        ->assertJsonPath('data.stats.first_time', 1);

    $this->actingAs($this->admin, 'sanctum')
        ->getJson(route('dashboard.worship', $this->first))
        ->assertJsonPath('data.stats.first_time', 1);
});

test('attendance list filters by gender and class', function () {
    $classGroup = ClassGroup::factory()->create();
    $this->visitor->update(['class_group_id' => $classGroup->id]);

    $this->actingAs($this->admin, 'sanctum')
        ->getJson(route('worships.attendances.index', [$this->second, 'gender' => 'female']))
        ->assertOk()
        ->assertJsonCount(2, 'data');

    $this->actingAs($this->admin, 'sanctum')
        ->getJson(route('worships.attendances.index', [$this->second, 'class_group_id' => $classGroup->id]))
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $this->visitor->id);
});
