<?php declare(strict_types=1);

namespace App\Events;

use App\Models\Usuario;
use Illuminate\Foundation\Events\Dispatchable;

final class UsuarioAprobado
{
    use Dispatchable;

    public function __construct(public Usuario $user)
    {
    }
}
