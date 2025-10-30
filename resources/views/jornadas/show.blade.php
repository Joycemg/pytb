{{-- resources/views/jornadas/show.blade.php --}}
@extends('layouts.app')
@section('title', 'Jornada ' . e($numero ?? ('#' . $jornada->id)))

@section('content')
    @php
        use Illuminate\Support\Facades\Route as LRoute;

        $tz = config('app.timezone', 'UTC');
        $fechaApertura = $jornada->abierta_at ? $jornada->abierta_at->timezone($tz)->format('d/m/Y') : '—';
        $fechaCierre = $jornada->cerrada_at ? $jornada->cerrada_at->timezone($tz)->format('d/m/Y') : '—';

        $yo = auth()->user();
        $mesas = $jornada->mesas ?? collect();

        // Agrupar mesas
        $sinTurno = collect();
        $porTurno = collect();
        foreach ($mesas as $m) {
            $titulo = trim((string) (optional($m->apartado)->titulo ?? ''));
            if ($titulo === '')
                $sinTurno->push($m);
            else {
                if (!$porTurno->has($titulo))
                    $porTurno->put($titulo, collect());
                $porTurno[$titulo]->push($m);
            }
        }

        if (!isset($estadoPorMesaUser) || !is_array($estadoPorMesaUser))
            $estadoPorMesaUser = [];
        if (!isset($pendientesPorMesa) || !is_array($pendientesPorMesa))
            $pendientesPorMesa = [];

        if (!isset($totalMesas))
            $totalMesas = $mesas->count();
        if (!isset($totalJug)) {
            $totalJug = 0;
            foreach ($mesas as $mm) {
                $totalJug += ($mm->inscripciones?->count() ?? 0);
            }
        }
    @endphp

    <div class="toolbar">
        <h1 style="margin:0">Jornada {{ e($numero ?? ('#' . $jornada->id)) }} — {{ $fechaApertura }}</h1>
        <span class="pill {{ ($jornada->estado ?? '') === 'abierta' ? 'ok' : 'warn' }}">
            {{ strtoupper($jornada->estado ?? '—') }}
        </span>
    </div>

    <section class="card"
             style="display:flex;gap:16px;flex-wrap:wrap;margin:.75rem 0 1rem">
        <div><strong>Mesas:</strong> {{ (int) $totalMesas }}</div>
        <div><strong>Jugadores:</strong> {{ (int) $totalJug }}</div>
        @if(method_exists($jornada, 'estaCerrada') ? $jornada->estaCerrada() : ($jornada->estado ?? '') === 'cerrada')
            <div><strong>Cerrada:</strong> {{ $fechaCierre }}</div>
        @endif
    </section>

    {{-- ===== GENERAL (sin turno) ===== --}}
    @if($sinTurno->count())
        <h3 class="h3">GENERAL</h3>
        @foreach($sinTurno as $mesa)
            @php
                $pendMesa = (int) ($pendientesPorMesa[$mesa->id] ?? 0);
                $canModerate = $yo && (
                    (method_exists($yo, 'hasAnyRole') && $yo->hasAnyRole(['admin', 'moderator'])) ||
                    ((int) $yo->id === (int) ($mesa->manager_id ?? 0))
                );
            @endphp

            <section class="card"
                     style="margin-bottom:1rem">
                <h3 style="margin:.2rem 0">{{ e($mesa->title) }}</h3>

                <div class="muted-sm"
                     style="margin-bottom:.3rem">
                    Capacidad: {{ (int) $mesa->capacity }}
                    · Manager: {{ e(optional($mesa->manager)->name ?? '—') }}
                    · Creador: {{ e(optional($mesa->creador)->name ?? '—') }}
                    @if($pendMesa > 0)
                        · <span class="pill info"
                              title="Pendientes de moderar">
                            {{ $pendMesa }} pendiente{{ $pendMesa > 1 ? 's' : '' }}
                        </span>
                    @endif
                </div>

                @if(($mesa->inscripciones ?? collect())->count())
                    @if($canModerate && $pendMesa > 0 && LRoute::has('moderacion.confirmarMesa'))
                        <form method="post"
                              action="{{ route('moderacion.confirmarMesa', $mesa) }}"
                              class="form-lote"
                              id="form-mesa-{{ $mesa->id }}"
                              autocomplete="off">
                            @csrf
                    @endif

                        <div class="tbl-wrap">
                            <table class="tbl"
                                   role="table"
                                   aria-label="Inscripciones de la mesa">
                                <thead>
                                    <tr>
                                        <th>Jugador</th>
                                        <th>Votó</th>
                                        <th>Asistió</th>
                                        <th>Comportamiento</th>
                                        <th>Estado</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($mesa->inscripciones as $i)
                                        @php
                                            $bloqueado = method_exists($i, 'estaModerada') ? $i->estaModerada() : (bool) ($i->moderated_at ?? false);
                                            $key = $i->mesa_id . ':' . $i->user_id;
                                            $est = $estadoPorMesaUser[$key] ?? ['asistencia' => null, 'comportamiento' => null];

                                            $txtAsist = $est['asistencia'] === 'presente' ? 'Sí' : ($est['asistencia'] === 'noshow' ? 'No' : '—');
                                            $txtComp = $est['comportamiento'] === 'good' ? 'Bueno' : ($est['comportamiento'] === 'bad' ? 'Malo' : ($est['comportamiento'] === 'neutral' ? 'Neutral' : '—'));
                                            $voto = optional($i->created_at)->timezone($tz)?->format('d/m/Y H:i:s');
                                            $nombreJugador = optional($i->usuario)->name ?? '—';
                                        @endphp

                                        <tr>
                                            <td data-label="Jugador">{{ e($nombreJugador) }}</td>
                                            <td data-label="Votó"
                                                class="mono">{{ $voto ?? '—' }}</td>
                                            <td data-label="Asistió">
                                                @if($bloqueado || !$canModerate || $pendMesa === 0)
                                                    <span>{{ $txtAsist }}</span>
                                                @else
                                                    <label style="display:inline-flex;gap:8px;align-items:center;margin:0;cursor:pointer">
                                                        <input type="checkbox"
                                                               name="asistencia[{{ $i->user_id }}]"
                                                               value="1"
                                                               class="chk-asistencia"
                                                               style="width:18px;height:18px;appearance:auto;accent-color: var(--brand)">
                                                        <span>Asistió</span>
                                                    </label>
                                                @endif
                                            </td>
                                            <td data-label="Comportamiento">
                                                @if($bloqueado || !$canModerate || $pendMesa === 0)
                                                    <span>{{ $txtComp }}</span>
                                                @else
                                                    <div class="wrap-comp"
                                                         style="opacity:.5;pointer-events:none">
                                                        <select name="comportamiento[{{ $i->user_id }}]">
                                                            <option value="neutral">Neutral</option>
                                                            <option value="good">Bueno</option>
                                                            <option value="bad">Malo</option>
                                                        </select>
                                                    </div>
                                                @endif
                                            </td>
                                            <td data-label="Estado">
                                                @if($bloqueado)
                                                    <span class="pill ok">Confirmado</span>
                                                    <small class="muted-sm">
                                                        · por {{ e(optional($i->moderador)->name ?? '—') }}
                                                        @php $mAt = optional($i->moderated_at)->timezone($tz); @endphp
                                                        @if($mAt) el {{ $mAt->format('d/m/Y H:i') }} @endif
                                                    </small>
                                                @else
                                                    <span class="muted-sm">Pendiente</span>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>

                        @if($canModerate && $pendMesa > 0 && LRoute::has('moderacion.confirmarMesa'))
                                <div class="mt-sm"
                                     style="display:flex;gap:8px;align-items:center">
                                    <button type="button"
                                            class="btn"
                                            id="btn-open-{{ $mesa->id }}">
                                        Confirmar selección ({{ $pendMesa }})
                                    </button>
                                </div>
                                <dialog id="dlg-{{ $mesa->id }}">
                                    <h3 style="margin:.2rem 0 1rem">Confirmar moderación</h3>
                                    <p class="muted-sm"
                                       style="margin:.2rem 0 .8rem">
                                        Ingresá tu contraseña para confirmar los cambios de esta mesa.
                                    </p>
                                    <label for="pass-{{ $mesa->id }}">Contraseña</label>
                                    <input id="pass-{{ $mesa->id }}"
                                           type="password"
                                           name="password"
                                           form="form-mesa-{{ $mesa->id }}"
                                           required
                                           placeholder="••••••••"
                                           autocomplete="current-password">
                                    <div style="display:flex;gap:8px;justify-content:flex-end;margin-top:12px">
                                        <button type="button"
                                                class="btn ghost"
                                                id="btn-cancel-{{ $mesa->id }}">Cancelar</button>
                                        <button type="submit"
                                                class="btn"
                                                form="form-mesa-{{ $mesa->id }}"
                                                data-once
                                                data-delay="300">Confirmar</button>
                                    </div>
                                </dialog>
                            </form>
                        @endif
                @else
                    <div class="muted-sm">Sin inscriptos.</div>
                @endif
            </section>
        @endforeach
    @endif

    {{-- ===== Por TURNO ===== --}}
    @foreach($porTurno as $tituloTurno => $grupoMesas)
        <h3 class="h3">{{ strtoupper($tituloTurno) }}</h3>
        @foreach($grupoMesas as $mesa)
            @php
                $pendMesa = (int) ($pendientesPorMesa[$mesa->id] ?? 0);
                $canModerate = $yo && (
                    (method_exists($yo, 'hasAnyRole') && $yo->hasAnyRole(['admin', 'moderator'])) ||
                    ((int) $yo->id === (int) ($mesa->manager_id ?? 0))
                );
            @endphp

            <section class="card"
                     style="margin-bottom:1rem">
                <h3 style="margin:.2rem 0">{{ e($mesa->title) }}</h3>

                <div class="muted-sm"
                     style="margin-bottom:.3rem">
                    Capacidad: {{ (int) $mesa->capacity }}
                    · Manager: {{ e(optional($mesa->manager)->name ?? '—') }}
                    · Creador: {{ e(optional($mesa->creador)->name ?? '—') }}
                    @if($pendMesa > 0)
                        · <span class="pill info"
                              title="Pendientes de moderar">
                            {{ $pendMesa }} pendiente{{ $pendMesa > 1 ? 's' : '' }}
                        </span>
                    @endif
                </div>

                @if(($mesa->inscripciones ?? collect())->count())
                    @if($canModerate && $pendMesa > 0 && LRoute::has('moderacion.confirmarMesa'))
                        <form method="post"
                              action="{{ route('moderacion.confirmarMesa', $mesa) }}"
                              class="form-lote"
                              id="form-mesa-{{ $mesa->id }}"
                              autocomplete="off">
                            @csrf
                    @endif

                        <div class="tbl-wrap">
                            <table class="tbl"
                                   role="table"
                                   aria-label="Inscripciones de la mesa">
                                <thead>
                                    <tr>
                                        <th>Jugador</th>
                                        <th>Votó</th>
                                        <th>Asistió</th>
                                        <th>Comportamiento</th>
                                        <th>Estado</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($mesa->inscripciones as $i)
                                        @php
                                            $bloqueado = method_exists($i, 'estaModerada') ? $i->estaModerada() : (bool) ($i->moderated_at ?? false);
                                            $key = $i->mesa_id . ':' . $i->user_id;
                                            $est = $estadoPorMesaUser[$key] ?? ['asistencia' => null, 'comportamiento' => null];

                                            $txtAsist = $est['asistencia'] === 'presente' ? 'Sí' : ($est['asistencia'] === 'noshow' ? 'No' : '—');
                                            $txtComp = $est['comportamiento'] === 'good' ? 'Bueno' : ($est['comportamiento'] === 'bad' ? 'Malo' : ($est['comportamiento'] === 'neutral' ? 'Neutral' : '—'));
                                            $voto = optional($i->created_at)->timezone($tz)?->format('d/m/Y H:i:s');
                                            $nombreJugador = optional($i->usuario)->name ?? '—';
                                        @endphp

                                        <tr>
                                            <td data-label="Jugador">{{ e($nombreJugador) }}</td>
                                            <td data-label="Votó"
                                                class="mono">{{ $voto ?? '—' }}</td>
                                            <td data-label="Asistió">
                                                @if($bloqueado || !$canModerate || $pendMesa === 0)
                                                    <span>{{ $txtAsist }}</span>
                                                @else
                                                    <label style="display:inline-flex;gap:8px;align-items:center;margin:0;cursor:pointer">
                                                        <input type="checkbox"
                                                               name="asistencia[{{ $i->user_id }}]"
                                                               value="1"
                                                               class="chk-asistencia"
                                                               style="width:18px;height:18px;appearance:auto;accent-color: var(--brand)">
                                                        <span>Asistió</span>
                                                    </label>
                                                @endif
                                            </td>
                                            <td data-label="Comportamiento">
                                                @if($bloqueado || !$canModerate || $pendMesa === 0)
                                                    <span>{{ $txtComp }}</span>
                                                @else
                                                    <div class="wrap-comp"
                                                         style="opacity:.5;pointer-events:none">
                                                        <select name="comportamiento[{{ $i->user_id }}]">
                                                            <option value="neutral">Neutral</option>
                                                            <option value="good">Bueno</option>
                                                            <option value="bad">Malo</option>
                                                        </select>
                                                    </div>
                                                @endif
                                            </td>
                                            <td data-label="Estado">
                                                @if($bloqueado)
                                                    <span class="pill ok">Confirmado</span>
                                                    <small class="muted-sm">
                                                        · por {{ e(optional($i->moderador)->name ?? '—') }}
                                                        @php $mAt = optional($i->moderated_at)->timezone($tz); @endphp
                                                        @if($mAt) el {{ $mAt->format('d/m/Y H:i') }} @endif
                                                    </small>
                                                @else
                                                    <span class="muted-sm">Pendiente</span>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>

                        @if($canModerate && $pendMesa > 0 && LRoute::has('moderacion.confirmarMesa'))
                                <div class="mt-sm"
                                     style="display:flex;gap:8px;align-items:center">
                                    <button type="button"
                                            class="btn"
                                            id="btn-open-{{ $mesa->id }}">
                                        Confirmar selección ({{ $pendMesa }})
                                    </button>
                                </div>
                                <dialog id="dlg-{{ $mesa->id }}">
                                    <h3 style="margin:.2rem 0 1rem">Confirmar moderación</h3>
                                    <p class="muted-sm"
                                       style="margin:.2rem 0 .8rem">
                                        Ingresá tu contraseña para confirmar los cambios de esta mesa.
                                    </p>
                                    <label for="pass-{{ $mesa->id }}">Contraseña</label>
                                    <input id="pass-{{ $mesa->id }}"
                                           type="password"
                                           name="password"
                                           form="form-mesa-{{ $mesa->id }}"
                                           required
                                           placeholder="••••••••"
                                           autocomplete="current-password">
                                    <div style="display:flex;gap:8px;justify-content:flex-end;margin-top:12px">
                                        <button type="button"
                                                class="btn ghost"
                                                id="btn-cancel-{{ $mesa->id }}">Cancelar</button>
                                        <button type="submit"
                                                class="btn"
                                                form="form-mesa-{{ $mesa->id }}"
                                                data-once
                                                data-delay="300">Confirmar</button>
                                    </div>
                                </dialog>
                            </form>
                        @endif
                @else
                    <div class="muted-sm">Sin inscriptos.</div>
                @endif
            </section>
        @endforeach
    @endforeach
