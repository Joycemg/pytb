<?php declare(strict_types=1);

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;
use App\Models\Usuario;

class MesasAperturaBatchMail extends Mailable
{
    use Queueable, SerializesModels;

    /** @param Collection<int, array{id:int,title:string,url:string,opens_at:\Carbon\CarbonInterface}> $mesas */
    public function __construct(
        public Usuario $user,
        public Collection $mesas
    ) {
    }

    public function build()
    {
        $count = $this->mesas->count();
        $first = (string) ($this->mesas->first()['title'] ?? 'mesa');

        $subject = $count === 1
            ? "Se abrió: {$first}"
            : "Se abren {$count} mesas";

        return $this->subject($subject)
            ->markdown('emails.mesas-apertura-batch', [
                'user' => $this->user,
                'mesas' => $this->mesas,
            ]);
    }
}
