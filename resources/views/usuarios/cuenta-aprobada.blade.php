{{-- resources/views/usuarios/cuenta-aprobada.blade.php --}}
@extends('layouts.app')
@section('title', 'Cuenta aprobada · ' . config('app.name', 'La Taberna'))

@push('head')
    <style>
        .ok-card {
            background: #fff;
            border: 1px solid var(--line, #e5e7eb);
            border-radius: 12px;
            padding: clamp(1rem, 2.5vw, 1.4rem);
            max-width: 720px;
            margin: auto;
        }

        .ok-head {
            display: flex;
            align-items: center;
            gap: .6rem;
            margin: 0 0 .5rem
        }

        .ok-cta {
            display: flex;
            gap: .6rem;
            flex-wrap: wrap;
            margin-top: .9rem
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: .55rem .9rem;
            border: 1px solid var(--line, #e5e7eb);
            background: #fff;
            border-radius: 8px;
            text-decoration: none;
        }

        .btn.gold {
            background: var(--accent, #C9A66B);
            color: #111;
            border-color: transparent
        }

        .muted {
            color: var(--muted, #6b7280)
        }
    </style>
@endpush

@section('content')
    <div class="ok-card">
        <h1 class="ok-head">✅ Cuenta aprobada</h1>
        <p>¡Bienvenido/a {{ auth()->user()->name ?? '' }}! Ya podés inscribirte a mesas, gestionar tus asistencias y empezar
            a sumar <strong>Puntos de Honor</strong>.</p>

        <div class="ok-cta">
            <a class="btn gold"
               href="{{ route('mesas.index') }}">Ver mesas</a>
            <a class="btn"
               href="{{ route('perfil.show') }}">Mi perfil</a>
        </div>

        <p class="muted"
           style="margin-top:1rem">
            Consejo: activá las <strong>notificaciones</strong> para enterarte cuando se libera un cupo.
        </p>
    </div>
    @endcomponent

    @slot('subcopy')
    Recibís este correo porque tu cuenta fue aprobada en **La Taberna**.
    @endslot
    — El equipo de La Taberna
@endsection