<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Location\StoreLocationRequest;
use App\Http\Requests\Location\UpdateLocationRequest;
use App\Http\Resources\LocationResource;
use App\Models\Location;
use App\Services\LocationService;
use App\Traits\ApiResponse;
use DomainException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LocationController extends Controller
{
    use ApiResponse;

    public function __construct(private readonly LocationService $locationService) {}

    public function index(Request $request): JsonResponse
    {
        $locations = $this->locationService->list($request->only(['search', 'province_id', 'per_page']));

        return LocationResource::collection($locations)
            ->additional(['success' => true, 'message' => 'Sucesso'])
            ->response();
    }

    public function store(StoreLocationRequest $request): JsonResponse
    {
        try {
            $location = $this->locationService->create($request->validated());
        } catch (UniqueConstraintViolationException) {
            return $this->duplicateNameError();
        }

        return $this->created(new LocationResource($location), 'Local criado com sucesso.');
    }

    public function show(Location $location): JsonResponse
    {
        return $this->success(new LocationResource($location->load('province')));
    }

    public function update(UpdateLocationRequest $request, Location $location): JsonResponse
    {
        try {
            $updated = $this->locationService->update($location, $request->validated());
        } catch (UniqueConstraintViolationException) {
            return $this->duplicateNameError();
        }

        return $this->success(new LocationResource($updated), 'Local actualizado com sucesso.');
    }

    public function destroy(Location $location): JsonResponse
    {
        try {
            $this->locationService->delete($location);
        } catch (DomainException $e) {
            return $this->error($e->getMessage(), 422);
        }

        return $this->success(message: 'Local eliminado com sucesso.');
    }

    private function duplicateNameError(): JsonResponse
    {
        return $this->error('Já existe um local com este nome nesta província.', 422, [
            'name' => ['Já existe um local com este nome nesta província.'],
        ]);
    }
}
