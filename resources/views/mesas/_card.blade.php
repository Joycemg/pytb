{{-- resources/views/mesas/_card.blade.php --}}
@php
    use Illuminate\Support\Facades\Route as LRoute;
    use Carbon\Carbon;

    /** ───── Valores precomputados (sin hits extra a DB) ───── */
    // Imagen segura
    $img = method_exists($t, 'getImageSrcAttribute') ? $t->image_src
        : ($t->image_path ? asset('storage/' . ltrim($t->image_path, '/')) : ($t->image_url ?? null));

    // TZ solo para cálculo; NO se muestra
    $tz = config('app.display_timezone', config('app.timezone', 'America/Argentina/La_Rioja'));

    // Estado básico (sin consultas)
    $isOpen  = method_exists($t, 'isEffectivelyOpen') ? (bool) $t->isEffectivelyOpen() : (bool) ($t->is_open ?? false);
    $apTitle = trim((string) optional($t->apartado)->titulo);
    $cap     = (int) ($t->capacity ?? 0);

    // Rutas
    $routeShow   = LRoute::has('mesas.show') ? route('mesas.show', $t) : '#';
    $routeSignup = LRoute::has('mesas.signup') ? route('mesas.signup', $t)
                   : (LRoute::has('inscripciones.store') ? route('inscripciones.store', $t) : null);

    // Apertura (prefiere inscripciones_abren_at si existe)
    $apertura = $t->inscripciones_abren_at ?? $t->opens_at ?? null;
    $aperturaLocal = $apertura ? Carbon::parse($apertura, $tz) : null;
    $opensTs       = $aperturaLocal ? $aperturaLocal->copy()->utc()->getTimestampMs() : null;
    $yaAbre        = $aperturaLocal ? now($tz)->greaterThanOrEqualTo($aperturaLocal) : true;

    // Capacidad efectiva aproximada (sin query): descuenta manager si cuenta
    $capEf = max(0, $cap - ((bool)($t->manager_counts_as_player ?? false) ? 1 : 0));
    $confirmadas = isset($t->confirmadas_count) ? (int) $t->confirmadas_count : -1; // -1 = desconocido
    $estaLlena   = ($confirmadas >= 0) ? ($confirmadas >= $capEf) : false;

    // Claves recibidas del index (si no vienen, arrays vacíos)
    $misMesaIds   = $misMesaIds   ?? [];
    $misApartados = $misApartados ?? [];

    // Normalizo la clave de apartado de ESTA mesa
    $apartadoKey = is_null($t->jornada_apartado_id) ? 'null' : (string) $t->jornada_apartado_id;

    // ¿Ya estoy inscripto en esta misma mesa?
    $yoEnEsta = in_array($t->id, $misMesaIds, true);

    // ¿Ya tengo una inscripción confirmada en el MISMO apartado de la jornada (y no es esta mesa)?
    $yaEnApartado = (!$yoEnEsta) && in_array($apartadoKey, $misApartados, true);

    // Reglas de bloqueo livianas
    $bloqueoHorario = (bool) $apertura && !$yaAbre;
    $bloqueoOtraMesa = $yaEnApartado;
    $bloqueoLlena = $estaLlena;
    $disabled = $bloqueoHorario || $bloqueoOtraMesa || $bloqueoLlena;

    // Ocultar botón a manager de la jornada (lo pasa el index)
    $esManagerEnJornada = isset($soyManagerEnJornada) ? (bool) $soyManagerEnJornada : false;

    // Tooltip del botón (sin mostrar zona horaria)
    $titleBtn = $bloqueoLlena   ? 'Mesa llena'
             : ($bloqueoOtraMesa? 'Ya estás inscripto en otra mesa de este turno'
             : ($bloqueoHorario ? ('Disponible: ' . $aperturaLocal?->isoFormat('DD/MM/YYYY HH:mm'))
             : ($yoEnEsta ? 'Ya estás inscripto en esta mesa' : 'Inscribirme')));
@endphp

