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

  @if($hasTables ?? false)
    @if(!empty($sinApartadoCards))
      <h3 class="h3">General</h3>
      <section class="cards" aria-label="Mesas generales">
        @foreach($sinApartadoCards as $card)
          @include('mesas._card', ['card' => $card, 'isGuest' => $isGuest])
        @endforeach
      </section>
    @endif

    @foreach($porApartadoCards as $grupo)
      <h3 class="h3">{{ $grupo['titleUpper'] }}</h3>
      <section class="cards" aria-label="Mesas del apartado {{ e($grupo['title']) }}">
        @foreach($grupo['cards'] as $card)
          @include('mesas._card', ['card' => $card, 'isGuest' => $isGuest])
        @endforeach
      </section>
    @endforeach

    @if(isset($tables) && method_exists($tables, 'links'))
      <div class="mt-sm">{{ $tables->links() }}</div>
    @endif
  @else
    <div class="card" role="status">No hay mesas disponibles.</div>
  @endif
@endsection
