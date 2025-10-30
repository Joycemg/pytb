<?php declare(strict_types=1);

namespace App\Policies;

use App\Models\Jornada;
use App\Models\Usuario;

final class JornadaPolicy
{
    public function open(Usuario $user): bool
    {
        return $this->isAdminOrModerator($user);
    }

    public function close(Usuario $user, Jornada $jornada): bool
    {
        return $this->isAdminOrModerator($user);
    }

    public function view(?Usuario $user, Jornada $jornada): bool
    {
        return true;
    }

    public function history(?Usuario $user): bool
    {
        return true;
    }

    public function moderate(Usuario $user, Jornada $jornada): bool
    {
        return $this->isAdminOrModerator($user);
    }

    private function isAdminOrModerator(Usuario $user): bool
    {
        if ($user->isAdmin()) {
            return true;
        }
        return $user->hasAnyRole(['admin', 'moderator']);
    }
}
