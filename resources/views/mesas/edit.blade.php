{{-- resources/views/mesas/edit.blade.php --}}
@extends('layouts.app')
@section('title', 'Editar mesa')

@push('head')
  <style>
    /* Forzar checkbox nativo (Hostinger, navegadores viejos) */
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
    /** @var \App\Models\Mesa $mesa */
    $user = auth()->user();
    $esAdminMod = $user && method_exists($user,'hasAnyRole') ? $user->hasAnyRole(['admin','moderator']) : false;

    // Cargar apartados activos de la jornada (consulta única y acotada)
    try {
        $apartados = \App\Models\JornadaApartado::query()
          ->where('jornada_id', (int) ($mesa->jornada_id ?? 0))
          ->where('activo', true)
          ->orderBy('orden')->orderBy('id')
          ->get(['id','titulo']);
    } catch (\Throwable $e) {
        $apartados = collect(); // fallback seguro si algo falla
    }

    $tz = (string) config('app.timezone', 'UTC');
    $opensHuman = $mesa->opens_at ? $mesa->opens_at->timezone($tz)->format('d/m/Y H:i') : '';
  @endphp

  <h1>Editar mesa</h1>

  <form class="card"
        method="post"
        action="{{ route('mesas.update', $mesa) }}"
        enctype="multipart/form-data"
        autocomplete="off"
        novalidate>
    @csrf @method('PUT')

    <div>
      <label for="title">Título</label>
      <input id="title"
             name="title"
             required
             maxlength="120"
             value="{{ old('title', (string) $mesa->title) }}"
             autocomplete="off">
      @error('title')<div class="flash" role="alert">⚠️ {{ $message }}</div>@enderror
    </div>

    <div>
      <label for="description">Descripción</label>
      <textarea id="description"
                name="description"
                rows="5"
                maxlength="2000">{{ old('description', (string) $mesa->description) }}</textarea>
      @error('description')<div class="flash" role="alert">⚠️ {{ $message }}</div>@enderror
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
               value="{{ (int) old('capacity', (int) $mesa->capacity) }}">
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
                 {{ old('manager_counts_as_player', $mesa->manager_counts_as_player) ? 'checked' : '' }}>
          <span>Sí</span>
        </label>
        @error('manager_counts_as_player')<div class="flash" role="alert">⚠️ {{ $message }}</div>@enderror
      </div>

      <div>
        <label for="opens_at_txt">Fecha y hora de apertura (referencia)</label>
        <input id="opens_at_txt"
               type="text"
               value="{{ $opensHuman }}"
               disabled
               aria-disabled="true">
        <p class="help">Este valor se define al crear o desde administración.</p>
      </div>
    </div>

    @if($esAdminMod)
      {{-- Admin / Moderador: puede cambiar manager y apartado --}}
      <div style="margin-top:.6rem">
        <x-manager-picker name="manager_id"
                          :selected="old('manager_id', (string) ($mesa->manager_id ?? ''))"
                          :required="true"
                          label="Manager (obligatorio)" />
        @error('manager_id')<div class="flash" role="alert">⚠️ {{ $message }}</div>@enderror
      </div>

      <div style="margin-top:.6rem">
        <label for="jornada_apartado_id">Apartado (opcional)</label>
        <select id="jornada_apartado_id" name="jornada_apartado_id">
          <option value="">— Sin apartado —</option>
          @foreach($apartados as $a)
            <option value="{{ (int) $a->id }}"
              {{ (string) old('jornada_apartado_id', (string) ($mesa->jornada_apartado_id ?? '')) === (string) $a->id ? 'selected' : '' }}>
              {{ $a->titulo }}
            </option>
          @endforeach
        </select>
        <p class="help">Cambiá el apartado si esta mesa pertenece a otro turno/segmento.</p>
        @error('jornada_apartado_id')<div class="flash" role="alert">⚠️ {{ $message }}</div>@enderror
      </div>
    @else
      {{-- Otros roles: solo lectura --}}
      <div class="muted-sm" style="margin-top:.4rem">
        Manager actual: <strong>{{ e(optional($mesa->manager)->name ?? '—') }}</strong><br>
        Apartado: <strong>{{ e(optional($mesa->apartado)->titulo ?? '—') }}</strong>
      </div>
    @endif

    <div style="margin-top:.6rem">
      <label for="image">Imagen (JPG/PNG, máx 1MB)</label>
      <input id="image" type="file" name="image" accept="image/jpeg,image/png">
      @error('image')<div class="flash" role="alert">⚠️ {{ $message }}</div>@enderror

      @if(!empty($mesa->image_path))
        <div class="muted-sm" style="margin-top:.35rem">Actual:</div>
        <img class="square"
             src="{{ asset('storage/' . ltrim($mesa->image_path, '/')) }}"
             alt="Imagen actual de la mesa"
             style="max-width:140px">
      @endif
    </div>

    <div>
      <label for="image_url">URL de imagen (opcional)</label>
      <input id="image_url"
             name="image_url"
             inputmode="url"
             maxlength="300"
             value="{{ old('image_url', (string) $mesa->image_url) }}"
             autocomplete="off">
      <p class="help">Si subís archivo, se ignora esta URL (el backend ya valida que no uses ambas).</p>
      @error('image_url')<div class="flash" role="alert">⚠️ {{ $message }}</div>@enderror
    </div>

    <div class="toolbar">
      <button class="btn" type="submit" data-once data-delay="300">Guardar</button>
      <a class="btn ghost" href="{{ route('mesas.show', $mesa) }}">Volver</a>
    </div>
  </form>

  @can('close', $mesa)
    {{-- Abrir/Cerrar en form separado (evita mezclar con edición normal) --}}
    <form method="post"
          action="{{ route('mesas.update', $mesa) }}"
          class="card"
          style="margin-top:1rem">
      @csrf @method('PUT')
      <input type="hidden" name="toggle_open" value="1">
      <input type="hidden" name="is_open" value="{{ $mesa->is_open ? 0 : 1 }}">
      <div class="toolbar">
        <button class="btn" data-once data-delay="200">
          {{ $mesa->is_open ? 'Cerrar mesa' : 'Abrir mesa' }}
        </button>
      </div>
    </form>
  @endcan
@endsection
