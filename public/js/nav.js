// public/js/nav.js
(function () {
    const STATE_ATTR = 'data-nav-open';
    const BTN_ATTR = 'data-nav-bound';

    function initNavOnce() {
        const btn = document.querySelector('.nav-toggle');
        const nav = document.getElementById('site-nav');

        if (!btn || !nav) return;

        // ¿ya inicializado este botón en esta sesión de DOM?
        if (btn.getAttribute(BTN_ATTR) === '1') return;
        btn.setAttribute(BTN_ATTR, '1');

        // Helpers -----------------------------

        function isOpen() {
            return btn.getAttribute('aria-expanded') === 'true';
        }

        function lockScroll(lock) {
            // Evita que el body scrollee cuando el menú está abierto (mobile drawer style).
            if (lock) {
                document.body.style.overflow = 'hidden';
            } else {
                document.body.style.overflow = '';
            }
        }

        function setOpen(open) {
            btn.setAttribute('aria-expanded', String(open));

            if (open) {
                nav.setAttribute('data-open', '');
                document.body.setAttribute(STATE_ATTR, '');
                lockScroll(true);

                // Mover el foco al primer link navegable del menú para accesibilidad
                const firstLink = nav.querySelector('a, button, [tabindex]:not([tabindex="-1"])');
                if (firstLink) {
                    firstLink.focus({ preventScroll: true });
                }
            } else {
                nav.removeAttribute('data-open');
                document.body.removeAttribute(STATE_ATTR);
                lockScroll(false);

                // devolver foco al botón hamburguesa
                btn.focus({ preventScroll: true });
            }
        }

        function toggle() {
            setOpen(!isOpen());
        }

        // Estado inicial cerrado
        setOpen(false);

        // Eventos -----------------------------

        // Toggle al click en el hamburguesa
        btn.addEventListener('click', (e) => {
            e.stopPropagation();
            toggle();
        });

        // Cerrar con ESC en cualquier lado
        // Nota: registramos UNA sola vez global y la guardamos en window para no duplicar
        if (!window.__navEscBound) {
            window.__navEscBound = true;
            document.addEventListener('keydown', (e) => {
                if (e.key === 'Escape') {
                    setOpen(false);
                }
            });
        }

        // Cerrar al hacer click en un link interno del menú
        nav.addEventListener('click', (e) => {
            if (e.target.closest('a')) {
                setOpen(false);
            }
        });

        // Cerrar al clickear fuera del menú cuando está abierto
        document.addEventListener('click', (e) => {
            if (!isOpen()) return;
            const clickInsideNav = nav.contains(e.target);
            const clickOnBtn = btn.contains(e.target);
            if (!clickInsideNav && !clickOnBtn) {
                setOpen(false);
            }
        }, { capture: true }); // capture para ganarle a otros handlers
    }

    // Disparadores para distintos sistemas de navegación parcial
    function readyHandler() {
        initNavOnce();
    }

    // DOM ya cargado / o cargando
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', readyHandler, { once: true });
    } else {
        readyHandler();
    }

    // page cache restore (bfcache / Turbo Drive / etc.)
    // usamos {once:false} pero la función misma hace guardia con BTN_ATTR
    window.addEventListener('pageshow', readyHandler);

    // Turbo (Hotwire)
    document.addEventListener('turbo:load', readyHandler);

    // Livewire
    document.addEventListener('livewire:load', readyHandler);

    // htmx
    document.addEventListener('htmx:afterSettle', readyHandler);
})();
