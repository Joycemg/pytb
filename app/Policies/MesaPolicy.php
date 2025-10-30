<?php declare(strict_types=1);

namespace App\Policies;

use App\Models\Mesa;
use App\Models\Usuario;

final class MesaPolicy
{
    public function viewAny(?Usuario $user): bool
    {
        return true;
    }

    public function view(?Usuario $user, Mesa $mesa): bool
    {
        return true;
    }

    public function create(Usuario $user): bool
    {
        return $this->isAdminOrModerator($user);
    }

    public function update(Usuario $user, Mesa $mesa): bool
    {
        $uid = (int) $user->id;

        if ($this->isAdminOrModerator($user)) {
            return true;
        }
        return $uid === (int) ($mesa->manager_id ?? 0)
            || $uid === (int) ($mesa->created_by ?? 0);
    }

    public function close(Usuario $user, Mesa $mesa): bool
    {
        return $this->isAdminOrModerator($user);
    }

    public function signup(Usuario $user, Mesa $mesa): bool
    {
        return $mesa->isEffectivelyOpen();
    }

    public function delete(Usuario $user, Mesa $mesa): bool
    {
        return $user->isAdmin();
    }

    private function isAdminOrModerator(Usuario $user): bool
    {
        if ($user->isAdmin()) {
            return true;
        }
        return $user->hasAnyRole(['admin', 'moderator']);
    }
}
