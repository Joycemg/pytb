{{-- resources/views/usuarios/pendientes.blade.php --}}
@extends('layouts.app')
@section('title', 'Pendientes de aprobación')

@section('content')
    @php
        // Resolver flags solo una vez (evita llamadas repetidas dentro del loop)
        $hasBulk = \Illuminate\Support\Facades\Route::has('admin.usuarios.bulk');
        $hasAprobar = \Illuminate\Support\Facades\Route::has('admin.usuarios.aprobar');

        // Sort actual y el toggle (no calcularlo dos veces)
        $sort = $sort ?? request('sort', 'created_desc');              // 'created_desc' | 'created_asc'
        $toggleSort = $sort === 'created_desc' ? 'created_asc' : 'created_desc';

        // Count de esta página (sin tocar DB de nuevo)
        $count = count($users);
    @endphp

    <h1>Pendientes de aprobación</h1>

    {{-- Filtros --}}
    <form method="GET"
          class="card filters"
          id="filtersForm"
          role="search"
          aria-label="Filtrar usuarios pendientes">
        <div>
            <label class="muted-sm">Buscar</label>
            <input name="q"
                   value="{{ $q ?? request('q') }}"
                   placeholder="Nombre, email o usuario…"
                   autocomplete="off">
        </div>

        <div>
            <label class="muted-sm">Por página</label>
            <select name="per_page"
                    id="per_page">
                @foreach([10, 20, 30, 50, 100] as $pp)
                    <option value="{{ $pp }}"
                            @selected(($per_page ?? (int) request('per_page', 30)) == $pp)>{{ $pp }}</option>
                @endforeach
            </select>
        </div>

        <div>
            <label class="muted-sm">Orden</label>
            <input type="hidden"
                   name="sort"
                   value="{{ $sort }}">
            <a class="pill sort-link"
               href="{{ request()->fullUrlWithQuery(['sort' => $toggleSort]) }}">
                <span class="muted-sm">{{ $sort === 'created_desc' ? 'Nuevo → Viejo' : 'Viejo → Nuevo' }}</span>
            </a>
        </div>

        <div>
            <label class="muted-sm">&nbsp;</label>
            <button class="btn full">Aplicar filtros</button>
        </div>
    </form>

    @if($count === 0)
        <div class="card text-center">
            <div class="emoji-xl">🗂️</div>
            <div style="font-weight:700">No hay usuarios pendientes</div>
            <div class="muted-sm">Probá con otra búsqueda o volvé más tarde.</div>
        </div>
        <div class="muted-sm mt-sm">{{ $users->links() }}</div>
    @else
        {{-- Acciones masivas --}}
        @if($hasBulk)
            <form id="bulkForm"
                  method="POST"
                  action="{{ route('admin.usuarios.bulk') }}"
                  class="tools mt-sm"
                  autocomplete="off">
                @csrf
                <input type="hidden"
                       name="action"
                       value="approve">

                <div class="row"
                     style="display:flex; gap:.6rem; align-items:flex-end; flex-wrap:wrap">
                    <div>
                        <label class="muted-sm">Confirmá tu contraseña</label>
                        <input type="password"
                               name="admin_password"
                               placeholder="Tu contraseña"
                               autocomplete="current-password"
                               required
                               style="min-width:220px">
                    </div>

                    <div>
                        <button id="btnBulkApprove"
                                type="submit"
                                class="btn sm"
                                disabled>
                            Aprobar seleccionados
                        </button>
                    </div>

                    <span class="muted-sm">Marcá uno o más usuarios para aprobarlos de una vez.</span>
                </div>
            </form>
        @endif

        <div class="tbl-wrap mt-sm"
             role="region"
             aria-label="Listado de usuarios pendientes">
            <table class="tbl"
                   role="table"
                   aria-describedby="tbl-help">
                <thead>
                    <tr>
                        @if($hasBulk)
                            <th style="width:36px">
                                <input type="checkbox"
                                       id="chkAll"
                                       aria-label="Seleccionar todos">
                            </th>
                        @endif
                        <th style="min-width:80px">ID</th>
                        <th style="min-width:260px">Usuario</th>
                        <th style="min-width:220px">Email</th>
                        <th style="min-width:160px">Celular</th>
                        <th style="min-width:170px">
                            <a class="sort-link"
                               href="{{ request()->fullUrlWithQuery(['sort' => $toggleSort]) }}">
                                Registro <span class="muted-sm">{{ $sort === 'created_desc' ? '↓' : '↑' }}</span>
                            </a>
                        </th>
                        <th style="width:1%"></th>
                    </tr>
                </thead>

                <tbody>
                    @foreach($users as $u)
                        @php
                            // Iniciales (1 sola vez)
                            $nm = trim((string) ($u->name ?? ''));
                            $inits = collect(explode(' ', $nm))->filter()->map(fn($p) => mb_substr($p, 0, 1))->take(2)->implode('');
                            if ($inits === '')
                                $inits = mb_substr((string) ($u->username ?? 'U'), 0, 2);

                            $hasCel = (bool) $u->celular;

                            // Evitar duplicar trabajo por fila
                            $createdFmt = $u->created_at?->format('Y-m-d H:i');
                            $createdHuman = $u->created_at?->diffForHumans();
                          @endphp
                        <tr>
                            @if($hasBulk)
                                <td data-label="Sel.">
                                    <input class="chk"
                                           type="checkbox"
                                           form="bulkForm"
                                           name="ids[]"
                                           value="{{ $u->id }}"
                                           aria-label="Seleccionar #{{ $u->id }}">
                                </td>
                            @endif

                            <td class="muted-sm"
                                data-label="ID">#{{ $u->id }}</td>

                            <td data-label="Usuario">
                                <div style="display:flex; align-items:center; gap:.6rem">
                                    <span class="avatar-chip"
                                          aria-hidden="true">{{ mb_strtoupper($inits) }}</span>
                                    <div>
                                        <div style="font-weight:700">{{ $u->name ?: '—' }}</div>
                                        <div class="muted-sm">@ {{ $u->username ?: '—' }}</div>
                                    </div>
                                </div>
                            </td>

                            <td data-label="Email">
                                <span class="copyable"
                                      data-copy="{{ $u->email }}"
                                      title="Clic/tocar para copiar">{{ $u->email }}</span>
                            </td>

                            <td data-label="Celular">
                                @if($hasCel)
                                    <span class="copyable"
                                          data-copy="{{ $u->celular }}"
                                          title="Clic/tocar para copiar"
                                          role="button"
                                          tabindex="0">{{ $u->celular }}</span>
                                @else
                                    <span class="badge warn"
                                          title="Campo obligatorio en registro">FALTA</span>
                                @endif
                            </td>

                            <td data-label="Registro">
                                <div>
                                    <div class="muted-sm">{{ $createdFmt }}</div>
                                    <div class="muted-sm">{{ $createdHuman }}</div>
                                </div>
                            </td>

                            <td data-label="Acción">
                                @if($hasAprobar)
                                    <form method="POST"
                                          action="{{ route('admin.usuarios.aprobar', $u) }}"
                                          class="inline"
                                          autocomplete="off">
                                        @csrf
                                        <div style="display:flex; gap:.4rem; align-items:center">
                                            <input type="password"
                                                   name="admin_password"
                                                   placeholder="Contraseña"
                                                   autocomplete="current-password"
                                                   required
                                                   style="width:140px">
                                            <button class="btn sm">Aprobar</button>
                                        </div>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <p id="tbl-help"
           class="visually-hidden">
            Tabla de usuarios con selección múltiple y acciones masivas.
        </p>

        <div class="mt-sm">{{ $users->onEachSide(1)->links() }}</div>
    @endif
