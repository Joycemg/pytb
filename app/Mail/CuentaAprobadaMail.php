<?php declare(strict_types=1);

namespace App\Mail;

use App\Models\Usuario;
use Illuminate\Mail\Mailable;

final class CuentaAprobadaMail extends Mailable
{
    public function __construct(public Usuario $user)
    {
    }

    public function build(): self
    {
        return $this->subject('Cuenta aprobada en La Taberna')
            ->markdown('emails.cuenta-aprobada', [
                'user' => $this->user,
                'ctaUrl' => route('mesas.index'),
            ])
            ->text('emails.cuenta-aprobada-text', [
                'user' => $this->user,
                'ctaUrl' => route('mesas.index'),
            ]);
    }
}
