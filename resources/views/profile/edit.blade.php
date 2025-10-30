{{-- resources/views/profile/edit.blade.php --}}
@extends('layouts.app')
@section('title', 'Mi perfil · ' . config('app.name', 'La Taberna'))

@section('content')
  <h1 style="margin:0 0 .6rem">Perfil</h1>

  @if(session('ok'))
    <div class="flash"
         role="status">✅ {{ session('ok') }}</div>
  @endif
  @if(session('error'))
    <div class="flash"
         role="alert">⚠️ {{ session('error') }}</div>
  @endif
  @if($errors->any())
    <div class="flash"
         role="alert">
      <strong>Revisá:</strong>
      <ul style="margin:.3rem 0 0 1rem">
        @foreach($errors->all() as $e)
          <li>{{ $e }}</li>
        @endforeach
      </ul>
    </div>
  @endif

  <form class="card grid"
        method="post"
        action="{{ route('profile.update') }}"
        enctype="multipart/form-data"
        style="gap:.8rem">
    @csrf

    {{-- Nombre --}}
    <div>
      <label for="name"
             class="muted-sm">Nombre</label>
      <input id="name"
             name="name"
             value="{{ old('name', $user->name) }}"
             required
             maxlength="100"
             autocomplete="name"
             autofocus>
      @error('name')<div class="muted-sm">⚠️ {{ $message }}</div>@enderror
    </div>

    {{-- Usuario (opcional) --}}
    <div>
      <label for="username"
             class="muted-sm">Usuario (opcional)</label>
      <input id="username"
             name="username"
             value="{{ old('username', $user->username) }}"
             maxlength="20"
             autocomplete="username"
             pattern="[A-Za-z0-9_.\-]{3,20}"
             title="3–20, letras/números/._-">
      @error('username')<div class="muted-sm">⚠️ {{ $message }}</div>@enderror
    </div>

    {{-- Biografía --}}
    <div>
      <label for="bio"
             class="muted-sm">Biografía</label>
      <textarea id="bio"
                name="bio"
                maxlength="1000"
                rows="4"
                placeholder="Contanos algo sobre vos…">{{ old('bio', $user->bio) }}</textarea>
      @error('bio')<div class="muted-sm">⚠️ {{ $message }}</div>@enderror
    </div>

    {{-- Avatar actual + carga por archivo/URL --}}
    <div class="grid"
         style="gap:.8rem; grid-template-columns: 96px 1fr;">
      <div>
        {{-- Preview cuadrado (si hay) --}}
        @php $avatar = (string) ($user->avatar_url ?? ''); @endphp
        @if($avatar)
          <img class="square"
               src="{{ $avatar }}"
               alt="Avatar actual"
               width="96"
               height="96"
               loading="lazy">
        @else
          <div class="square"
               style="display:flex;align-items:center;justify-content:center;font-weight:800">
            {{ mb_strtoupper(mb_substr((string) $user->name, 0, 1)) ?: 'U' }}
          </div>
        @endif
      </div>

      <div class="grid"
           style="gap:.6rem">
        <div>
          <label for="avatar"
                 class="muted-sm">Avatar (archivo)</label>
          <input id="avatar"
                 type="file"
                 name="avatar"
                 accept="image/jpeg,image/png,image/webp">
          @error('avatar')<div class="muted-sm">⚠️ {{ $message }}</div>@enderror
        </div>

        <div>
          <label for="avatar_url"
                 class="muted-sm">Avatar URL (descarga remota segura)</label>
          <input id="avatar_url"
                 name="avatar_url"
                 inputmode="url"
                 autocomplete="url"
                 placeholder="https://…"
                 value="{{ old('avatar_url', $user->avatar_url) }}">
          @error('avatar_url')<div class="muted-sm">⚠️ {{ $message }}</div>@enderror
        </div>

        @if(\Illuminate\Support\Facades\Route::has('profile.avatar.delete') && $avatar)
          <form method="post"
                action="{{ route('profile.avatar.delete') }}"
                onsubmit="return confirm('¿Quitar avatar actual?')"
                style="margin:0">
            @csrf
            <button class="btn sm line"
                    data-once>Quitar avatar</button>
          </form>
        @endif
      </div>
    </div>

    <div style="display:flex; gap:.6rem; flex-wrap:wrap; margin-top:.2rem">
      <button class="btn"
              data-once>Guardar</button>

      @if(\Illuminate\Support\Facades\Route::has('password.change'))
        <a class="btn line"
           href="{{ route('password.change') }}">Cambiar contraseña</a>
      @endif
    </div>
  </form>
@endsection