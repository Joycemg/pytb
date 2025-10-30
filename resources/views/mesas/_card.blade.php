{{-- resources/views/mesas/_card.blade.php --}}
@php
    $button = $card['button'] ?? [];
    $countdown = $card['countdown'] ?? ['visible' => false];
    $apartadoTitle = $card['apartadoTitle'] ?? '';
    $isFull = (bool) ($card['isFull'] ?? false);
    $isOpen = (bool) ($card['isOpen'] ?? false);
    $capacity = $card['capacity'] ?? 0;
    $title = $card['title'] ?? '';
    $image = $card['image'] ?? null;
@endphp

<div class="card surface" style="overflow:hidden">
  {{-- Media + overlay (ALTA y ligera) --}}
  <a class="fill clickable" href="{{ $card['showUrl'] ?? '#' }}" aria-label="Abrir mesa {{ e($title) }}">
    <div class="card-media cover" style="position:relative; height:clamp(190px, 38vw, 300px);">
      @if($image)
        <img
          src="{{ $image }}"
          alt="Imagen de {{ e($title) }}"
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
          {{ e($title) }}
        </div>
        @if($apartadoTitle !== '')
          <span class="badge" style="background:rgba(255,255,255,.15);border-color:rgba(255,255,255,.35)">
            {{ e($apartadoTitle) }}
          </span>
        @endif
      </div>

      {{-- Chips de estado --}}
      <div style="position:absolute; top:.55rem; left:.55rem; display:flex; gap:.35rem; align-items:center;">
        <span class="badge" style="background:#fff;border-color:#fff;color:#333">
          {{ $isOpen ? 'Abierta' : 'Cerrada' }}
        </span>
        <span class="badge" style="background:#fff;border-color:#fff;color:#333">
          Cap: {{ $capacity }}
        </span>
        @if($isFull)
          <span class="badge" style="background:#ffefef;border-color:#ffd5d5;color:#a33">Llena</span>
        @endif
      </div>
    </div>
  </a>

  <div class="card-body" style="display:flex; flex-direction:column; gap:.6rem">
    {{-- Metas breves --}}
    <div class="muted-sm" style="display:flex; flex-wrap:wrap; gap:.5rem .75rem; align-items:center">
      @if(!empty($card['singleVote']))
        <span class="badge">Voto único</span>
      @endif
      @if(!empty($card['opensAtLabel']))
        <span>Abre {{ $card['opensAtLabel'] }}</span>
      @endif
    </div>

    {{-- Apertura con countdown (solo fecha/hora; SIN zona horaria visible) --}}
    @if(!empty($countdown['visible']))
      <div class="muted-sm" aria-live="polite" style="
        display:flex; align-items:center; gap:.45rem;
        padding:.45rem .6rem; border:1px dashed var(--line); border-radius:8px; background:#fcfcfd;
      ">
        <span aria-hidden="true">⏳</span>
        <div>
          <div style="font-weight:600; font-size:.92rem">{{ __('Apertura') }}</div>
          <div style="font-variant-numeric:tabular-nums">
            <time
              datetime="{{ $countdown['iso'] ?? '' }}"
              @if(!empty($countdown['timestamp'])) data-countdown-ts="{{ $countdown['timestamp'] }}" @endif>
              {{ $countdown['label'] ?? '' }}
            </time>
            · <span class="count" data-countdown-label>—</span>
          </div>
        </div>
      </div>
    @endif

    {{-- CTA: oculto a manager; deshabilitado por horario / llena / ya inscripto en otra mesa del mismo apartado --}}
    @if(empty($isGuest))
      @if(!empty($button['show']) && !empty($card['signupUrl']))
        <form method="post" action="{{ $card['signupUrl'] }}" style="margin-top:.1rem">
          @csrf
          <button
            type="submit"
            class="btn {{ !empty($button['disabled']) ? 'is-disabled' : '' }}"
            style="width:100%"
            title="{{ $button['title'] ?? 'Inscribirme' }}"
            data-enabled-title="Inscribirme"
            @if(!empty($button['disabled'])) disabled aria-disabled="true" @endif
            {{-- Auto-activación SOLO por horario (no si está llena o ya inscripto en otra mesa) --}}
            @if(!empty($button['autoActivateAt']) && empty($button['disabled'])) data-activate-at-utc="{{ $button['autoActivateAt'] }}" @endif
          >
            {{ $button['label'] ?? 'Inscribirme' }}
          </button>
        </form>
      @endif
    @else
      <a class="btn line" href="{{ route('auth.login') }}" style="width:100%">Ingresar para inscribirme</a>
    @endif
  </div>
</div>
