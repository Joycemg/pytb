<?php

namespace App\Http\Middleware;

use Illuminate\Http\Middleware\TrustProxies as Middleware;
use Illuminate\Http\Request;

class TrustProxies extends Middleware
{
    /** Confiar en cualquier proxy (tu Caddy) */
    protected $proxies = '*';

    /** Usa todas las cabeceras estándar */
    protected $headers = Request::HEADER_X_FORWARDED_ALL;
}
