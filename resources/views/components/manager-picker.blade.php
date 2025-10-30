{{-- resources/views/components/manager-picker.blade.php --}}
@props([
  'name'         => 'manager_id',
  'selected'     => null,
  'required'     => false,
  'label'        => 'Manager',
  'suggestLimit' => 12,
  'maxItems'     => 3000,
])

@php
  // Dataset (o podés inyectar $users desde el controller)
  if (isset($users) && $users instanceof \Illuminate\Support\Collection) {
      $allUsers = $users;
  } else {
      $allUsers = \App\Models\Usuario::query()
          ->leftJoin('mesas', 'usuarios.id', '=', 'mesas.manager_id')
          ->selectRaw("
            usuarios.id,
            usuarios.name,
            usuarios.email,
            usuarios.username,
            usuarios.celular AS phone,
            COUNT(mesas.id) AS mcount
          ")
          ->groupBy('usuarios.id','usuarios.name','usuarios.email','usuarios.username','usuarios.celular')
          ->orderByDesc('mcount')
          ->orderBy('usuarios.name')
          ->limit((int) $maxItems) /* evita datasets enormes en shared hosting */
          ->get();
  }

  $suggested   = $allUsers->filter(fn($u) => (int)$u->mcount > 0)->take((int)$suggestLimit)->values();
  $uid         = 'mgrp_' . \Illuminate\Support\Str::uuid()->toString();
  $selectId    = $uid . '_select';
  $filterId    = $uid . '_filter';
  $clearId     = $uid . '_clear';
  $dataId      = $uid . '_data';
  $selectedVal = is_null($selected) ? '' : (string) $selected;

  $data = $allUsers->map(fn($u) => [
    'id'        => (string)$u->id,
    'name'      => (string)$u->name,
    'email'     => (string)($u->email ?? ''),
    'username'  => (string)($u->username ?? ''),
    'phone'     => (string)($u->phone ?? ''),
    'mcount'    => (int)$u->mcount,
    'suggested' => (int)$u->mcount > 0,
  ])->values();
@endphp

<div class="manager-picker">
  <label for="{{ $filterId }}">{{ $label }}{{ $required ? ' *' : '' }}</label>

  <div class="mgr-tools">
    <input type="search"
           id="{{ $filterId }}"
           class="mgr-filter"
           placeholder="Filtrar por nombre, email, usuario o celular…"
           autocomplete="off"
           aria-controls="{{ $selectId }}"
           aria-label="Filtrar managers">
    <button type="button"
            id="{{ $clearId }}"
            class="btn ghost"
            aria-controls="{{ $selectId }}">
      Limpiar
    </button>
  </div>

  <select name="{{ $name }}"
          id="{{ $selectId }}"
          class="mgr-select"
          size="8"
          {{ $required ? 'required' : '' }}
          aria-label="Elegir manager">
    <option value="" disabled {{ $selectedVal==='' ? 'selected' : '' }}>— Elegí un manager —</option>
    @foreach($suggested as $u)
      <option value="{{ $u->id }}" data-suggested="1" {{ (string)$selectedVal === (string)$u->id ? 'selected' : '' }}>
        {{ $u->name }}
        @if($u->phone) — {{ $u->phone }}
        @elseif($u->email) — {{ $u->email }}
        @elseif($u->username) — {{ '@'.$u->username }}
        @endif
      </option>
    @endforeach
  </select>

  <div class="mgr-hint muted-sm">
    Tip: escribí y presioná <kbd>Enter</kbd> para elegir la primera coincidencia.
  </div>

  <script type="application/json" id="{{ $dataId }}">{!! $data->toJson() !!}</script>
</div>

@push('scripts')
<script>
(function(){
  const filter   = document.getElementById(@json($filterId));
  const select   = document.getElementById(@json($selectId));
  const clearBtn = document.getElementById(@json($clearId));
  const dataEl   = document.getElementById(@json($dataId));
  if (!filter || !select || !dataEl) return;

  const ALL = JSON.parse(dataEl.textContent || '[]');
  const digits = s => (s || '').replace(/\D+/g, '');
  const norm   = s => (s || '').toString().toLowerCase();

  function optionLabel(u){
    const extra = u.phone || u.email || (u.username ? '@'+u.username : '');
    return extra ? `${u.name} — ${extra}` : u.name;
  }

  function rebuild(list, keepValue){
    const prevVal = keepValue ?? select.value ?? '';
    select.innerHTML = '';
    const head = document.createElement('option');
    head.value = ''; head.disabled = true; head.textContent = '— Elegí un manager —';
    select.appendChild(head);
    list.forEach(u => {
      const op = document.createElement('option');
      op.value = String(u.id);
      op.textContent = optionLabel(u);
      if (u.suggested) op.dataset.suggested = '1';
      select.appendChild(op);
    });
    const idx = Array.from(select.options).findIndex(o => o.value === prevVal);
    if (idx >= 0) select.selectedIndex = idx;
    else if (select.options.length > 1) select.selectedIndex = 1;
  }

  (function initial(){
    const suggested = ALL.filter(u => !!u.suggested)
                         .sort((a,b) => b.mcount - a.mcount || norm(a.name).localeCompare(norm(b.name)))
                         .slice(0, {{ (int)$suggestLimit }} );
    rebuild(suggested, @json($selectedVal));
  })();

  function applyFilter(q){
    const ql = norm(q).trim();
    const qd = digits(q);
    if (!ql && !qd){
      const suggested = ALL.filter(u => !!u.suggested)
                           .sort((a,b) => b.mcount - a.mcount || norm(a.name).localeCompare(norm(b.name)))
                           .slice(0, {{ (int)$suggestLimit }} );
      rebuild(suggested);
      return;
    }
    let results = ALL.filter(u => {
      if (qd.length >= 3) return digits(u.phone).includes(qd);
      if (ql.length >= 2) {
        return norm(u.name).includes(ql)
            || norm(u.email).includes(ql)
            || norm(u.username).includes(ql);
      }
      return false;
    });
    results.sort((a,b) => {
      const sa = a.suggested ? 1 : 0;
      const sb = b.suggested ? 1 : 0;
      if (sa !== sb) return sb - sa;
      return norm(a.name).localeCompare(norm(b.name), 'es');
    });
    rebuild(results);
  }

  filter.addEventListener('input', () => applyFilter(filter.value));
  filter.addEventListener('keydown', (e) => {
    if (e.key === 'Enter') { e.preventDefault(); if (select.options.length > 1) select.selectedIndex = 1; }
  });
  clearBtn?.addEventListener('click', () => { filter.value = ''; applyFilter(''); select.focus(); });
})();
</script>
@endpush
