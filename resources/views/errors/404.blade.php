{{-- resources/views/errors/404.blade.php --}}
<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) ?: 'es' }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport"
          content="width=device-width,initial-scale=1">
    <title>Página no encontrada</title>
    {{-- Carga tu CSS modular (sin Vite, apto Hostinger) --}}
    <link rel="stylesheet"
          href="{{ asset('css/app.css') }}">
</head>

<body>
    @php
        $homeUrl = \Illuminate\Support\Facades\Route::has('home') ? route('home') : url('/');
      @endphp

    <main class="container-680 text-center">
        <h1 class="h1-clamp">404 — Página no encontrada</h1>
        <p class="muted">Lo que buscás no existe o cambió de lugar.</p>

        <div class="toolbar"
             style="justify-content:center">
            <a class="btn"
               href="{{ $homeUrl }}">Volver al inicio</a>

        </div>
    </main>
</body>

</html>