<?php declare(strict_types=1);

namespace App\Policies;

use App\Models\Inscripcion;
use App\Models\Usuario;

final class InscripcionPolicy
{
    public function mark(Usuario $user, Inscripcion $inscripcion): bool
    {
        if ($user->isAdmin()) {
            return true;
        }
        return $this->canManageMesa($user, $inscripcion);
    }

    public function remove(Usuario $user, Inscripcion $inscripcion): bool
    {
        $uid = (int) $user->id;

        if ($uid === (int) $inscripcion->user_id) {
            return true;
        }
        if ($user->isAdmin()) {
            return true;
        }
        return $this->canManageMesa($user, $inscripcion);
    }

    private function canManageMesa(Usuario $user, Inscripcion $inscripcion): bool
    {
        $uid = (int) $user->id;

        // Si ya está cargada la relación, comparar directo (0 queries)
        if ($mesa = $inscripcion->getRelationValue('mesa')) {
            return $uid === (int) ($mesa->created_by ?? 0)
                || $uid === (int) ($mesa->manager_id ?? 0);
        }

        // Consulta mínima (1 exists) sin hidratar modelo
        return \App\Models\Mesa::query()
            ->whereKey($inscripcion->mesa_id)
            ->where(function ($q) use ($uid) {
                $q->where('created_by', $uid)->orWhere('manager_id', $uid);
            })
            ->toBase()
            ->exists();
    }
}
