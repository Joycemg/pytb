<?php declare(strict_types=1);

namespace App\Policies;

use App\Models\Usuario;

final class UserAuditPolicy
{
    public function view(Usuario $actor): bool
    {
        return $actor->hasAnyRole(['admin', 'moderator']) || $actor->isAdmin();
    }

    public function export(Usuario $actor): bool
    {
        return $actor->isAdmin();
    }
}
