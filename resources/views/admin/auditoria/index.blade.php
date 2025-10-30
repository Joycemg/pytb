{{-- resources/views/admin/auditoria/index.blade.php --}}
@extends('layouts.app')
@section('title', 'Auditoría')

@section('content')
    <div class="container">
        <h1 style="margin:0 0 .6rem">Auditoría</h1>

        {{-- ===== Filtros ===== --}}
        <form method="GET"
              class="card filters"
              autocomplete="off">
            <div>
                <label class="muted-sm"
                       for="q">Buscar</label>
                <input id="q"
                       name="q"
                       value="{{ old('q', $q ?? '') }}"
                       placeholder="IP o User-Agent">
            </div>

            <div>
                <label class="muted-sm"
                       for="action">Acción</label>
                <input id="action"
                       name="action"
                       value="{{ old('action', $action ?? '') }}"
                       placeholder="ej: approve">
            </div>

            <div>
                <label class="muted-sm"
                       for="actor_id">Actor ID</label>
                <input id="actor_id"
                       name="actor_id"
                       value="{{ old('actor_id', $actor_id ?? '') }}">
            </div>

            <div>
                <label class="muted-sm"
                       for="target_id">Target ID</label>
                <input id="target_id"
                       name="target_id"
                       value="{{ old('target_id', $target_id ?? '') }}">
            </div>

            <div>
                <label class="muted-sm"
                       for="from">Desde</label>
                <input id="from"
                       type="date"
                       name="from"
                       value="{{ old('from', $from ?? '') }}">
            </div>

            <div>
                <label class="muted-sm"
                       for="to">Hasta</label>
                <input id="to"
                       type="date"
                       name="to"
                       value="{{ old('to', $to ?? '') }}">
            </div>

            <div>
                <label class="muted-sm"
                       for="per_page">Por página</label>
                <select id="per_page"
                        name="per_page">
                    @foreach([20, 40, 60, 80, 100] as $pp)
                        <option value="{{ $pp }}"
                                @selected(($per_page ?? 40) == $pp)>{{ $pp }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="muted-sm">&nbsp;</label>
                <div class="tools">
                    <button class="btn sm"
                            data-once>Filtrar</button>
                    <a class="btn line sm"
                       href="{{ route('admin.auditoria.index') }}">Limpiar</a>
                </div>
            </div>

            <div style="margin-left:auto">
                <label class="muted-sm">&nbsp;</label>
                @can('export', \App\Models\UserAudit::class)
                    <a class="btn ghost sm"
                       href="{{ route('admin.auditoria.export', request()->query()) }}">
                        Exportar CSV
                    </a>
                @endcan
            </div>
        </form>

        {{-- ===== Timeline =====
        CSS modular sugerido:
        - resources/css/components/timeline.css
        Clases usadas: .timeline, .tl-item, .tl-head, .tl-meta, .mono,
        .tl-dot-approve/.lock/.unlock/.role/.update/.loginok/.loginfail/.register
        --}}
        <div class="card p-sm">
            <div class="timeline">
                @forelse($audits as $a)
                    @php
                        $actor = $a->actor_id ? ($usersMap[$a->actor_id] ?? null) : null;
                        $target = $a->target_id ? ($usersMap[$a->target_id] ?? null) : null;

                        $actorName = $actor ? ($actor->name ?: ('#' . $a->actor_id)) : 'Sistema';
                        $targetName = $target ? ($target->name ?: ('#' . $a->target_id)) : ($a->target_id ? '#' . $a->target_id : '—');

                        $actorBadge = $actor && $actor->role ? strtolower($actor->role) : null;
                        $targetBadge = $target && $target->role ? strtolower($target->role) : null;

                        $meta = $a->meta ?? [];
                        $accion = (string) $a->action;

                        // Frase + color del punto
                        $texto = '';
                        $cssDot = '';

                        switch ($accion) {
                            case 'approve':
                                $texto = "{$actorName} aprobó a {$targetName}";
                                $cssDot = 'tl-dot-approve';
                                break;
                            case 'lock':
                                $texto = "{$actorName} bloqueó a {$targetName}";
                                $cssDot = 'tl-dot-lock';
                                break;
                            case 'unlock':
                                $texto = "{$actorName} desbloqueó a {$targetName}";
                                $cssDot = 'tl-dot-unlock';
                                break;
                            case 'role.set':
                                $from = $meta['from'] ?? null;
                                $to = $meta['to'] ?? null;
                                $cambio = ($from && $to) ? "de {$from} a {$to}" : '';
                                $texto = "{$actorName} cambió el rol {$cambio} de {$targetName}";
                                $cssDot = 'tl-dot-role';
                                break;
                            case 'pwd.reset':
                                $texto = "{$actorName} reseteó la contraseña de {$targetName}";
                                $cssDot = 'tl-dot-update';
                                break;
                            case 'user.update.basic':
                                $fields = isset($meta['fields']) && is_array($meta['fields']) ? implode(', ', $meta['fields']) : 'campos';
                                $texto = "{$actorName} editó {$fields} de {$targetName}";
                                $cssDot = 'tl-dot-update';
                                break;
                            default:
                                if (str_starts_with($accion, 'bulk.')) {
                                    $que = substr($accion, 5);
                                    if ($que === 'role') {
                                        $texto = "{$actorName} cambió rol (masivo) de {$targetName}";
                                        $cssDot = 'tl-dot-role';
                                    } elseif ($que === 'lock') {
                                        $texto = "{$actorName} bloqueó (masivo) a {$targetName}";
                                        $cssDot = 'tl-dot-lock';
                                    } elseif ($que === 'unlock') {
                                        $texto = "{$actorName} desbloqueó (masivo) a {$targetName}";
                                        $cssDot = 'tl-dot-unlock';
                                    } elseif ($que === 'delete') {
                                        $texto = "{$actorName} eliminó (masivo) a {$targetName}";
                                        $cssDot = 'tl-dot-update';
                                    } else {
                                        $texto = "{$actorName} ejecutó {$accion} sobre {$targetName}";
                                        $cssDot = 'tl-dot-update';
                                    }
                                } elseif ($accion === 'login.ok') {
                                    $texto = "{$actorName} inició sesión correctamente";
                                    $cssDot = 'tl-dot-loginok';
                                } elseif ($accion === 'login.fail') {
                                    $texto = "Intento de inicio de sesión fallido";
                                    $cssDot = 'tl-dot-loginfail';
                                } elseif ($accion === 'register.ok') {
                                    $texto = "{$actorName} se registró (aprobado)";
                                    $cssDot = 'tl-dot-register';
                                } elseif ($accion === 'register.pending') {
                                    $texto = "{$targetName} se registró (pendiente de aprobación)";
                                    $cssDot = 'tl-dot-register';
                                } else {
                                    $texto = "{$actorName} ejecutó {$accion} sobre {$targetName}";
                                    $cssDot = 'tl-dot-update';
                                }
                                break;
                        }

                        $fecha = $a->created_at?->format('Y-m-d H:i');
                        $ip = $a->ip ? (string) $a->ip : null;
                        $ua = $a->ua ? (string) $a->ua : null;

                        $metaStr = '';
                        if (!empty($meta)) {
                            $json = json_encode($meta, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                            $metaStr = mb_strlen($json) <= 120 ? $json : null;
                        }
                      @endphp

                    <div class="tl-item {{ $cssDot }}">
                        <div class="tl-head"
                             style="margin-bottom:.25rem">
                            <span class="badge">{{ e($accion) }}</span>
                            @if($actorBadge)
                                <span class="badge">actor: {{ e($actorBadge) }}</span>
                            @endif
                            @if($targetBadge)
                                <span class="badge">target: {{ e($targetBadge) }}</span>
                            @endif
                        </div>

                        <div style="margin-bottom:.25rem">{{ $texto }}</div>

                        <div class="tl-meta muted-sm">
                            @if($fecha)<span class="mono">{{ $fecha }}</span>@endif
                            @if($ip)<span class="mono"
                              style="margin-left:.5rem">IP: {{ $ip }}</span>@endif
                            @if($ua)<span class="mono"
                                  style="margin-left:.5rem;display:inline-block;max-width:100%;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">UA:
                            {{ \Illuminate\Support\Str::limit($ua, 120) }}</span>@endif
                        </div>

                        @if($metaStr !== '')
                            <div style="margin-top:.25rem">
                                <code class="mono"
                                      style="white-space:pre-wrap;font-size:.82rem">{{ $metaStr }}</code>
                            </div>
                        @elseif(!empty($meta))
                            <details style="margin-top:.25rem">
                                <summary class="muted-sm">ver meta…</summary>
                                <pre class="mono"
                                     style="margin:0;white-space:pre-wrap;font-size:.82rem">
                                        {{ json_encode($meta, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</pre>
                            </details>
                        @endif
                    </div>
                @empty
                    <div class="text-center muted"
                         style="padding:1rem 0">Sin resultados</div>
                @endforelse
            </div>
        </div>

        <div class="mt-sm">
            {{ $audits->onEachSide(1)->links('pagination.hostinger') }}
        </div>
    </div>
@endsection