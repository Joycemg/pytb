{{-- resources/views/usuarios/index.blade.php --}}
@extends('layouts.app')
@section('title', 'Usuarios')

@section('content')
      <h1 style="margin:0 0 .6rem">Usuarios</h1>

      {{-- Filtros --}}
      <form method="GET"
            class="card filters"
            id="filtersForm"
            role="search"
            aria-label="Filtrar usuarios">
        <div>
          <label class="muted-sm">Buscar</label>
          <input name="q"
                 value="{{ request('q') }}"
                 placeholder="Nombre, email o usuario…">
        </div>

        <div>
          <label class="muted-sm">Rol</label>
          @php $role = request('role', ''); @endphp
          <select name="role">
            <option value="" @selected($role === '')>Todos</option>
            @foreach(['user' => 'user', 'moderator' => 'moderator', 'admin' => 'admin'] as $val => $label)
                  <option value="{{ $val }}" @selected($role === $val)>{{ $label }}</option>
            @endforeach
          </select>
        </div>

        <div>
          <label class="muted-sm">Estado</label>
          @php $state = request('state', ''); @endphp
          <select name="state">
            <option value="" @selected($state === '')>Todos</option>
            <option value="approved" @selected($state === 'approved')>Aprobado</option>
            <option value="pending"  @selected($state === 'pending')>Pendiente</option>
            <option value="locked"   @selected($state === 'locked')>Bloqueado</option>
          </select>
        </div>

        <div>
          <label class="muted-sm">Por página</label>
          <select name="per_page" id="per_page">
            @foreach([10, 20, 30, 50, 100] as $pp)
                  <option value="{{ $pp }}" @selected((int) request('per_page', 30) === $pp)>{{ $pp }}</option>
            @endforeach
          </select>
        </div>

        <div>
          <label class="muted-sm">Orden</label>
          @php
            $sort = request('sort', 'created_desc'); // created_desc | created_asc
            $toggleSort = $sort === 'created_desc' ? 'created_asc' : 'created_desc';
          @endphp
          <input type="hidden" name="sort" value="{{ $sort }}">
          <a class="pill sort-link" href="{{ request()->fullUrlWithQuery(['sort' => $toggleSort]) }}">
            <span class="muted-sm">{{ $sort === 'created_desc' ? 'Nuevo → Viejo' : 'Viejo → Nuevo' }}</span>
          </a>
        </div>

        <div>
          <label class="muted-sm">&nbsp;</label>
          <button class="btn full">Aplicar filtros</button>
        </div>
      </form>

      {{-- Acciones masivas (opcionales, si existe la ruta) --}}
      @if(\Illuminate\Support\Facades\Route::has('admin.usuarios.bulk'))
        <form id="bulkForm"
              method="POST"
              action="{{ route('admin.usuarios.bulk') }}"
              class="tools mt-sm">
          @csrf
          <input type="hidden" name="action" id="bulkAction" value="">
          <button type="submit" class="btn sm" id="btnBulkApprove" data-action="approve" disabled
                  data-confirm="¿Aprobar todos los seleccionados?">Aprobar</button>
          <button type="submit" class="btn sm" id="btnBulkLock" data-action="lock" disabled
                  data-confirm="¿Bloquear todos los seleccionados?">Bloquear</button>
          <button type="submit" class="btn sm" id="btnBulkUnlock" data-action="unlock" disabled
                  data-confirm="¿Desbloquear todos los seleccionados?">Desbloquear</button>
          <span class="muted-sm">Marcá uno o más usuarios para operar en lote.</span>
        </form>
      @endif

      @php $count = $users->count(); @endphp

      @if($count === 0)
        <div class="card text-center">
          <div class="emoji-xl">🗂️</div>
          <div style="font-weight:700">No hay usuarios</div>
          <div class="muted-sm">Probá con otros filtros o volvé más tarde.</div>
        </div>
        <div class="muted-sm mt-sm">{{ $users->links() }}</div>
      @else
        <div class="tbl-wrap mt-sm">
          <table class="tbl">
            <thead>
              <tr>
                @if(\Illuminate\Support\Facades\Route::has('admin.usuarios.bulk'))
                      <th style="width:36px">
                        <input type="checkbox" id="chkAll" aria-label="Seleccionar todos">
                      </th>
                @endif
                <th style="min-width:80px">ID</th>
                <th style="min-width:260px">Usuario</th>
                <th style="min-width:220px">Email</th>
                <th style="min-width:160px">Celular</th>
                <th style="min-width:130px">Rol</th>
                <th style="min-width:170px">
                  <a class="sort-link" href="{{ request()->fullUrlWithQuery(['sort' => $toggleSort]) }}">
                    Registro <span class="muted-sm">{{ $sort === 'created_desc' ? '↓' : '↑' }}</span>
                  </a>
                </th>
                <th style="min-width:140px">Estado</th>
                <th style="width:1%"></th>
              </tr>
            </thead>
            <tbody>
              @foreach($users as $u)
                @php
                    $nm = trim((string) ($u->name ?? ''));
                    $inits = collect(explode(' ', $nm))->filter()->map(fn($p) => mb_substr($p, 0, 1))->take(2)->implode('');
                    if ($inits === '')
                        $inits = mb_substr((string) ($u->username ?? 'U'), 0, 2);
                    $hasCel = (bool) $u->celular;
                    $isApproved = (bool) $u->approved_at;
                    $isLocked = (bool) $u->locked_at;
                    $role = $u->role ?? 'user';
                @endphp
                <tr>
                  @if(\Illuminate\Support\Facades\Route::has('admin.usuarios.bulk'))
                    <td data-label="Sel.">
                      <input class="chk" type="checkbox" form="bulkForm" name="ids[]" value="{{ $u->id }}"
                             aria-label="Seleccionar #{{ $u->id }}">
                    </td>
                  @endif

                  <td class="muted-sm" data-label="ID">#{{ $u->id }}</td>

                  <td data-label="Usuario">
                    <div style="display:flex; align-items:center; gap:.6rem">
                      <span class="avatar-chip" aria-hidden="true">{{ mb_strtoupper($inits) }}</span>
                      <div>
                        <div style="font-weight:700">{{ $u->name ?: '—' }}</div>
                        <div class="muted-sm">@ {{ $u->username ?: '—' }}</div>
                      </div>
                    </div>
                  </td>

                  <td data-label="Email">
                    <span class="copyable" data-copy="{{ $u->email }}" title="Clic/tocar para copiar">
                      {{ $u->email }}
                    </span>
                  </td>

                  <td data-label="Celular">
                    @if($hasCel)
                          <span class="copyable" data-copy="{{ $u->celular }}" title="Clic/tocar para copiar"
                                role="button" tabindex="0">{{ $u->celular }}</span>
                    @else
                          <span class="badge warn" title="Campo obligatorio en registro">FALTA</span>
                    @endif
                  </td>

                  <td data-label="Rol">
                    <span class="pill">{{ $role }}</span>
                  </td>

                  <td data-label="Registro">
                    <div>
                      <div class="muted-sm">{{ $u->created_at?->format('Y-m-d H:i') }}</div>
                      <div class="muted-sm">{{ $u->created_at?->diffForHumans() }}</div>
                    </div>
                  </td>

                  <td data-label="Estado">
                    <div style="display:flex; gap:.35rem; flex-wrap:wrap; align-items:center">
                      <span class="badge {{ $isApproved ? '' : 'warn' }}"
                            @if($isApproved) style="background:#7C6F63" @endif>
                        {{ $isApproved ? 'Aprobado' : 'Pendiente' }}
                      </span>
                      @if($isLocked)
                        <span class="badge">Bloqueado</span>
                      @endif
                    </div>
                  </td>

                  <td data-label="Acción" style="white-space:nowrap">
                    @if(\Illuminate\Support\Facades\Route::has('admin.usuarios.edit'))
                          <a class="btn sm line" href="{{ route('admin.usuarios.edit', $u) }}">Editar</a>
                    @endif

                    @if(!$isApproved && \Illuminate\Support\Facades\Route::has('admin.usuarios.aprobar'))
                          <form method="POST"
                                action="{{ route('admin.usuarios.aprobar', $u) }}"
                                style="display:inline"
                                onsubmit="return confirm('¿Aprobar a {{ $u->name ?: ('usuario #' . $u->id) }}?')">
                            @csrf
                            <button class="btn sm">Aprobar</button>
                          </form>
                    @endif

                    @if(\Illuminate\Support\Facades\Route::has('admin.usuarios.lock') && \Illuminate\Support\Facades\Route::has('admin.usuarios.unlock'))
                          @if(!$isLocked)
                            <form method="POST"
                                  action="{{ route('admin.usuarios.lock', $u) }}"
                                  style="display:inline"
                                  onsubmit="return confirm('¿Bloquear a {{ $u->name ?: ('usuario #' . $u->id) }}?')">
                              @csrf
                              <button class="btn sm">Bloquear</button>
                            </form>
                          @else
                            <form method="POST"
                                  action="{{ route('admin.usuarios.unlock', $u) }}"
                                  style="display:inline">
                              @csrf
                              <button class="btn sm">Desbloquear</button>
                            </form>
                          @endif
                    @endif
                  </td>
                </tr>
              @endforeach
            </tbody>
          </table>
        </div>

        <div class="mt-sm">{{ $users->onEachSide(1)->links() }}</div>
      @endif
