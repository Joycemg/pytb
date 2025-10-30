{{-- resources/views/auth/login.blade.php --}}
@extends('layouts.app')
@section('title', 'Entrar')

@section('content')
  <div class="container">
    <h1 class="mb-md">Iniciar sesión</h1>

    @if(session('status'))
      <div class="flash" role="status">{{ session('status') }}</div>
    @endif

    @if(session('error'))
      <div class="flash error" role="alert">⚠️ {{ session('error') }}</div>
    @endif

    <form class="card grid"
          method="post"
          action="{{ route('auth.login.post') }}"
          autocomplete="on"
          novalidate>
      @csrf

      <div>
        <label for="email">Email</label>
        <input id="email"
               type="email"
               name="email"
               value="{{ old('email') }}"
               required
               autocomplete="username"
               autocapitalize="none"
               spellcheck="false"
               inputmode="email"
               enterkeyhint="next"
               autofocus>
        @error('email')
          <div class="flash mt-xs" role="alert">⚠️ {{ $message }}</div>
        @enderror
      </div>

      <div>
        <label for="password">Contraseña</label>
        <input id="password"
               type="password"
               name="password"
               required
               autocomplete="current-password"
               placeholder="••••••••"
               enterkeyhint="go">
        @error('password')
          <div class="flash mt-xs" role="alert">⚠️ {{ $message }}</div>
        @enderror
      </div>

      <label class="chk">
        <input type="hidden" name="remember" value="0">
        <input type="checkbox"
               name="remember"
               value="1"
               {{ old('remember') ? 'checked' : '' }}>
        Recordarme
      </label>

      {{-- Si usás un "intended" opcional, podés preservarlo así:
      @if(request()->has('intended'))
        <input type="hidden" name="intended" value="{{ request('intended') }}">
      @endif
      --}}

      <button class="btn" type="submit" data-once data-delay="200">
        Entrar
      </button>

      <p class="muted mt-xs">
        ¿No tenés cuenta?
        @if(\Illuminate\Support\Facades\Route::has('auth.register'))
          <a href="{{ route('auth.register') }}">Crear cuenta</a>
        @else
          <a href="{{ url('/register') }}">Crear cuenta</a>
        @endif
        @if(\Illuminate\Support\Facades\Route::has('password.request'))
          · <a href="{{ route('password.request') }}">Olvidé mi contraseña</a>
        @endif
      </p>
    </form>
  </div>
@endsection
