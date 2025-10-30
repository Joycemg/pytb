{{-- resources/views/jornadas/index.blade.php --}}
@extends('layouts.app')
@section('title', 'Jornadas')

@section('content')
    @php
        if (!empty($actual)) {
            $actual->loadMissing(['apartados' => fn($q) => $q->orderBy('orden')->orderBy('id')]);
        }
    @endphp

    <div class="toolbar">
        <h1>Jornadas</h1>

        @if(!$actual)
            @can('open', \App\Models\Jornada::class)
                @if(\Illuminate\Support\Facades\Route::has('jornadas.abrir'))
                    <form method="post"
                          action="{{ route('jornadas.abrir') }}"
                          autocomplete="off">
                        @csrf
                        <button class="btn" data-once>🟢 Abrir jornada</button>
                    </form>
                @endif
            @endcan
        @endif
    </div>

    <section class="card">
        <h3 class="m-0">Jornada actual</h3>

        @if($actual)
            @php
                $tz = config('app.timezone', 'UTC');
                $fechaApertura = optional($actual->abierta_at)->timezone($tz)?->format('d/m/Y') ?? '—';
            @endphp

            <div class="toolbar">
                <div>Estado:
                    <strong>
                        <span class="pill {{ ($actual->estado ?? '') === 'abierta' ? 'ok' : 'warn' }}">
                            {{ strtoupper($actual->estado ?? '—') }}
                        </span>
                    </strong>
                </div>
                <div>Abierta: <strong>{{ $fechaApertura }}</strong></div>
                @if(\Illuminate\Support\Facades\Route::has('jornadas.show'))
                    <a class="btn ghost" href="{{ route('jornadas.show', $actual) }}">Ver detalle</a>
                @endif
            </div>

            @can('open', \App\Models\Jornada::class)
                <hr class="divider">

                <h4>Turnos</h4>

                @if($actual->apartados->count())
                    <div class="tbl-wrap">
                        <table class="tbl" role="table" aria-label="Turnos de la jornada">
                            <thead>
                                <tr>
                                    <th>Título</th>
                                    <th>Orden</th>
                                    <th>Activo</th>
                                    <th style="width:260px"></th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($actual->apartados as $a)
                                    <tr>
                                        <td data-label="Título">{{ e($a->titulo) }}</td>
                                        <td data-label="Orden">{{ (int) $a->orden }}</td>
                                        <td data-label="Activo">{{ $a->activo ? 'Sí' : 'No' }}</td>
                                        <td data-label="Acciones">
                                            {{-- Toggle activo --}}
                                            @if(\Illuminate\Support\Facades\Route::has('jornadas.apartados.update'))
                                                <form method="post"
                                                      action="{{ route('jornadas.apartados.update', [$actual, $a]) }}"
                                                      class="inline">
                                                    @csrf @method('PUT')
                                                    <input type="hidden" name="activo" value="{{ $a->activo ? 0 : 1 }}">
                                                    <button class="btn line" data-once>
                                                        {{ $a->activo ? 'Desactivar' : 'Activar' }}
                                                    </button>
                                                </form>
                                            @endif

                                            {{-- Eliminar turno → CONFIRMA CON MODAL (js-need-pwd) --}}
                                            @if(\Illuminate\Support\Facades\Route::has('jornadas.apartados.destroy'))
                                                <form method="post"
                                                      action="{{ route('jornadas.apartados.destroy', [$actual, $a]) }}"
                                                      class="inline js-need-pwd"
                                                      autocomplete="off">
                                                    @csrf @method('DELETE')
                                                    <button class="btn danger line" data-once>Eliminar</button>
                                                </form>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <div class="muted">Aún no hay turnos.</div>
                @endif

                {{-- Crear turno --}}
                @if(\Illuminate\Support\Facades\Route::has('jornadas.apartados.store'))
                    <h5>Agregar turno</h5>
                    <form method="post"
                          action="{{ route('jornadas.apartados.store', $actual) }}"
                          class="grid grid-form-3"
                          autocomplete="off">
                        @csrf
                        <div>
                            <label for="ap_titulo">Título</label>
                            <input id="ap_titulo"
                                   name="titulo"
                                   required
                                   maxlength="120"
                                   placeholder="Ej.: Turno noche"
                                   value="{{ old('titulo') }}">
                            @error('titulo')<div class="flash">⚠️ {{ $message }}</div>@enderror
                        </div>
                        <div>
                            <label for="ap_orden">Orden</label>
                            <input id="ap_orden"
                                   type="number"
                                   name="orden"
                                   min="0"
                                   max="255"
                                   value="{{ old('orden', 1) }}">
                            @error('orden')<div class="flash">⚠️ {{ $message }}</div>@enderror
                        </div>
                        <div>
                            <label class="muted">Activo</label>
                            <label class="chk">
                                <input type="checkbox" name="activo" value="1" {{ old('activo', '1') ? 'checked' : '' }}>
                                Sí
                            </label>
                        </div>
                        <div class="form-row-span">
                            <button class="btn" data-once>Crear turno</button>
                        </div>
                    </form>
                @endif
            @endcan

            {{-- Cerrar jornada → CONFIRMA CON MODAL (js-need-pwd) --}}
            @can('close', $actual)
                <hr class="divider">
                @if(\Illuminate\Support\Facades\Route::has('jornadas.cerrar'))
                    <form method="post"
                          action="{{ route('jornadas.cerrar', $actual) }}"
                          id="form-close"
                          class="js-need-pwd"
                          autocomplete="off">
                        @csrf
                        <button class="btn line" data-once>🔴 Cerrar jornada</button>
                    </form>
                @endif
            @endcan
        @else
            <div class="muted">No hay jornada abierta.</div>
        @endif
    </section>

    <h3>Historial</h3>
    @if(($hist ?? null) && $hist->count())
        <div class="cards">
            @foreach($hist as $j)
                @php
                    $tz = config('app.timezone', 'UTC');
                    $num = $numeroPorId[$j->id] ?? null;
                    $fecha = optional($j->abierta_at)->timezone($tz)?->format('d/m/Y') ?? '—';
                @endphp
                <article class="card fill">
                    <h4>Jornada {{ $num ?? ('#' . (int) $j->id) }} — {{ $fecha }}</h4>
                    <div class="muted">Estado: {{ strtoupper($j->estado ?? '—') }}</div>
                    <div class="card-footer">
                        @if(\Illuminate\Support\Facades\Route::has('jornadas.show'))
                            <a class="btn" href="{{ route('jornadas.show', $j) }}">Ver detalle</a>
                        @endif
                    </div>
                </article>
            @endforeach
        </div>

        @if(method_exists($hist, 'links'))
            <div class="mt-sm">
                {{ $hist->onEachSide(1)->links('pagination.hostinger') }}
            </div>
        @endif
    @else
        <div class="card">Sin jornadas cerradas.</div>
    @endif
