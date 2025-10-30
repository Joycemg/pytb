{{-- resources/views/mesas/_show_aside.blade.php --}}
@php
    use Illuminate\Support\Facades\Route as LRoute;
    // $mesa y $efectivamenteAbierta vienen del include
    $estaAbierta = (bool) ($mesa->is_open ?? false);
@endphp

<aside class="card">
    <div class="card-header">
        <div class="card-title">Acciones</div>
    </div>

    @can('update', $mesa)
        @if(LRoute::has('mesas.edit'))
            <a class="btn full"
               href="{{ route('mesas.edit', $mesa) }}"
               aria-label="Editar mesa {{ e($mesa->title) }}">✏️ Editar</a>
        @endif
    @endcan

    @can('close', $mesa)
        @if(LRoute::has('mesas.update'))
            <form method="post"
                  action="{{ route('mesas.update', $mesa) }}"
                  class="mt-sm">
                @csrf @method('PUT')
                <input type="hidden"
                       name="is_open"
                       value="{{ $estaAbierta ? 0 : 1 }}">
                <button class="btn full"
                        data-once
                        aria-pressed="{{ $estaAbierta ? 'true' : 'false' }}">
                    {{ $estaAbierta ? 'Cerrar mesa' : 'Abrir mesa' }}
                </button>
            </form>
            <p class="muted mt-sm">
                Estado actual: <strong>{{ $estaAbierta ? 'Abierta' : 'Cerrada' }}</strong>
            </p>
        @endif
    @endcan

    @can('delete', $mesa)
        <div class="card p-sm mt-sm">
            <div class="card-title"
                 style="margin:.1rem 0 .3rem">Eliminar mesa</div>

            <details>
                <summary class="btn danger"
                         style="list-style:none;display:inline-block;cursor:pointer"
                         role="button"
                         aria-controls="frm-del-{{ $mesa->id }}"
                         aria-expanded="false">
                    🗑️ Eliminar mesa
                </summary>

                @if(LRoute::has('mesas.destroy'))
                    <form id="frm-del-{{ $mesa->id }}"
                          method="post"
                          action="{{ route('mesas.destroy', $mesa) }}"
                          class="mt-sm"
                          novalidate>
                        @csrf @method('DELETE')

                        <label for="pwd-{{ $mesa->id }}">Confirmá tu contraseña</label>
                        <input id="pwd-{{ $mesa->id }}"
                               type="password"
                               name="password"
                               required
                               minlength="6"
                               placeholder="••••••••"
                               autocomplete="current-password">

                        @error('password')
                            <div class="flash mt-sm"
                                 role="alert"
                                 style="border-color:#e0b4b4">
                                ⚠️ {{ $message }}
                            </div>
                        @enderror

                        <p class="muted mt-sm">
                            Esta acción borra la mesa y todas sus inscripciones. No se puede deshacer.
                        </p>

                        <button class="btn danger"
                                type="submit"
                                data-once>
                            Eliminar definitivamente
                        </button>
                    </form>
                @endif
            </details>
        </div>
    @endcan

    <div class="muted mt-sm">
        Creada por: {{ e(optional($mesa->creador)->name ?? '—') }}<br>
        Manager: {{ e(optional($mesa->manager)->name ?? '—') }}
    </div>
</aside>