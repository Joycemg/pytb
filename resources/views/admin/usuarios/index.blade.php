@extends('layouts.app')
@section('title', 'Usuarios')

@section('content')
    <div class="container">
        <h1 class="m-0">Usuarios</h1>

        {{-- Filtros --}}
        <form class="card filters"
              method="GET"
              role="search"
              aria-label="Filtrar usuarios">
            <div>
                <label class="muted-sm"
                       for="q">Buscar</label>
                <input id="q"
                       name="q"
                       value="{{ old('q', $q ?? '') }}"
                       placeholder="Nombre, usuario o email…"
                       autocomplete="off">
            </div>
            <div>
                <label class="muted-sm"
                       for="rol">Rol</label>
                <select id="rol"
                        name="rol">
                    <option value="">(todos)</option>
                    @foreach(['user', 'moderator', 'admin'] as $r)
                        <option value="{{ $r }}"
                                @selected(($rol ?? '') === $r)>{{ $r }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="muted-sm"
                       for="estado">Estado</label>
                <select id="estado"
                        name="estado">
                    @foreach(['' => 'Todos', 'pending' => 'Pendientes', 'approved' => 'Aprobados', 'locked' => 'Bloqueados'] as $k => $lbl)
                        <option value="{{ $k }}"
                                @selected(($estado ?? '') === $k)>{{ $lbl }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="muted-sm">&nbsp;</label>
                <button class="btn full">Filtrar</button>
            </div>
        </form>

        @php
            use Illuminate\Support\Facades\Gate;
            $canApprove = Gate::allows('approve', \App\Models\Usuario::class);
            $canRole = Gate::allows('changeRole', \App\Models\Usuario::class);
            $canDelete = Gate::allows('bulkAction', [\App\Models\Usuario::class, 'delete']);
            $canLock = Gate::allows('bulkAction', [\App\Models\Usuario::class, 'lock']);
            $canUnlock = Gate::allows('bulkAction', [\App\Models\Usuario::class, 'unlock']);
        @endphp

        {{-- Acciones masivas + tabla --}}
        <form id="bulk-form"
              method="POST"
              action="{{ route('admin.usuarios.bulk') }}"
              class="mt-sm"
              data-require-selection="true"
              data-selection-selector=".chk">
            @csrf

            <div class="tools">
                <select name="action"
                        id="bulk-action">
                    @if($canApprove)
                    <option value="approve">Aprobar</option> @endif
                    @if($canRole)
                        <option value="role:user">Rol → user</option>
                        <option value="role:moderator">Rol → moderator</option>
                        <option value="role:admin">Rol → admin</option>
                    @endif
                    @if($canLock)
                    <option value="lock">Bloquear</option> @endif
                    @if($canUnlock)
                    <option value="unlock">Desbloquear</option> @endif
                    @if($canDelete)
                    <option value="delete">Borrar</option> @endif
                </select>

                <button type="button"
                        class="btn sm"
                        data-pwd-target="#bulk-form">
                    Aplicar a seleccionados
                </button>
            </div>

            <div class="tbl-wrap mt-sm"
                 role="region"
                 aria-label="Listado de usuarios">
                <table class="tbl"
                       role="table"
                       aria-describedby="tbl-help">
                    <thead>
                        <tr>
                            <th style="width:36px">
                                <input type="checkbox"
                                       id="chkAll"
                                       aria-label="Seleccionar todos">
                            </th>
                            <th>ID</th>
                            <th>Nombre</th>
                            <th>Email</th>
                            <th>Rol</th>
                            <th>Estado</th>
                            <th style="width:1%"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($users as $u)
                            <tr>
                                <td data-label="Sel.">
                                    <input class="chk"
                                           type="checkbox"
                                           name="ids[]"
                                           value="{{ $u->id }}"
                                           aria-label="Seleccionar #{{ $u->id }}">
                                </td>
                                <td data-label="ID"
                                    class="muted-sm">#{{ $u->id }}</td>
                                <td data-label="Nombre">{{ e($u->name) }}</td>
                                <td data-label="Email">{{ e($u->email) }}</td>
                                <td data-label="Rol"><span class="pill">{{ e($u->role ?? 'user') }}</span></td>
                                <td data-label="Estado">
                                    @if($u->locked_at)
                                        <span class="pill">bloqueado</span>
                                    @elseif(!$u->approved_at)
                                        <span class="pill">pendiente</span>
                                    @else
                                        <span class="pill">ok</span>
                                    @endif
                                </td>
                                <td data-label="Acción">
                                    @can('updateBasic', $u)
                                        <a class="btn line sm"
                                           href="{{ route('admin.usuarios.edit', $u) }}">Editar</a>
                                    @endcan
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7"
                                    class="text-center muted">Sin resultados</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <p id="tbl-help"
               class="visually-hidden">Tabla de usuarios con selección múltiple y acciones masivas.</p>
        </form>

        <div class="mt-sm">
            {{ $users->onEachSide(1)->links('pagination.hostinger') }}
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        (function () {
            const all = document.getElementById('chkAll');
            all && all.addEventListener('change', () => {
                document.querySelectorAll('.chk').forEach(c => c.checked = all.checked);
            });
        })();
    </script>
@endpush