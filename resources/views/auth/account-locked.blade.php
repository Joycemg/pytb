{{-- resources/views/auth/blocked.blade.php --}}
@extends('layouts.app')
@section('title', 'Cuenta bloqueada')

@section('content')
    <div class="container">
        <h1>Cuenta bloqueada</h1>

        <div class="flash error"
             role="alert"
             aria-live="polite">
            Tu cuenta fue bloqueada. Si creés que es un error, contactá a un administrador.
        </div>

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