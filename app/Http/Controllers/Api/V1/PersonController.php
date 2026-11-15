<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Person\StorePersonRequest;
use App\Http\Requests\Person\UpdatePersonRequest;
use App\Http\Resources\PersonResource;
use App\Models\Person;
use App\Services\PersonService;
use App\Traits\ApiResponse;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PersonController extends Controller
{
    use ApiResponse;

    public function __construct(private readonly PersonService $personService) {}

    public function index(Request $request): JsonResponse
    {
        $people = $this->personService->list(
            $request->only(['search', 'type', 'gender', 'location_id', 'province_id', 'class_group_id', 'per_page'])
        );

        return PersonResource::collection($people)
            ->additional(['success' => true, 'message' => 'Sucesso'])
            ->response();
    }

    /**
     * Verifica se já existe alguém com o número, antes de registar uma pessoa nova.
     */
    public function lookup(Request $request): JsonResponse
    {
        $request->validate(['phone' => ['required', 'string']]);

        $person = $this->personService->findByPhone($request->string('phone'));

        if (! $person) {
            return $this->notFound('Não existe nenhuma pessoa com este número.');
        }

        return $this->success(new PersonResource($this->personService->loadDetails($person)));
    }

    public function store(StorePersonRequest $request): JsonResponse
    {
        try {
            $person = $this->personService->create($request->validated(), $request->user());
        } catch (UniqueConstraintViolationException) {
            return $this->duplicatePhoneError();
        }

        return $this->created(new PersonResource($person), 'Pessoa registada com sucesso.');
    }

    public function show(Person $person): JsonResponse
    {
        return $this->success(new PersonResource($this->personService->loadDetails($person)));
    }

    public function update(UpdatePersonRequest $request, Person $person): JsonResponse
    {
        try {
            $updated = $this->personService->update($person, $request->validated());
        } catch (UniqueConstraintViolationException) {
            return $this->duplicatePhoneError();
        }

        return $this->success(new PersonResource($updated), 'Pessoa actualizada com sucesso.');
    }

    public function destroy(Person $person): JsonResponse
    {
        $this->personService->delete($person);

        return $this->success(message: 'Pessoa eliminada com sucesso.');
    }

    private function duplicatePhoneError(): JsonResponse
    {
        return $this->error('Já existe uma pessoa com este número.', 422, [
            'phone' => ['Já existe uma pessoa com este número.'],
        ]);
    }
}
