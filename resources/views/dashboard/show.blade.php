{{-- resources/views/dashboard/index.blade.php (o resources/views/panel.blade.php) --}}
@extends('layouts.app')
@section('title', 'Mi panel · ' . config('app.name', 'La Taberna'))

@section('content')
  @php
    // Usar relaciones precargadas por el controlador; si no, cargar liviano.
    $ins = $user->relationLoaded('inscripciones')
      ? $user->inscripciones
      : $user->inscripciones()
        ->select(['id', 'mesa_id', 'user_id', 'is_waiting', 'moderated_at', 'created_at'])
        ->orderBy('is_waiting')       // confirmadas primero
        ->orderByDesc('created_at')
        ->limit(10)
        ->with(['mesa:id,title'])
        ->get();

    $evs = $user->relationLoaded('eventosHonor')
      ? $user->eventosHonor
      : $user->eventosHonor()
        ->select(['id', 'user_id', 'mesa_id', 'slug', 'delta', 'occurred_at'])
        ->latest('occurred_at')->orderByDesc('id')
        ->limit(20)
        ->get();
  @endphp

  <h1>Hola, {{ e($user->name) }}</h1>

  <div class="grid grid-2">
    <section class="card">
      <h3>Actividad reciente</h3>

      @if($ins->count())
        <ul>
          @foreach($ins as $s)
            @php
              $mesaTitulo = e(optional($s->mesa)->title ?? '—');
              // Estado legible con columnas reales del modelo Inscripcion
              $estado = $s->is_waiting
                ? 'Lista de espera'
                : ($s->moderated_at ? 'Moderada' : 'Confirmada (pendiente de moderación)');
            @endphp
            <li>
              {{ $mesaTitulo }} — {{ $estado }}
            </li>
          @endforeach
        </ul>
      @else
        <p class="muted">Sin actividad reciente.</p>
      @endif
    </section>

    <section class="card">
      <h3>Honor</h3>
      <p>Puntos totales: <strong>{{ (int) $user->honor_total }}</strong></p>

      @if($evs->count())
        <ul>
          @foreach($evs as $e)
            <li>
              {{ optional($e->occurred_at)->format('Y-m-d') ?? '—' }}
              · {{ e($e->reason ?? $e->slug) }}
              · {{ $e->delta > 0 ? '+' : '' }}{{ (int) $e->delta }}
            </li>
          @endforeach
        </ul>
      @else
        <p class="muted">Sin eventos de honor todavía.</p>
      @endif




    </section>
  </div>
@endsection