<div class="card surface" style="overflow:hidden">
  {{-- Media + overlay (ALTA y ligera) --}}
  <a class="fill clickable" href="{{ $routeShow }}" aria-label="Abrir mesa {{ e($t->title) }}">
    <div class="card-media cover" style="position:relative; height:clamp(190px, 38vw, 300px);">
      @if($img)
        <img
          src="{{ $img }}"
          alt="Imagen de {{ e($t->title) }}"
          loading="lazy" decoding="async" referrerpolicy="no-referrer"
          style="width:100%;height:100%;object-fit:cover"
        >
      @else
        <div style="display:grid;place-items:center;width:100%;height:100%;color:var(--muted);background:#fff">
          <span>Sin imagen</span>
        </div>
      @endif

      {{-- Overlay: título + badge de apartado --}}
      <div style="
        position:absolute; inset:auto 0 .35rem 0;
        display:flex; flex-wrap:wrap; gap:.35rem .5rem; align-items:center;
        padding:.55rem .75rem; color:#fff;
        background:linear-gradient(180deg,rgba(0,0,0,0) 0%,rgba(0,0,0,.45) 40%,rgba(0,0,0,.55) 100%);
      ">
        <div style="font-weight:700; font-size:1.02rem; text-shadow:0 1px 2px rgba(0,0,0,.45)">
          {{ e($t->title) }}
        </div>
        @if($apTitle !== '')
          <span class="badge" style="background:rgba(255,255,255,.15);border-color:rgba(255,255,255,.35)">
            {{ e($apTitle) }}
          </span>
        @endif
      </div>

      {{-- Chips de estado --}}
      <div style="position:absolute; top:.55rem; left:.55rem; display:flex; gap:.35rem; align-items:center;">
        <span class="badge" style="background:#fff;border-color:#fff;color:#333">
          {{ $isOpen ? 'Abierta' : 'Cerrada' }}
        </span>
        <span class="badge" style="background:#fff;border-color:#fff;color:#333">
          Cap: {{ $cap }}
        </span>
        @if($estaLlena)
          <span class="badge" style="background:#ffefef;border-color:#ffd5d5;color:#a33">Llena</span>
        @endif
      </div>
    </div>
  </a>

  <div class="card-body" style="display:flex; flex-direction:column; gap:.6rem">
    {{-- Metas breves --}}
    <div class="muted-sm" style="display:flex; flex-wrap:wrap; gap:.5rem .75rem; align-items:center">
      @if(!empty($t->single_vote))
        <span class="badge">Voto único</span>
      @endif
      @if(!empty($t->opens_at))
        <span>Abre {{ \Carbon\Carbon::parse($t->opens_at, $tz)->format('d/m H:i') }}</span>
      @endif
    </div>

    {{-- Apertura con countdown (solo fecha/hora; SIN zona horaria visible) --}}
    @if($aperturaLocal)
      <div class="muted-sm" aria-live="polite" style="
        display:flex; align-items:center; gap:.45rem;
        padding:.45rem .6rem; border:1px dashed var(--line); border-radius:8px; background:#fcfcfd;
      ">
        <span aria-hidden="true">⏳</span>
        <div>
          <div style="font-weight:600; font-size:.92rem">{{ __('Apertura') }}</div>
          <div style="font-variant-numeric:tabular-nums">
            <time
              datetime="{{ $aperturaLocal->toAtomString() }}"
              data-countdown-ts="{{ $opensTs }}">
              {{ $aperturaLocal->format('Y-m-d H:i') }}
            </time>
            · <span class="count" data-countdown-label>—</span>
          </div>
        </div>
      </div>
    @endif

    {{-- CTA: oculto a manager; deshabilitado por horario / llena / ya inscripto en otra mesa del mismo apartado --}}
    @auth
      @if($routeSignup && !$esManagerEnJornada)
        <form method="post" action="{{ $routeSignup }}" style="margin-top:.1rem">
          @csrf
          <button
            type="submit"
            class="btn {{ $disabled || $yoEnEsta ? 'is-disabled' : '' }}"
            style="width:100%"
            title="{{ $titleBtn }}"
            data-enabled-title="Inscribirme"
            @if($disabled || $yoEnEsta) disabled aria-disabled="true" @endif
            {{-- Auto-activación SOLO por horario (no si está llena o ya inscripto en otra mesa) --}}
            @if(!$bloqueoLlena && !$bloqueoOtraMesa && $bloqueoHorario && $opensTs) data-activate-at-utc="{{ $opensTs }}" @endif
          >
            @if($yoEnEsta) Ya estás inscripto
            @elseif($bloqueoLlena) Mesa llena
            @elseif($bloqueoOtraMesa) Ya estás inscripto en otra mesa de este turno
            @elseif($bloqueoHorario) Abre pronto
            @else Inscribirme
            @endif
          </button>
        </form>
      @endif
    @else
      <a class="btn line" href="{{ route('auth.login') }}" style="width:100%">Ingresar para inscribirme</a>
    @endauth
  </div>
</div>
