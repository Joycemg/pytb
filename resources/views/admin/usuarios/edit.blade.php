@extends('layouts.app')
@section('title', 'Editar usuario #' . $u->id)

@section('content')
    <div class="container">
        <h1 style="margin:0 0 .6rem">Editar usuario #{{ $u->id }}</h1>

        {{-- Acciones rápidas --}}
        <section class="card">
            <div class="tools">
                @can('approve', \App\Models\Usuario::class)
                    @if(!$u->approved_at)
                        <form method="POST"
                              action="{{ route('admin.usuarios.aprobar', $u) }}"
                              class="js-need-pwd">
                            @csrf
                            <button class="btn sm"
                                    data-once>✓ Aprobar</button>
                        </form>
                    @endif
                @endcan

                @can('lock', $u)
                    @if(!$u->locked_at)
                        <form method="POST"
                              action="{{ route('admin.usuarios.lock', $u) }}"
                              class="js-need-pwd">
                            @csrf
                            <button class="btn line sm danger"
                                    onclick="return confirm('¿Bloquear a {{ $u->name }}?')">Bloquear</button>
                        </form>
                    @endif
                @endcan

                @can('unlock', $u)
                    @if($u->locked_at)
                        <form method="POST"
                              action="{{ route('admin.usuarios.unlock', $u) }}"
                              class="js-need-pwd">
                            @csrf
                            <button class="btn sm">Desbloquear</button>
                        </form>
                    @endif
                @endcan
            </div>

            @can('changeRole', \App\Models\Usuario::class)
                <div class="mt-sm">
                    <form method="POST"
                          action="{{ route('admin.usuarios.role', $u) }}"
                          class="tools js-need-pwd">
                        @csrf
                        <label class="muted-sm"
                               for="role">Rol</label>
                        <select id="role"
                                name="role"
                                style="max-width:220px">
                            @foreach(['user', 'moderator', 'admin'] as $r)
                                <option value="{{ $r }}"
                                        @selected(($u->role ?? 'user') === $r)>{{ $r }}</option>
                            @endforeach
                        </select>
                        <button class="btn sm"
                                data-once>Cambiar</button>
                    </form>
                </div>
            @endcan

            @can('resetPassword', \App\Models\Usuario::class)
                <div class="mt-sm">
                    <form method="POST"
                          action="{{ route('admin.usuarios.password', $u) }}"
                          class="grid js-need-pwd"
                          style="grid-template-columns:1fr 1fr auto;gap:.5rem;align-items:end">
                        @csrf
                        <div>
                            <label class="muted-sm"
                                   for="pwd">Nueva contraseña</label>
                            <input id="pwd"
                                   type="password"
                                   name="password"
                                   autocomplete="new-password"
                                   placeholder="••••••••"
                                   required>
                        </div>
                        <div>
                            <label class="muted-sm"
                                   for="pwd2">Repetir</label>
                            <input id="pwd2"
                                   type="password"
                                   name="password_confirmation"
                                   autocomplete="new-password"
                                   required>
                        </div>
                        <div>
                            <label class="muted-sm">&nbsp;</label>
                            <button class="btn sm"
                                    data-once>Resetear password</button>
                        </div>
                    </form>
                </div>
            @endcan
        </section>

        <div style="margin:1rem 0;border-top:1px dashed var(--line)"></div>

        {{-- Formularios por campo --}}
        @if(auth()->user()->hasRole('admin'))
            <form class="card js-need-pwd"
                  method="POST"
                  action="{{ route('admin.usuarios.update', $u) }}">
                @csrf @method('PUT')
                <input type="hidden"
                       name="field"
                       value="name">
                <label class="muted-sm"
                       for="name">Nombre</label>
                <div class="tools">
                    <input id="name"
                           name="name"
                           value="{{ old('name', $u->name) }}"
                           style="flex:1">
                    <button class="btn sm"
                            data-once>Guardar</button>
                </div>
            </form>
        @endif

        <form class="card mt-sm js-need-pwd"
              method="POST"
              action="{{ route('admin.usuarios.update', $u) }}">
            @csrf @method('PUT')
            <input type="hidden"
                   name="field"
                   value="email">
            <label class="muted-sm"
                   for="email">Email</label>
            <div class="tools">
                <input id="email"
                       type="email"
                       name="email"
                       value="{{ old('email', $u->email) }}"
                       required
                       style="flex:1">
                <button class="btn sm"
                        data-once>Guardar</button>
            </div>
        </form>

        <form class="card mt-sm js-need-pwd"
              method="POST"
              action="{{ route('admin.usuarios.update', $u) }}">
            @csrf @method('PUT')
            <input type="hidden"
                   name="field"
                   value="username">
            <label class="muted-sm"
                   for="username">Username</label>
            <div class="tools">
                <input id="username"
                       name="username"
                       value="{{ old('username', $u->username) ?? '' }}"
                       style="flex:1">
                <button class="btn sm"
                        data-once>Guardar</button>
            </div>
            <div class="muted-sm mt-sm">Permitidos: letras, números, punto, guion y guion bajo.</div>
        </form>

        @if(auth()->user()->hasRole('admin'))
            <form class="card mt-sm js-need-pwd"
                  method="POST"
                  action="{{ route('admin.usuarios.update', $u) }}">
                @csrf @method('PUT')
                <input type="hidden"
                       name="field"
                       value="celular">
                <label class="muted-sm"
                       for="celular">Celular</label>
                <div class="tools">
                    <input id="celular"
                           name="celular"
                           value="{{ old('celular', $u->celular) }}"
                           placeholder="+54 9 11 1234-5678"
                           required
                           style="flex:1">
                    <button class="btn sm"
                            data-once>Guardar</button>
                </div>
                <div class="muted-sm mt-sm">Permitidos: dígitos, +, espacios, paréntesis y guiones.</div>
            </form>

            <form class="card mt-sm js-need-pwd"
                  method="POST"
                  action="{{ route('admin.usuarios.update', $u) }}">
                @csrf @method('PUT')
                <input type="hidden"
                       name="field"
                       value="honor">
                <label class="muted-sm d-block">Honor</label>
                <div class="tools"
                     style="flex-wrap:wrap">
                    <button type="button"
                            class="btn line sm"
                            id="honorMinus10">−10</button>
                    <input type="number"
                           id="honorInput"
                           name="honor"
                           value="{{ old('honor', (int) $u->honor) }}"
                           step="10"
                           min="-100000"
                           max="100000"
                           style="max-width:160px">
                    <button type="button"
                            class="btn line sm"
                            id="honorPlus10">+10</button>
                    <span class="muted-sm">Actual: <strong>{{ (int) $u->honor }}</strong></span>
                    <span class="grow"></span>
                    <button class="btn sm"
                            data-once>Guardar</button>
                </div>
                <div class="muted-sm mt-sm">Se ajusta a múltiplos de 10 al guardar (rango −100.000 a 100.000).</div>
            </form>
        @endif
    </div>
@endsection

@push('scripts')
    <script>
        (function () {
            var input = document.getElementById('honorInput');
            if (input) {
                function clamp10(v) { v = Math.round((+v || 0) / 10) * 10; if (v > 100000) v = 100000; if (v < -100000) v = -100000; return v; }
                var minus = document.getElementById('honorMinus10'), plus = document.getElementById('honorPlus10');
                minus && minus.addEventListener('click', () => input.value = clamp10((+input.value || 0) - 10));
                plus && plus.addEventListener('click', () => input.value = clamp10((+input.value || 0) + 10));
                input.addEventListener('change', () => input.value = clamp10(input.value));
            }
        })();
    </script>
@endpush