{{-- resources/views/ranking/index.blade.php --}}
@extends('layouts.app')
@section('title', 'Ranking de honor')

@section('content')
  <h1 class="m-0 mb-md">Ranking de honor</h1>

  <div class="tbl-wrap">
    <table class="tbl ranking-table"
           role="table"
           aria-label="Tabla de ranking de honor">
      <caption class="visually-hidden">Posiciones del ranking de honor</caption>
      <thead>
        <tr>
          <th scope="col"
              class="col-idx">#</th>
          <th scope="col">Usuario</th>
          <th scope="col"
              class="muted col-username">Username</th>
          <th scope="col"
              class="col-honor">Honor</th>
        </tr>
      </thead>
      <tbody>
        @php
          $start = method_exists($users, 'firstItem') ? (int) ($users->firstItem() ?? 1) : 1;
          $meId = optional(auth()->user())->id;
        @endphp

        @forelse($users as $idx => $u)
          @php
            $pos = $start + (int) $idx;
            $soyYo = $meId && ((int) $u->id === (int) $meId);

            $rowClass = '';
            if ($pos === 1)
              $rowClass = 'top-1';
            elseif ($pos === 2)
              $rowClass = 'top-2';
            elseif ($pos === 3)
              $rowClass = 'top-3';
          @endphp

          <tr @if($soyYo) id="yo" @endif
              class="{{ trim(($soyYo ? 'fila-yo ' : '') . $rowClass) }}"
              @if($soyYo) aria-current="true" aria-label="Tu posición actual en el ranking" @endif>
            <td class="col-idx">
              @if($pos === 1)
                <span class="rank-badge rank-1"
                      aria-label="1er puesto">1°</span>
              @elseif($pos === 2)
                <span class="rank-badge rank-2"
                      aria-label="2do puesto">2°</span>
              @elseif($pos === 3)
                <span class="rank-badge rank-3"
                      aria-label="3er puesto">3°</span>
              @else
                <span class="mono">{{ $pos }}</span>
              @endif
            </td>
            <td>{{ e((string) $u->name) }}</td>
            <td class="muted col-username">
              @if(!empty($u->username))
                {{ '@' . e((string) $u->username) }}
              @else
                —
              @endif
            </td>
            <td class="col-honor mono"><strong>{{ number_format((int) ($u->honor_total ?? 0), 0, ',', '.') }}</strong></td>
          </tr>
        @empty
          <tr>
            <td colspan="4"
                class="muted">No hay datos para mostrar.</td>
          </tr>
        @endforelse
      </tbody>
    </table>
  </div>

  @if(method_exists($users, 'links'))
    <div class="mt-sm">
      {{ $users->links() }}
    </div>
  @endif
@endsection

@push('scripts')
  <script>
    document.addEventListener('DOMContentLoaded', function () {
      var yo = document.getElementById('yo');
      if (yo && yo.scrollIntoView) yo.scrollIntoView({ block: 'center' });
    });
  </script>
@endpush
