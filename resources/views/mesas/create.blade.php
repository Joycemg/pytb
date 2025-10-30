{{-- resources/views/mesas/create.blade.php --}}
@extends('layouts.app')
@section('title', 'Nueva mesa')

@push('head')
  <style>
    /* Forzar checkbox nativo (compatibilidad en hosting compartido) */
    input[type="checkbox"]{
      appearance:checkbox!important;-webkit-appearance:checkbox!important;-moz-appearance:checkbox!important;
      width:auto!important;height:auto!important;padding:0!important;border:none!important;background:transparent!important;vertical-align:middle
    }
    label.chk{display:inline-flex;gap:.6rem;align-items:center;cursor:pointer}
    .muted-sm{color:var(--muted);font-size:.9rem}
    .help{color:var(--muted);font-size:.88rem;margin:.25rem 0 0}
    .grid-auto{display:grid;gap:.8rem;grid-template-columns:repeat(auto-fit,minmax(220px,1fr))}
    .toolbar{display:flex;gap:.6rem;flex-wrap:wrap;align-items:center;margin-top:.6rem}
  </style>
@endpush

@section('content')
  @php
    /** @var \App\Models\Jornada $jornada */
    // Turnos/apartados activos de la jornada actual (consulta acotada)
    try {
      $apartados = \App\Models\JornadaApartado::query()
        ->where('jornada_id', (int) ($jornada->id ?? 0))
        ->where('activo', true)
        ->orderBy('orden')->orderBy('id')
        ->get(['id','titulo']);
    } catch (\Throwable $e) {
      $apartados = collect(); // fallback seguro si la DB no responde
    }
  @endphp

  <h1>Nueva mesa</h1>

  <form class="card"
        method="post"
        action="{{ route('mesas.store') }}"
        enctype="multipart/form-data"
        autocomplete="off"
        novalidate>
    @csrf

    <div>
      <label for="title">Título</label>
      <input id="title"
             name="title"
             required
             maxlength="120"
             value="{{ old('title','') }}"
             autocomplete="off"
             autofocus>
      @error('title')<div class="flash" role="alert">⚠️ {{ $message }}</div>@enderror
    </div>

    <div>
      <label for="description">Descripción</label>
      <textarea id="description"
                name="description"
                rows="5"
                maxlength="2000"
                placeholder="Detalles, reglas, etc.">{{ old('description','') }}</textarea>
      @error('description')<div class="flash" role="alert">⚠️ {{ $message }}</div>@enderror
    </div>

    {{-- Turno / apartado (opcional) --}}
    <div>
      <label for="jornada_apartado_id">Turno (opcional)</label>
      <select id="jornada_apartado_id" name="jornada_apartado_id">
        <option value="">— Sin turno —</option>
        @foreach($apartados as $a)
          <option value="{{ (int) $a->id }}"
            {{ (string) old('jornada_apartado_id','') === (string) $a->id ? 'selected' : '' }}>
            {{ $a->titulo }}
          </option>
        @endforeach
      </select>
      <p class="help">Ej.: “Turno noche” habilita votar además del turno principal.</p>
      @error('jornada_apartado_id')<div class="flash" role="alert">⚠️ {{ $message }}</div>@enderror
    </div>

    <div class="grid-auto">
      <div>
        <label for="capacity">Capacidad</label>
        <input id="capacity"
               type="number"
               name="capacity"
               min="1"
               max="100"
               required
               inputmode="numeric"
               value="{{ (int) old('capacity', 6) }}">
        @error('capacity')<div class="flash" role="alert">⚠️ {{ $message }}</div>@enderror
      </div>

      <div>
        <span class="muted-sm">Manager cuenta como jugador</span>
        <input type="hidden" name="manager_counts_as_player" value="0">
        <label class="chk" style="margin-top:.35rem" for="manager_counts_as_player_chk">
          <input id="manager_counts_as_player_chk"
                 type="checkbox"
                 name="manager_counts_as_player"
                 value="1"
                 {{ old('manager_counts_as_player') ? 'checked' : '' }}>
          <span>Sí</span>
        </label>
        @error('manager_counts_as_player')<div class="flash" role="alert">⚠️ {{ $message }}</div>@enderror
      </div>

      <div>
        <label for="opens_at">Fecha y hora de apertura (opcional)</label>
        <input id="opens_at"
               type="datetime-local"
               name="opens_at"
               value="{{ old('opens_at','') }}">
        @error('opens_at')<div class="flash" role="alert">⚠️ {{ $message }}</div>@enderror
      </div>
    </div>

    {{-- Manager OBLIGATORIO --}}
    <div style="margin-top:.6rem">
      <x-manager-picker
        name="manager_id"
        :selected="old('manager_id')"
        :required="true"
        label="Manager (obligatorio)" />
      @error('manager_id')<div class="flash" role="alert">⚠️ {{ $message }}</div>@enderror
    </div>

    {{-- Voto único no se muestra: el controlador lo fuerza a true --}}

    <div style="margin-top:.6rem">
      <label for="image">Imagen (JPG/PNG, máx 1MB)</label>
      <input id="image"
             type="file"
             name="image"
             accept="image/jpeg,image/png">
      @error('image')<div class="flash" role="alert">⚠️ {{ $message }}</div>@enderror
    </div>

    <div>
      <label for="image_url">URL de imagen (opcional)</label>
      <input id="image_url"
             name="image_url"
             value="{{ old('image_url','') }}"
             inputmode="url"
             maxlength="300"
             autocomplete="off"
             spellcheck="false">
      <p class="help">Si subís archivo, se ignora esta URL (el backend valida no usar ambas).</p>
      @error('image_url')<div class="flash" role="alert">⚠️ {{ $message }}</div>@enderror
    </div>

    <div class="toolbar">
      <button class="btn" type="submit" data-once data-delay="300">Crear</button>
      <a class="btn ghost" href="{{ route('mesas.index') }}">Cancelar</a>
    </div>
  </form>
@endsection