@endsection

{{-- ===== MODAL GLOBAL DE CONFIRMACIÓN (un único input visible) ===== --}}
@push('scripts')
<dialog id="pwd-confirm"
        aria-labelledby="pwd-confirm-title"
        aria-describedby="pwd-confirm-desc">
  <form method="dialog"
        id="pwd-confirm-form"
        class="card"
        role="document">
    <h3 id="pwd-confirm-title">Confirmar acción</h3>
    <p id="pwd-confirm-desc">Ingresá tu contraseña para confirmar.</p>

    {{-- Campo username oculto para evitar warnings de Chrome --}}
    <input type="text"
           name="username"
           autocomplete="username"
           tabindex="-1"
           aria-hidden="true"
           style="position:absolute;left:-9999px;width:1px;height:1px;opacity:0">

    <label class="muted-sm" for="pwd-confirm-input">Contraseña</label>
    <input id="pwd-confirm-input"
           type="password"
           autocomplete="current-password"
           required>

    <div class="modal-actions" style="display:flex;gap:.5rem;justify-content:flex-end;margin-top:.75rem">
      <button value="cancel"
              type="button"
              id="pwd-confirm-cancel"
              class="btn line sm">Cancelar</button>
      <button value="ok"
              id="pwd-confirm-ok"
              class="btn sm">Confirmar</button>
    </div>
  </form>
</dialog>

<script>
/**
 * Intercepta formularios con .js-need-pwd y usa el MODAL para pedir la contraseña.
 * Inyecta SOLO inputs ocultos antes de enviar:
 *   - admin_password
 *   - password
 *   - __admin_pwd_checked
 * No crea ningún input visible extra y evita duplicaciones.
 */
(function () {
  const dlg = document.getElementById('pwd-confirm');
  const card = document.getElementById('pwd-confirm-form');
  const input = document.getElementById('pwd-confirm-input');
  const okBtn = document.getElementById('pwd-confirm-ok');
  const cancelBtn = document.getElementById('pwd-confirm-cancel');
  let pendingForm = null;

  function openFor(formEl) {
    if (!formEl || formEl.dataset.pwdOk === '1') return;
    pendingForm = formEl;
    if (dlg && typeof dlg.showModal === 'function') {
      input.value = '';
      dlg.showModal();
      setTimeout(() => { try { input.focus(); } catch(_) {} }, 10);
    }
  }

  function addHiddenOnce(formEl, name, value) {
    let el = formEl.querySelector('input[name="'+name+'"]');
    if (!el) {
      el = document.createElement('input');
      el.type = 'hidden';
      el.name = name;
      formEl.appendChild(el);
    }
    el.value = value;
  }

  function attachAndSubmit(formEl, pwd) {
    if (!formEl || formEl.dataset.pwdOk === '1') return;
    addHiddenOnce(formEl, 'admin_password', pwd);
    addHiddenOnce(formEl, 'password', pwd);
    addHiddenOnce(formEl, '__admin_pwd_checked', pwd);
    formEl.dataset.pwdOk = '1';
    formEl.submit();
  }

  // Evita submit implícito del <form method="dialog">
  card?.addEventListener('submit', (e) => e.preventDefault());

  okBtn?.addEventListener('click', function (e) {
    const val = (input.value || '').trim();
    if (!val) { e.preventDefault(); input.focus(); return; }
    dlg?.close('ok');
    attachAndSubmit(pendingForm, val);
    pendingForm = null;
    input.value = '';
  });

  cancelBtn?.addEventListener('click', function (e) {
    e.preventDefault();
    dlg?.close('cancel');
    pendingForm = null;
    input.value = '';
  });

  // Cerrar al clickear fuera del card
  dlg?.addEventListener('click', (e) => {
    const r = card.getBoundingClientRect();
    const inside = e.clientX >= r.left && e.clientX <= r.right &&
                   e.clientY >= r.top && e.clientY <= r.bottom;
    if (!inside) {
      dlg.close('cancel');
      pendingForm = null;
      input.value = '';
    }
  });

  // Intercepta SOLO forms con .js-need-pwd
  document.addEventListener('submit', function (ev) {
    const f = ev.target;
    if (!(f instanceof HTMLFormElement)) return;
    if (!f.classList?.contains('js-need-pwd')) return;
    if (f.dataset.pwdOk === '1') return; // ya listo
    ev.preventDefault();
    openFor(f);
  }, true);
})();
</script>
@endpush
