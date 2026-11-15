<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Worship\FinishWorshipRequest;
use App\Http\Requests\Worship\StoreWorshipRequest;
use App\Http\Requests\Worship\UpdateWorshipRequest;
use App\Http\Resources\WorshipResource;
use App\Models\Worship;
use App\Services\WorshipService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class WorshipController extends Controller
{
    use ApiResponse;

    public function __construct(private readonly WorshipService $worshipService) {}

    public function index(Request $request): JsonResponse
    {
        $worships = $this->worshipService->list(
            $request->user(),
            $request->only(['search', 'program_id', 'date', 'date_from', 'date_to', 'per_page'])
        );

        return WorshipResource::collection($worships)
            ->additional(['success' => true, 'message' => 'Sucesso'])
            ->response();
    }

    public function store(StoreWorshipRequest $request): JsonResponse
    {
        $worship = $this->worshipService->create($request->validated(), $request->user());

        return $this->created(new WorshipResource($worship), 'Culto criado com sucesso.');
    }

    public function show(Worship $worship): JsonResponse
    {
        Gate::authorize('view', $worship);

        return $this->success(new WorshipResource($worship->load(['program', 'creator'])));
    }

    public function update(UpdateWorshipRequest $request, Worship $worship): JsonResponse
    {
        $updated = $this->worshipService->update($worship, $request->validated());

        return $this->success(new WorshipResource($updated), 'Culto actualizado com sucesso.');
    }

    public function finish(FinishWorshipRequest $request, Worship $worship): JsonResponse
    {
        $finished = $this->worshipService->finish($worship, $request->validated('end_time'));

        return $this->success(new WorshipResource($finished), 'Culto finalizado com sucesso.');
    }

    public function destroy(Worship $worship): JsonResponse
    {
        Gate::authorize('delete', $worship);

        $this->worshipService->delete($worship);

        return $this->success(message: 'Culto eliminado com sucesso.');
    }
}
