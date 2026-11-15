<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Worship;
use Illuminate\Auth\Access\Response;

class WorshipPolicy
{
    /**
     * Admin e workers podem listar; a listagem já é filtrada pelos programas acessíveis.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Worship $worship): Response
    {
        return $this->allowIfProgramAccessible($user, $worship);
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Worship $worship): Response
    {
        return $this->allowIfOpenAndAccessible($user, $worship);
    }

    /**
     * Finalizar é definitivo: um culto finalizado não volta a ser aberto nem corrigido.
     */
    public function finish(User $user, Worship $worship): Response
    {
        return $this->allowIfOpenAndAccessible($user, $worship);
    }

    /**
     * Registar ou remover presenças só enquanto o culto está em curso.
     */
    public function attend(User $user, Worship $worship): Response
    {
        return $this->allowIfOpenAndAccessible($user, $worship);
    }

    public function delete(User $user, Worship $worship): Response
    {
        return $user->isAdmin()
            ? Response::allow()
            : Response::deny('Apenas administradores podem eliminar cultos.');
    }

    /**
     * Acesso ao programa primeiro (403); depois, culto finalizado (409 Conflict).
     */
    private function allowIfOpenAndAccessible(User $user, Worship $worship): Response
    {
        $access = $this->allowIfProgramAccessible($user, $worship);

        if ($access->denied()) {
            return $access;
        }

        return $worship->isFinished()
            ? Response::denyWithStatus(409, 'Este culto já foi finalizado e não pode ser alterado.')
            : Response::allow();
    }

    private function allowIfProgramAccessible(User $user, Worship $worship): Response
    {
        return $user->canAccessProgram($worship->program_id)
            ? Response::allow()
            : Response::deny('Não tem acesso a este culto.');
    }
}
