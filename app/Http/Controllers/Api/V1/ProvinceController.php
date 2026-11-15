<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Province\StoreProvinceRequest;
use App\Http\Requests\Province\UpdateProvinceRequest;
use App\Http\Resources\ProvinceResource;
use App\Models\Province;
use App\Services\ProvinceService;
use App\Traits\ApiResponse;
use DomainException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProvinceController extends Controller
{
    use ApiResponse;

    public function __construct(private readonly ProvinceService $provinceService) {}

    public function index(Request $request): JsonResponse
    {
        $provinces = $this->provinceService->list($request->only(['search']));

        return $this->success(ProvinceResource::collection($provinces));
    }

    public function store(StoreProvinceRequest $request): JsonResponse
    {
        try {
            $province = $this->provinceService->create($request->validated());
        } catch (UniqueConstraintViolationException) {
            return $this->duplicateNameError();
        }

        return $this->created(new ProvinceResource($province), 'Província criada com sucesso.');
    }

    public function show(Province $province): JsonResponse
    {
        return $this->success(new ProvinceResource($province));
    }

    public function update(UpdateProvinceRequest $request, Province $province): JsonResponse
    {
        try {
            $updated = $this->provinceService->update($province, $request->validated());
        } catch (UniqueConstraintViolationException) {
            return $this->duplicateNameError();
        }

        return $this->success(new ProvinceResource($updated), 'Província actualizada com sucesso.');
    }

    public function destroy(Province $province): JsonResponse
    {
        try {
            $this->provinceService->delete($province);
        } catch (DomainException $e) {
            return $this->error($e->getMessage(), 422);
        }

        return $this->success(message: 'Província eliminada com sucesso.');
    }

    /**
     * Resposta para o caso raro de dois pedidos simultâneos passarem a validação
     * com o mesmo nome: o índice único do code trava o segundo.
     */
    private function duplicateNameError(): JsonResponse
    {
        return $this->error('Já existe uma província com este nome.', 422, [
            'name' => ['Já existe uma província com este nome.'],
        ]);
    }
}
