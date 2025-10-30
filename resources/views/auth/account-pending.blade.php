{{-- resources/views/auth/pending.blade.php --}}
@extends('layouts.app')
@section('title', 'Cuenta pendiente')

@section('content')
    <div class="container">
        <h1>Cuenta pendiente</h1>

        <div class="flash"
             role="status"
             aria-live="polite">
            Tu cuenta aún no fue aprobada por un moderador. Te avisaremos cuando esté lista.
        </div>

        <p class="muted mt-sm">
            Si ya pasó un tiempo razonable, escribinos y revisamos tu caso.
        </p>

        <div class="tools mt-sm">
            @if(\Illuminate\Support\Facades\Route::has('home'))
                <a class="btn line"
                   href="{{ route('home') }}">Volver al inicio</a>
            @endif

            @if(\Illuminate\Support\Facades\Route::has('auth.logout'))
                <form method="POST"
                      action="{{ route('auth.logout') }}">
                    @csrf
                    <button class="btn danger"
                            data-once>Cerrar sesión</button>
                </form>
            @endif
        </div>
    </div>
@endsection