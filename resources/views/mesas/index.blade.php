{{-- resources/views/mesas/index.blade.php --}}
@extends('layouts.app')
@section('title', 'Mesas')

@section('content')
  <div class="toolbar">
    <h1 style="margin:0">Mesas</h1>

    @can('create', \App\Models\Mesa::class)
      @if(\Illuminate\Support\Facades\Route::has('mesas.create'))
        <a class="btn"
           href="{{ route('mesas.create') }}"
           aria-label="Crear nueva mesa">➕ Nueva</a>
      @endif
    @endcan
  </div>

  @php
    // Colección de la página actual (funciona con Paginator o con array/collection)
    $collection = method_exists($tables ?? null, 'getCollection')
      ? $tables->getCollection()
      : collect($tables ?? []);

    // Agrupaciones (no requieren $jornada)
    $sinApartado = $collection->filter(fn($m) => !trim((string) optional($m->apartado)->titulo))->values();
    $porApartado = $collection
      ->filter(fn($m) => trim((string) optional($m->apartado)->titulo))
      ->groupBy(fn($m) => trim((string) optional($m->apartado)->titulo))
      ->sortKeys(\SORT_NATURAL | \SORT_FLAG_CASE);

    // Helper mayúsculas
    $toUpper = function (string $s): string {
      return function_exists('mb_strtoupper') ? mb_strtoupper($s, 'UTF-8') : strtoupper($s);
    };

    // ¿soy manager en la jornada actual? (solo si viene $jornada)
    $soyManagerEnJornada = false;
    if (auth()->check() && !empty($jornada?->id)) {
      $soyManagerEnJornada = \App\Models\Mesa::query()
        ->where('jornada_id', $jornada->id)
        ->where('manager_id', auth()->id())
        ->exists();
    }

    // ─────────────────────────────────────────────────────────────
    // Traemos **una sola vez** las inscripciones confirmadas del usuario
    // en la jornada actual para bloquear otras mesas del MISMO apartado.
    // ─────────────────────────────────────────────────────────────
    $misMesaIds   = [];
    $misApartados = []; // array de claves de apartado normalizadas: 'null' o '<id>'
    if (auth()->check() && !empty($jornada?->id)) {
      $rows = \App\Models\Inscripcion::query()
        ->join('mesas as mm', 'mm.id', '=', 'inscripciones.mesa_id')
        ->where('inscripciones.user_id', auth()->id())
        ->where('inscripciones.is_waiting', false)
        ->where('mm.jornada_id', $jornada->id)
        ->get(['inscripciones.mesa_id', 'mm.jornada_apartado_id']);

      $misMesaIds = $rows->pluck('mesa_id')->all();
      $misApartados = $rows->map(function ($r) {
          return is_null($r->jornada_apartado_id) ? 'null' : (string) $r->jornada_apartado_id;
        })->unique()->values()->all();
    }
  @endphp

  @if(($tables ?? null) && ($collection->count() > 0))
    {{-- Sin apartado --}}
    @if($sinApartado->count() > 0)
      <h3 class="h3">General</h3>
      <section class="cards" aria-label="Mesas generales">
        @foreach($sinApartado as $t)
          @include('mesas._card', [
            't' => $t,
            'soyManagerEnJornada' => $soyManagerEnJornada,
            'misMesaIds' => $misMesaIds,        // para saber si ya estoy en ESTA mesa
            'misApartados' => $misApartados,    // para bloquear otras mesas del mismo apartado
          ])
        @endforeach
      </section>
    @endif

    {{-- Agrupadas por apartado --}}
    @foreach($porApartado as $tituloApartado => $grupo)
      <h3 class="h3">{{ $toUpper((string) $tituloApartado) }}</h3>
      <section class="cards" aria-label="Mesas del apartado {{ e($tituloApartado) }}">
        @foreach($grupo as $t)
          @include('mesas._card', [
            't' => $t,
            'soyManagerEnJornada' => $soyManagerEnJornada,
            'misMesaIds' => $misMesaIds,
            'misApartados' => $misApartados,
          ])
        @endforeach
      </section>
    @endforeach

    {{-- Paginación --}}
    @if(method_exists($tables, 'links'))
      <div class="mt-sm">{{ $tables->links() }}</div>
    @endif
  @else
    <div class="card" role="status">No hay mesas disponibles.</div>
  @endif
@endsection
