{{-- resources/views/pagination/hostinger.blade.php --}}
@if ($paginator->hasPages())
    <nav role="navigation"
         aria-label="Paginación"
         class="pager-host">
        {{-- Anterior --}}
        @if ($paginator->onFirstPage())
            <span class="pg-btn pg-disabled"
                  aria-disabled="true"
                  aria-label="Anterior">‹ Anterior</span>
        @else
            <a class="pg-btn"
               href="{{ $paginator->previousPageUrl() }}"
               rel="prev"
               aria-label="Anterior">‹ Anterior</a>
        @endif

        {{-- Números y separadores --}}
        @foreach ($elements as $element)
            {{-- “…” --}}
            @if (is_string($element))
                <span class="pg-gap"
                      aria-hidden="true">{{ $element }}</span>
            @endif

            {{-- Enlaces de páginas --}}
            @if (is_array($element))
                @foreach ($element as $page => $url)
                    @if ($page == $paginator->currentPage())
                        <span class="pg-btn pg-active"
                              aria-current="page">{{ $page }}</span>
                    @else
                        <a class="pg-btn"
                           href="{{ $url }}"
                           aria-label="Ir a la página {{ $page }}">{{ $page }}</a>
                    @endif
                @endforeach
            @endif
        @endforeach

        {{-- Siguiente --}}
        @if ($paginator->hasMorePages())
            <a class="pg-btn"
               href="{{ $paginator->nextPageUrl() }}"
               rel="next"
               aria-label="Siguiente">Siguiente ›</a>
        @else
            <span class="pg-btn pg-disabled"
                  aria-disabled="true"
                  aria-label="Siguiente">Siguiente ›</span>
        @endif
    </nav>
@endif