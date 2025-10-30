{{-- resources/views/ranking/index.blade.php --}}
@extends('layouts.app')
@section('title', 'Ranking de honor')

@push('head')
  <style>
    /* Tabla base (ligera) */
    table {
      width: 100%;
      border-collapse: collapse;
    }

    th,
    td {
      padding: .55rem .6rem;
      border-bottom: 1px solid var(--line);
    }

    thead th {
      text-align: left;
      position: sticky;
      top: 0;
      background: #fafafa;
      z-index: 1;
    }

    /* Números tabulares: mejor alineación */
    .mono {
      font-variant-numeric: tabular-nums;
      font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
    }

    .col-idx {
      width: 64px;
      text-align: left;
    }

    .col-honor {
      width: 120px;
      text-align: right;
    }

    /* Resaltado del usuario actual */
    .fila-yo {
      outline: 3px solid var(--accent);
      background: #fff;
    }

    .fila-yo.top-1,
    .fila-yo.top-2,
    .fila-yo.top-3 {
      outline-width: 2px;
    }

    /* Top 3: fila sutil + chapita de posición */
    .top-1 {
      background: #fff8e1;
    }

    /* dorado claro */
    .top-2 {
      background: #f3f6ff;
    }

    /* plateado claro */
    .top-3 {
      background: #fff4ec;
    }

    /* bronce claro */

    .rank-badge {
      display: inline-block;
      min-width: 2.1rem;
      padding: .1rem .45rem;
      border-radius: 9999px;
      font-weight: 700;
      font-size: .9rem;
      text-align: center;
      line-height: 1.2;
    }

    .rank-1 {
      background: #fde68a;
      color: #7a5e00;
    }

    /* gold pill */
    .rank-2 {
      background: #e5e7eb;
      color: #374151;
    }

    /* silver pill */
    .rank-3 {
      background: #fed7aa;
      color: #7c3e0a;
    }

    /* bronze pill */

    /* Responsive: ocultar username en pantallas angostas */
    @media (max-width: 560px) {

      .col-username,
      td.col-username {
        display: none;
      }

      .col-honor {
        width: 90px;
      }

      .col-idx {
        width: 56px;
      }
    }
  </style>
@endpush

@section('content')
  <h1 style="margin:0 0 .6rem">Ranking de honor</h1>

  <div class="card"
       style="overflow:auto">
    <table role="table"
           aria-label="Tabla de ranking de honor">
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

          <tr id="{{ $soyYo ? 'yo' : '' }}"
              class="{{ trim(($soyYo ? 'fila-yo ' : '') . $rowClass) }}">
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
    <div style="margin-top:10px">
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