@endsection

@push('scripts')
    <script>
        (function () {
            // Autosubmit en per_page
            var pp = document.getElementById('per_page');
            if (pp) pp.addEventListener('change', function () { pp.form.submit(); });

            // Bulk select + botón habilitado
            var chkAll = document.getElementById('chkAll');
            var bulkBtn = document.getElementById('btnBulkApprove');
            var checks = Array.prototype.slice.call(document.querySelectorAll('.chk'));
            function refreshBulk() {
                if (!bulkBtn) return;
                var any = checks.some(function (c) { return c.checked; });
                bulkBtn.disabled = !any;
            }
            if (chkAll) {
                chkAll.addEventListener('change', function () {
                    checks.forEach(function (c) { c.checked = chkAll.checked; });
                    refreshBulk();
                }, { passive: true });
            }
            checks.forEach(function (c) { c.addEventListener('change', refreshBulk, { passive: true }); });
            refreshBulk();

            // Copiar (email + celular)
            function copyText(text) {
                if (!text) return Promise.reject();
                if (navigator.clipboard?.writeText) return navigator.clipboard.writeText(text);
                return new Promise(function (resolve, reject) {
                    try {
                        var tmp = document.createElement('input');
                        tmp.value = text; document.body.appendChild(tmp); tmp.select();
                        var ok = document.execCommand('copy');
                        document.body.removeChild(tmp);
                        ok ? resolve() : reject();
                    } catch (e) { reject(e); }
                });
            }
            function markCopied(el) {
                el.classList.add('copied');
                setTimeout(function () { el.classList.remove('copied'); }, 1400);
            }
            document.querySelectorAll('.copyable').forEach(function (el) {
                var val = el.getAttribute('data-copy') || el.textContent.trim();
                function doCopy() { copyText(val).then(function () { markCopied(el); }); }
                el.addEventListener('click', doCopy);
                el.addEventListener('touchend', function (e) { e.preventDefault(); doCopy(); }, { passive: false });
                el.addEventListener('keydown', function (e) {
                    if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); doCopy(); }
                });
                el.setAttribute('tabindex', '0');
                el.setAttribute('role', 'button');
                el.setAttribute('aria-label', 'Copiar "' + val + '"');
            });

            // Búsqueda: autosubmit tras 500 ms de pausa
            var q = document.querySelector('input[name="q"]');
            var timer = null;
            if (q) {
                q.addEventListener('input', function () {
                    clearTimeout(timer);
                    timer = setTimeout(function () { q.form && q.form.submit(); }, 500);
                });
            }
        })();
    </script>
@endpush