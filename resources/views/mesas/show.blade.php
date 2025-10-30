{{-- resources/views/mesas/show.blade.php --}}
@extends('layouts.app')
@section('title', e($mesa->title))

@section('content')
  @php
    use Illuminate\Support\Facades\Route as LRoute;
    use Carbon\Carbon;

    $tz = config('app.display_timezone', config('app.timezone', 'America/Argentina/La_Rioja'));

    $efectivamenteAbierta = method_exists($mesa, 'isEffectivelyOpen')
      ? $mesa->isEffectivelyOpen()
      : (bool) ($mesa->is_open ?? false);

    // ✅ Usamos la relación confirmada que trae el controller
    $confirmados = $mesa->inscripcionesConfirmadas ?? collect();

    $capBase = method_exists($mesa, 'capacidadEfectiva')
      ? (int) $mesa->capacidadEfectiva()
      : max(0, (int) $mesa->capacity - ((bool) $mesa->manager_counts_as_player ? 1 : 0));

    $full = $confirmados->count() >= $capBase;

    $yo = auth()->user();
    $ya = $yo ? $confirmados->firstWhere('user_id', $yo->id) : null;

    $esAdminMod = $yo && (
      (method_exists($yo, 'hasAnyRole') && $yo->hasAnyRole(['admin', 'moderator'])) ||
      (method_exists($yo, 'hasRole') && ($yo->hasRole('admin') || $yo->hasRole('moderator')))
    );

    $puedeVerContactoJugadores = $esAdminMod || ($yo && (int) $yo->id === (int) ($mesa->manager_id ?? 0));
    $puedeVerContactoManager = $esAdminMod;
    $mostrarColTelefono = $puedeVerContactoJugadores || $puedeVerContactoManager;

    $routeSignup = LRoute::has('mesas.signup') ? 'mesas.signup'
      : (LRoute::has('inscripciones.store') ? 'inscripciones.store' : null);
    $routeUnvote = LRoute::has('mesas.signup.remove') ? 'mesas.signup.remove'
      : (LRoute::has('inscripciones.destroy') ? 'inscripciones.destroy' : null);

    $img = method_exists($mesa, 'getImageSrcAttribute') ? $mesa->image_src
      : ($mesa->image_path ? asset('storage/' . ltrim($mesa->image_path, '/')) : ($mesa->image_url ?: null));

    $tituloApartado = optional($mesa->apartado)->titulo;

    $managerId = (int) ($mesa->manager_id ?? 0);
    $managerCuenta = (bool) ($mesa->manager_counts_as_player ?? false);
    $hayManagerJugador = $managerCuenta && $managerId > 0;

    // ✅ Ahora confirmados ya viene “solo confirmados”; solo excluimos al manager si cuenta
    $confirmadosSinManager = $hayManagerJugador
      ? $confirmados->filter(fn($i) => (int) $i->user_id !== $managerId)->values()
      : $confirmados;

    $totalJugadores = ($hayManagerJugador ? 1 : 0) + $confirmadosSinManager->count();

    // Detectar si estoy inscripto en OTRA mesa del mismo turno/apartado
    $yaEstoyEnOtraMesaMismoApartado = false;
    if (auth()->check() && !$ya) {
      $apartadoId = $mesa->jornada_apartado_id;
      $jornadaId = $mesa->jornada_id;
      $yaEstoyEnOtraMesaMismoApartado = \App\Models\Mesa::query()
        ->where('jornada_id', $jornadaId)
        ->where(function ($qq) use ($apartadoId) {
          if ($apartadoId === null)
            $qq->whereNull('jornada_apartado_id');
          else
            $qq->where('jornada_apartado_id', $apartadoId);
        })
        ->where('id', '<>', $mesa->id)
        ->whereHas('inscripciones', function ($iq) {
          $iq->where('is_waiting', false)->where('user_id', auth()->id());
        })
        ->toBase()
        ->exists();
    }

    // Apertura: usa inscripciones_abren_at o abre con opens_at
    $apertura = $mesa->inscripciones_abren_at ?? $mesa->opens_at ?? null;
    $opensTs = $apertura ? Carbon::parse($apertura, $tz)->utc()->getTimestampMs() : null;
    $yaAbre = $apertura ? now($tz)->greaterThanOrEqualTo(Carbon::parse($apertura, $tz)) : true;
    $titleBtn = $yaAbre
      ? 'Inscribirme'
      : ('Disponible: ' . Carbon::parse($apertura, $tz)->isoFormat('DD/MM/YYYY HH:mm'));
  @endphp

  <div class="grid grid-2">
    <section class="card">
      {{-- Portada --}}
      @if($img)
        <div class="card" style="padding:0; overflow:hidden; border:1px solid var(--line); border-radius:8px; height:clamp(140px,28vw,260px)">
          <img src="{{ $img }}"
               alt="Imagen de la mesa {{ e($mesa->title) }}"
               loading="lazy" decoding="async" referrerpolicy="no-referrer"
               style="display:block;width:100%;height:100%;object-fit:cover">
        </div>
      @else
        <div class="card"
             style="display:grid;place-items:center;height:clamp(140px,28vw,260px);border:1px solid var(--line);border-radius:8px">
          <span class="muted">Sin imagen</span>
        </div>
      @endif

      <h1 style="margin:.8rem 0 .2rem;display:flex;gap:.5rem;align-items:center;flex-wrap:wrap">
        {{ e($mesa->title) }}
        @if(!empty($tituloApartado))
          <span class="badge" title="Apartado">{{ e($tituloApartado) }}</span>
        @endif
      </h1>

      <div class="muted" style="font-size:.95rem">
        @if($efectivamenteAbierta) <span class="badge">Abierta</span> @else <span class="badge">Cerrada</span> @endif
        · Capacidad {{ (int) $mesa->capacity }}
        @if($mesa->single_vote) · <span class="badge">Voto único</span> @endif
        @if($mesa->opens_at) · Abre {{ $mesa->opens_at->timezone($tz)->format('d/m H:i') }} @endif
        @if($managerCuenta) · <span class="badge">Manager cuenta</span> @endif
      </div>

      {{-- Apertura con countdown --}}
      @if($apertura)
        <div class="muted" style="font-size:.85rem" aria-live="polite">
          {{ __('Apertura') }}:
          <time
            datetime="{{ \Carbon\Carbon::parse($apertura, $tz)->toAtomString() }}"
            data-countdown-ts="{{ $opensTs }}">
            {{ \Carbon\Carbon::parse($apertura, $tz)->format('Y-m-d H:i') }}
          </time>
          <span class="count" data-countdown-label></span>
        </div>
      @endif

      @if(!empty($mesa->description))
        <p style="margin-top:.8rem;white-space:pre-wrap">{{ $mesa->description }}</p>
      @endif

      @auth
        @php
          $soyManagerEnJornada = false;
          if (auth()->check() && $mesa->jornada_id) {
            $soyManagerEnJornada = \App\Models\Mesa::where('jornada_id', $mesa->jornada_id)
              ->where('manager_id', auth()->id())->exists();
          }
        @endphp

        @if(!$soyManagerEnJornada)
          @if($ya && $routeUnvote)
            <form method="post" action="{{ route($routeUnvote, $mesa) }}" style="margin-top:1rem">
              @csrf @method('DELETE')
              <button class="btn line" data-once>🗑️ Sacar mi voto</button>
            </form>
          @elseif(!$ya && $routeSignup)
            {{-- Botón Inscribirme (bloqueos) --}}
            @php
              $bloqueoHorario = (bool) $apertura && !$yaAbre;
              $bloqueoOtraMesa = (bool) $yaEstoyEnOtraMesaMismoApartado;
              $bloqueoFull = (bool) $full;
              $bloqueoCierre = !$efectivamenteAbierta && !$bloqueoHorario;
              $disabled = $bloqueoHorario || $bloqueoOtraMesa || $bloqueoFull || $bloqueoCierre;
              $soloHorario = $bloqueoHorario && !$bloqueoOtraMesa && !$bloqueoFull && !$bloqueoCierre;
            @endphp

            <form method="post" action="{{ route($routeSignup, $mesa) }}" style="margin-top:1rem">
              @csrf
              <button class="btn"
                      data-once
                      data-delay="500"
                      @if($disabled) disabled aria-disabled="true" @endif
                      @if($soloHorario && $opensTs) data-activate-at-utc="{{ $opensTs }}" @endif
                      title="{{ $titleBtn }}"
                      data-enabled-title="Inscribirme"
                      aria-label="@if($bloqueoFull) Mesa llena
                       @elseif($bloqueoCierre) Mesa cerrada
                       @elseif($bloqueoOtraMesa) Ya estás inscripto en otra mesa de este turno
                       @elseif($bloqueoHorario) {{ $titleBtn }}
                       @else Inscribirme @endif"
                      class="{{ $bloqueoHorario ? 'is-disabled' : '' }}">
                @if($bloqueoFull) Mesa llena
                @elseif($bloqueoCierre) Cerrada
                @elseif($bloqueoHorario) Abre pronto
                @else Inscribirme
                @endif
              </button>
            </form>

            @if($yaEstoyEnOtraMesaMismoApartado)
              <p class="muted" style="margin:.5rem 0 0">
                Ya estás inscripto en otra mesa de este turno. Quitá tu inscripción de esa mesa para poder inscribirte acá.
              </p>
            @endif
          @endif
        @endif
      @endauth

      {{-- ======= TABLA ======= --}}
      <h3 id="inscriptos" style="margin:1rem 0 .4rem">Jugadores ({{ $totalJugadores }})</h3>

      <div class="tbl-wrap">
        <table class="tbl" role="table" aria-label="Jugadores inscriptos">
          <thead>
            <tr>
              <th style="width:56px">#</th>
              <th>Jugador</th>
              @if($mostrarColTelefono)<th style="width:280px">Celular</th>@endif
              <th style="width:220px">Votó</th>
            </tr>
          </thead>
          <tbody>
            @php
              $row = 0;
              $totalCols = 3 + ($mostrarColTelefono ? 1 : 0);
            @endphp

            {{-- MANAGER (si cuenta como jugador) --}}
            @if($hayManagerJugador)
              @php
                $row = 1;
                $managerNombre = e(optional($mesa->manager)->name ?? '—');
                $managerPhone = null;
                if ($puedeVerContactoManager && $mesa->manager) {
                  foreach (array_keys($mesa->manager->getAttributes()) as $k) {
                    if ($k && preg_match('/(phone|cel|m[oó]vil|tel)/i', $k)) {
                      $val = $mesa->manager->getAttribute($k);
                      if (!empty($val)) {
                        $managerPhone = trim((string) $val);
                        break;
                      }
                    }
                  }
                }
              @endphp
              <tr>
                <td class="mono" data-label="#">@if($row < 10)&nbsp;@endif{{ $row }}</td>
                <td colspan="{{ $totalCols - 1 }}">
                  <strong>{{ $managerNombre }}</strong>
                  <span class="badge-sm" style="margin-left:.35rem">Manager</span>
                  @if($managerPhone)
                    <span class="muted-sm" style="margin:0 .35rem">•</span>
                    <span class="copyable mono" role="button" tabindex="0" title="Tocar para copiar"
                          aria-label="Copiar celular del manager" data-copy="{{ e($managerPhone) }}">{{ e($managerPhone) }}</span>
                  @endif
                </td>
              </tr>
            @endif

            {{-- JUGADORES --}}
            @forelse($confirmadosSinManager as $i)
              @php
                $row++;
                $u = $i->usuario;
                $voto = optional($i->created_at)->timezone($tz)?->format('d/m/Y H:i:s');

                $celStr = null;
                if ($puedeVerContactoJugadores && $u) {
                  foreach (array_keys($u->getAttributes()) as $k) {
                    if ($k && preg_match('/(phone|cel|m[oó]vil|tel)/i', $k)) {
                      $val = $u->getAttribute($k);
                      if (!empty($val)) {
                        $celStr = trim((string) $val);
                        break;
                      }
                    }
                  }
                }
              @endphp
              <tr>
                <td class="mono" data-label="#">@if($row < 10)&nbsp;@endif{{ $row }}</td>
                <td data-label="Jugador">{{ e(optional($u)->name ?? '—') }}</td>

                @if($mostrarColTelefono)
                  <td data-label="Celular">
                    @if($celStr)
                      <span class="copyable mono" role="button" tabindex="0" title="Tocar para copiar"
                            aria-label="Copiar celular" data-copy="{{ e($celStr) }}">{{ e($celStr) }}</span>
                    @else
                      <span class="muted-sm">—</span>
                    @endif
                  </td>
                @endif

                <td class="mono" data-label="Votó">{{ $voto ?? '—' }}</td>
              </tr>
            @empty
              <tr>
                <td colspan="{{ $totalCols }}" class="muted-sm">No hay jugadores aún.</td>
              </tr>
            @endforelse
          </tbody>
        </table>
      </div>
      {{-- ======= /TABLA ======= --}}
    </section>

    @includeWhen(true, 'mesas._show_aside', ['mesa' => $mesa, 'efectivamenteAbierta' => $efectivamenteAbierta])
  </div>
