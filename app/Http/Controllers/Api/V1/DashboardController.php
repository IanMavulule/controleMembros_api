<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\DashboardFilterRequest;
use App\Http\Resources\ProgramResource;
use App\Http\Resources\WorshipResource;
use App\Models\Program;
use App\Models\Worship;
use App\Services\DashboardService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;

class DashboardController extends Controller
{
    use ApiResponse;

    public function __construct(private readonly DashboardService $dashboardService) {}

    public function overview(DashboardFilterRequest $request): JsonResponse
    {
        return $this->success($this->dashboardService->overview($request->validated()));
    }

    public function program(DashboardFilterRequest $request, Program $program): JsonResponse
    {
        $result = $this->dashboardService->program($program, $request->validated());

        return WorshipResource::collection($result['worships'])
            ->additional([
                'success' => true,
                'message' => 'Sucesso',
                'program' => (new ProgramResource($result['program']))->resolve(),
                'stats' => $result['stats'],
            ])
            ->response();
    }

    public function worship(Worship $worship): JsonResponse
    {
        return $this->success([
            'worship' => (new WorshipResource($worship->load(['program', 'creator'])))->resolve(),
            'stats' => $this->dashboardService->worship($worship),
        ]);
    }
}
