<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Attendance\StoreAttendanceRequest;
use App\Http\Resources\PersonResource;
use App\Models\Person;
use App\Models\Worship;
use App\Services\AttendanceService;
use App\Traits\ApiResponse;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class AttendanceController extends Controller
{
    use ApiResponse;

    public function __construct(private readonly AttendanceService $attendanceService) {}

    public function index(Request $request, Worship $worship): JsonResponse
    {
        Gate::authorize('view', $worship);

        $people = $this->attendanceService->list($worship, $request->only(['search', 'type', 'gender', 'class_group_id', 'per_page']));

        return PersonResource::collection($people)
            ->additional([
                'success' => true,
                'message' => 'Sucesso',
                'summary' => $this->attendanceService->summary($worship),
            ])
            ->response();
    }

    public function store(StoreAttendanceRequest $request, Worship $worship): JsonResponse
    {
        try {
            $result = $this->attendanceService->register($worship, $request->validated(), $request->user());
        } catch (DomainException $e) {
            return $this->error($e->getMessage(), 422);
        }

        $message = match (true) {
            $result['promoted'] => 'Presença registada. '.$result['person']->full_name.' passou a ser membro!',
            $result['person_created'] => 'Pessoa registada e presença confirmada.',
            default => 'Presença registada.',
        };

        return (new PersonResource($result['person']))
            ->additional([
                'success' => true,
                'message' => $message,
                'person_created' => $result['person_created'],
                'promoted_to_member' => $result['promoted'],
            ])
            ->response()
            ->setStatusCode(201);
    }

    public function destroy(Worship $worship, Person $person): JsonResponse
    {
        Gate::authorize('attend', $worship);

        try {
            $this->attendanceService->remove($worship, $person);
        } catch (DomainException $e) {
            return $this->notFound($e->getMessage());
        }

        return $this->success(message: 'Presença removida com sucesso.');
    }
}