@endsection

@push('scripts')
  {{-- Countdown liviano (corrige desfase con meta server-now-utc-ms) --}}
  <script>
  (function(){
    const meta = document.querySelector('meta[name="server-now-utc-ms"]');
    const serverNow = meta ? +meta.content : Date.now();
    const startClient = Date.now();
    const skew = startClient - serverNow;

    function fmt(ms){
      if (ms <= 0) return '¡Ya abrió!';
      const s = Math.floor(ms / 1000);
      const d = Math.floor(s / 86400);
      const h = Math.floor((s % 86400) / 3600);
      const m = Math.floor((s % 3600) / 60);
      const ss = s % 60;
      if (d > 0) return `${d}d ${String(h).padStart(2,'0')}:${String(m).padStart(2,'0')}:${String(ss).padStart(2,'0')}`;
      return `${String(h).padStart(2,'0')}:${String(m).padStart(2,'0')}:${String(ss).padStart(2,'0')}`;
    }

    function tickOne(timeEl){
      const ts = +timeEl.getAttribute('data-countdown-ts');
      const label = timeEl.parentElement?.querySelector('[data-countdown-label]');
      if (!ts || !label) return true;

      const now = Date.now() - skew;
      const left = ts - now;
      label.textContent = fmt(left);
      return left <= 0;
    }

    function tickAll(){
      let doneAll = true;
      document.querySelectorAll('time[data-countdown-ts]').forEach(t => {
        const done = tickOne(t);
        if (!done) doneAll = false;
      });
      if (!doneAll) setTimeout(tickAll, 1000);
    }

    document.addEventListener('DOMContentLoaded', tickAll);
  })();
  </script>

  {{-- Copiar a portapapeles para .copyable --}}
  <script>
    (function () {
      function copyText(text) {
        if (!text) return Promise.reject();
        if (navigator.clipboard?.writeText) { try { return navigator.clipboard.writeText(text); } catch (e) {} }
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
        setTimeout(function(){ el.classList.remove('copied'); }, 1200);
      }
      document.addEventListener('click', function (ev) {
        var el = ev.target.closest('.copyable'); if (!el) return;
        ev.preventDefault();
        var t = (el.getAttribute('data-copy') || el.textContent || '').trim();
        copyText(t).then(function(){ markCopied(el); });
      }, false);
      document.addEventListener('keydown', function (ev) {
        if (ev.key !== 'Enter' && ev.key !== ' ') return;
        var el = ev.target.closest('.copyable'); if (!el) return;
        ev.preventDefault();
        var t = (el.getAttribute('data-copy') || el.textContent || '').trim();
        copyText(t).then(function(){ markCopied(el); });
      }, false);
    })();
  </script>
@endpush
