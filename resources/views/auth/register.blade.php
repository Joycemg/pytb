{{-- resources/views/auth/register.blade.php --}}
@extends('layouts.app')
@section('title', 'Crear cuenta')

@section('content')
  <div class="container">
    <h1 class="mb-md">Crear cuenta</h1>

    {{-- Aviso fijo: aprobación manual por admin/mod --}}
    <div class="flash mb-md"
         role="status"
         aria-live="polite">
      🕒 Tu cuenta quedará <strong>pendiente de aprobación</strong>. Un <strong>administrador</strong> o
      <strong>moderador</strong> debe aprobarla antes de que puedas ingresar. Te vamos a avisar por email cuando se
      apruebe.
    </div>

    @if(session('status'))
      <div class="flash"
           role="status"
           aria-live="polite">{{ session('status') }}</div>
    @endif

    @if(session('error'))
      <div class="flash error"
           role="alert"
           aria-live="assertive">⚠️ {{ session('error') }}</div>
    @endif

    <form class="card grid"
          method="post"
          action="{{ route('auth.register.post') }}"
          autocomplete="on"
          novalidate
          aria-describedby="reg-help">
      @csrf

      <p id="reg-help"
         class="muted mb-md">
        Completá tus datos. Los campos marcados como obligatorios son necesarios para crear la cuenta.
      </p>

      <div>
        <label for="name">Nombre <span class="muted"
                aria-hidden="true">· obligatorio</span></label>
        <input id="name"
               name="name"
               value="{{ old('name', '') }}"
               required
               maxlength="100"
               autocomplete="name"
               enterkeyhint="next"
               autofocus>
        @error('name')
          <div class="flash mt-xs"
               role="alert">⚠️ {{ $message }}</div>
        @enderror
      </div>

      <div>
        <label for="email">Email <span class="muted"
                aria-hidden="true">· obligatorio</span></label>
        <input id="email"
               type="email"
               name="email"
               value="{{ old('email', '') }}"
               required
               maxlength="150"
               autocomplete="email"
               inputmode="email"
               autocapitalize="none"
               spellcheck="false">
        <p class="muted mt-xs">
          Usaremos este email para avisarte cuando tu cuenta sea aprobada.
        </p>
        @error('email')
          <div class="flash mt-xs"
               role="alert">⚠️ {{ $message }}</div>
        @enderror
      </div>

      <div>
        <label for="celular">Celular <span class="muted"
                aria-hidden="true">· obligatorio</span></label>
        <input id="celular"
               type="tel"
               name="celular"
               value="{{ old('celular', '') }}"
               required
               maxlength="30"
               autocomplete="tel"
               inputmode="tel"
               pattern="[0-9+()\-\s]{6,}"
               placeholder="+54 11 1234-5678"
               title="Usá dígitos, +, espacios, guiones o paréntesis (mínimo 6 caracteres)">
        <p class="muted mt-xs">
          Podés usar espacios o guiones. Ej: +54 11 1234-5678
        </p>
        @error('celular')
          <div class="flash mt-xs"
               role="alert">⚠️ {{ $message }}</div>
        @enderror
      </div>

      <div>
        <label for="username">Usuario (opcional)</label>
        <input id="username"
               name="username"
               value="{{ old('username', '') }}"
               maxlength="20"
               autocomplete="username"
               autocapitalize="none"
               spellcheck="false"
               pattern="[A-Za-z0-9_\.]{3,20}"
               title="3–20 caracteres: letras, números, guión bajo o punto">
        <p class="muted mt-xs">
          3–20 caracteres (letras, números, _ o .)
        </p>
        @error('username')
          <div class="flash mt-xs"
               role="alert">⚠️ {{ $message }}</div>
        @enderror
      </div>

      <div class="grid grid-2-eq">
        <div>
          <label for="password">Contraseña <span class="muted"
                  aria-hidden="true">· obligatorio</span></label>
          <input id="password"
                 type="password"
                 name="password"
                 required
                 minlength="8"
                 autocomplete="new-password"
                 placeholder="••••••••"
                 enterkeyhint="next">
          <p class="muted mt-xs">
            Recomendado: al menos 8 caracteres.
          </p>
          @error('password')
            <div class="flash mt-xs"
                 role="alert">⚠️ {{ $message }}</div>
          @enderror
        </div>

        <div>
          <label for="password_confirmation">Repetir contraseña <span class="muted"
                  aria-hidden="true">· obligatorio</span></label>
          <input id="password_confirmation"
                 type="password"
                 name="password_confirmation"
                 required
                 autocomplete="new-password"
                 placeholder="••••••••"
                 enterkeyhint="done">
        </div>
      </div>

      <button class="btn"
              type="submit"
              data-once
              data-delay="300"
              aria-label="Registrarme. La cuenta quedará pendiente de aprobación por un admin o moderador.">
        Registrarme
      </button>

      <p class="muted mt-md">
        ¿Ya tenés cuenta?
        @if(\Illuminate\Support\Facades\Route::has('auth.login'))
          <a href="{{ route('auth.login') }}">Entrar</a>
        @else
          <a href="{{ url('/login') }}">Entrar</a>
        @endif
      </p>

      <hr class="divider">

      <p class="muted mt-sm">
        <strong>Importante:</strong> hasta que un administrador o moderador apruebe tu cuenta,
        verás tu estado como <em>pendiente</em> y no vas a poder participar en las mesas.
      </p>
    </form>
  </div>
@endsection