@endsection

@push('scripts')
    <script>
        /**
         * Cableado de:
         * - Apertura/cierre del <dialog> por mesa (btn-open-*, dlg-*, btn-cancel-*).
         * - Habilitar <select> de comportamiento solo si "Asistió" está tildado.
         */
        document.addEventListener('DOMContentLoaded', function () {
            // Abrir/cerrar modales por mesa
            document.querySelectorAll('button[id^="btn-open-"]').forEach(function (btn) {
                const mesaId = btn.id.replace('btn-open-', '');
                const dlg = document.getElementById('dlg-' + mesaId);
                const cancel = document.getElementById('btn-cancel-' + mesaId);
                const pass = document.getElementById('pass-' + mesaId);
                if (!dlg) return;

                btn.addEventListener('click', function () {
                    try { dlg.showModal(); } catch (_) { return; }
                    if (pass) setTimeout(() => pass.focus(), 50);
                });

                cancel?.addEventListener('click', function () { dlg.close('cancel'); });

                // Cerrar click fuera
                dlg.addEventListener('click', function (e) {
                    const r = dlg.getBoundingClientRect();
                    const inside = e.clientX >= r.left && e.clientX <= r.right && e.clientY >= r.top && e.clientY <= r.bottom;
                    if (!inside) dlg.close('cancel');
                });
            });

            // Habilitar/deshabilitar select de comportamiento
            function setCompEnabled(row, enabled) {
                const wrap = row.querySelector('.wrap-comp');
                const sel = wrap ? wrap.querySelector('select') : null;
                if (!wrap || !sel) return;
                if (enabled) {
                    wrap.style.opacity = '1';
                    wrap.style.pointerEvents = 'auto';
                    sel.disabled = false;
                } else {
                    wrap.style.opacity = '.5';
                    wrap.style.pointerEvents = 'none';
                    sel.disabled = true;
                    sel.value = 'neutral'; // coherente con backend
                }
            }

            // Estado inicial + listeners
            document.querySelectorAll('table.tbl tbody tr').forEach(function (tr) {
                const chk = tr.querySelector('.chk-asistencia');
                if (!chk) return;
                setCompEnabled(tr, chk.checked === true);
                chk.addEventListener('change', function () {
                    setCompEnabled(tr, chk.checked === true);
                });
            });
        });
    </script>
@endpush