@endsection

@push('scripts')
      <script>
        (function () {
          // Autosubmit en per_page
          var pp = document.getElementById('per_page');
          if (pp) pp.addEventListener('change', function () { pp.form.submit(); });

          // Bulk select + botones habilitados
          var chkAll = document.getElementById('chkAll');
          var checks = Array.prototype.slice.call(document.querySelectorAll('.chk'));
          var bulkForm = document.getElementById('bulkForm');
          var bulkAction = document.getElementById('bulkAction');
          var bulkBtns = ['btnBulkApprove','btnBulkLock','btnBulkUnlock']
            .map(function(id){ return document.getElementById(id); })
            .filter(Boolean);

          function refreshBulk() {
            var any = checks.some(function (c) { return c.checked; });
            bulkBtns.forEach(function (btn) { btn.disabled = !any; });
          }
          if (chkAll) {
            chkAll.addEventListener('change', function () {
              checks.forEach(function (c) { c.checked = chkAll.checked; });
              refreshBulk();
            });
          }
          checks.forEach(function (c) { c.addEventListener('change', refreshBulk); });
          refreshBulk();

          bulkBtns.forEach(function(btn){
            btn.addEventListener('click', function(e){
              if (btn.disabled) return;
              var msg = btn.getAttribute('data-confirm') || '¿Confirmar operación en lote?';
              if (!confirm(msg)) { e.preventDefault(); return; }
              if (bulkAction) bulkAction.value = btn.getAttribute('data-action') || '';
            });
          });

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

          // Búsqueda autosubmit tras 500 ms
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
