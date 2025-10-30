<?php declare(strict_types=1);

namespace App\Policies;

use App\Models\Usuario;

final class UsuarioPolicy
{
    public function viewAdmin(Usuario $actor): bool
    {
        // Admin y moderador pueden entrar a la sección admin
        return $actor->hasAnyRole(['admin', 'moderator']) || $actor->hasRole('admin');
    }

    public function approve(Usuario $actor): bool
    {
        return $actor->hasAnyRole(['admin', 'moderator']) || $actor->hasRole('admin');
    }

    /**
     * Editar datos básicos:
     * - Admin: puede todo.
     * - Moderador: solo si el target tiene menor jerarquía (no pares ni admins).
     *   El controller limita campos a email/username para moderador.
     */
    public function updateBasic(Usuario $actor, Usuario $target): bool
    {
        if ($actor->hasRole('admin')) {
            return true;
        }
        if ($actor->hasRole('moderator')) {
            return $target->jerarquia() < $actor->jerarquia();
        }
        return false;
    }

    /** Solo admin puede cambiar roles. */
    public function changeRole(Usuario $actor): bool
    {
        return $actor->hasRole('admin');
    }

    /** Solo admin puede resetear contraseñas. */
    public function resetPassword(Usuario $actor): bool
    {
        return $actor->hasRole('admin');
    }

    /**
     * Bloquear/Desbloquear:
     * - Admin: siempre.
     * - Moderador: solo si el target tiene menor jerarquía (nunca admins ni moderadores).
     */
    public function lock(Usuario $actor, Usuario $target): bool
    {
        if ($actor->hasRole('admin')) {
            return true;
        }
        if ($actor->hasRole('moderator')) {
            return $target->jerarquia() < $actor->jerarquia();
        }
        return false;
    }

    public function unlock(Usuario $actor, Usuario $target): bool
    {
        if ($actor->hasRole('admin')) {
            return true;
        }
        if ($actor->hasRole('moderator')) {
            return $target->jerarquia() < $actor->jerarquia();
        }
        return false;
    }

    /**
     * Acciones masivas:
     * - Admin: todas.
     * - Moderador: solo approve / lock / unlock.
     */
    public function bulkAction(Usuario $actor, string $action): bool
    {
        if ($actor->hasRole('admin')) {
            return true;
        }
        if ($actor->hasRole('moderator')) {
            return in_array($action, ['approve', 'lock', 'unlock'], true);
        }
        return false;
    }
}
