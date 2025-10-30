{{-- resources/views/layouts/app.blade.php --}}
<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) ?: 'es' }}">

<head>
  <meta charset="utf-8">
  <meta name="viewport"
        content="width=device-width,initial-scale=1,viewport-fit=cover">
  <meta name="csrf-token"
        content="{{ csrf_token() }}">
  <link rel="canonical"
        href="{{ url()->current() }}">

  {{-- Hora del servidor (UTC) para countdowns/auto-enable --}}
  <meta name="server-now-utc-ms"
        content="{{ now('UTC')->getTimestampMs() }}">

  {{-- PWA / modo app --}}
  <link rel="manifest"
        href="/manifest.webmanifest">

  {{-- ANDROID theme color --}}
  <meta name="theme-color"
        content="{{ env('PWA_THEME_COLOR', '#7b2d26') }}">

  {{-- iOS "standalone" --}}
  <meta name="apple-mobile-web-app-capable"
        content="yes">
  <meta name="apple-mobile-web-app-status-bar-style"
        content="black-translucent">
  <meta name="apple-mobile-web-app-title"
        content="{{ config('app.name', 'La Taberna') }}">
  <link rel="apple-touch-icon"
        href="/icons/pwa-192.png">

  <title>
    @hasSection('title')
      @yield('title') · {{ config('app.name', 'La Taberna') }}
    @else
      {{ config('app.name', 'La Taberna') }}
    @endif
  </title>

  {{-- CSS unificado --}}
  <link rel="stylesheet"
        href="{{ asset('css/app.css') }}">

  @stack('head')

  {{-- Config global JS temprana --}}
  <script>
    // Datos para PWA/push usados en pwa.js
    window.PWA = {
      vapidPublicKey: @json(env('WEBPUSH_VAPID_PUBLIC_KEY', '')),
      subscribeUrl: @json(route('push.subscribe')),
      pingUrl: @json(route('push.ping')),
    };

    // Detectar modo "instalada" (standalone) para look 100% app
    (function () {
      function isStandalone() {
        if (window.matchMedia && window.matchMedia('(display-mode: standalone)').matches) {
          return true;
        }
        if ('standalone' in navigator && navigator.standalone) {
          return true;
        }
        if (window.location.search.includes('pwa=1')) {
          return true;
        }
        return false;
      }

      if (isStandalone()) {
        document.documentElement.classList.add('pwa-standalone');
        document.body?.classList.add('pwa-standalone');
      }
    })();
  </script>

  {{-- Nav responsive / accesibilidad menú hamburguesa --}}
  <script src="{{ asset('js/nav.js') }}"
          defer></script>

  {{-- PWA SW + Push --}}
  <script src="{{ asset('js/pwa.js') }}"
          defer></script>
</head>

<body>
  {{-- HEADER --}}
  <header class="site-head">
    <div class="nav">
      <a class="brand"
         href="{{ url('/') }}">
        <img src="{{ asset('logo.png') }}"
             alt="Logo">
        <span>{{ config('app.name', 'La Taberna') }}</span>
      </a>

      {{-- Botón hamburguesa --}}
      <button class="nav-toggle"
              aria-controls="site-nav"
              aria-expanded="false"
              aria-label="Abrir menú">
        <span class="bars"><span class="bar"></span></span>
      </button>

      {{-- Botón "Instalar" (mobile only, se muestra si no está instalada) --}}
      <button type="button"
              id="pwa-install-btn"
              class="pwa-install-btn"
              hidden>
        📲 Instalar
      </button>

      @php use Illuminate\Support\Facades\Route as LRoute; @endphp

      <nav id="site-nav"
           class="nav-main"
           data-nav-collapsible>
        <a class="nav-link"
           href="{{ route('mesas.index') }}">Mesas</a>

        @if (LRoute::has('jornadas.index'))
          <a class="nav-link"
             href="{{ route('jornadas.index') }}">Jornadas</a>
        @endif

        @if (LRoute::has('ranking'))
          <a class="nav-link"
             href="{{ route('ranking') }}">Honor</a>
        @endif

        @if (LRoute::has('dashboard'))
          <a class="nav-link"
             href="{{ route('dashboard') }}">Panel</a>
        @endif

        @auth
          @php
            $u = auth()->user();
            $isPending = is_null($u->approved_at ?? null);
            $isLocked = !is_null($u->locked_at ?? null);
          @endphp

          @if ($isPending && LRoute::has('auth.pending'))
            <a class="nav-link badge warn"
               href="{{ route('auth.pending') }}">Cuenta pendiente</a>
          @endif

          @if ($isLocked && LRoute::has('auth.locked'))
            <a class="nav-link badge warn"
               href="{{ route('auth.locked') }}">Cuenta bloqueada</a>
          @endif
        @endauth

        @can('viewAdmin', \App\Models\Usuario::class)
          @php
            $pendCount = \Illuminate\Support\Facades\Cache::remember(
              'admin.pending_users_count',
              60,
              function () {
                return \App\Models\Usuario::query()
                  ->whereNull('approved_at')
                  ->count();
              }
            );
          @endphp

          <div class="dropdown"
               data-admin-dropdown>
            <button class="nav-link has-flag"
                    type="button"
                    data-admin-toggle
                    aria-haspopup="true"
                    aria-expanded="false"
                    aria-controls="admin-menu">
              Admin ▾
              @if($pendCount > 0)
                <span class="flag"
                      aria-label="{{ $pendCount }} pendientes">{{ $pendCount }}</span>
              @endif
            </button>

            <div class="menu"
                 id="admin-menu"
                 role="menu"
                 hidden>
              @if (LRoute::has('usuarios.pendientes'))
                <a href="{{ route('usuarios.pendientes') }}">
                  Pendientes
                  @if($pendCount > 0)
                    <span class="badge warn"
                          style="margin-left:.35rem">{{ $pendCount }}</span>
                  @endif
                </a>
              @endif

              @if (LRoute::has('admin.usuarios.index'))
                <a href="{{ route('admin.usuarios.index') }}">Usuarios</a>
              @endif

              @if (LRoute::has('admin.auditoria.index'))
                <a href="{{ route('admin.auditoria.index') }}">Auditoría</a>
              @endif
            </div>
          </div>
        @endcan

        <span class="grow"></span>

        @auth
          @if (LRoute::has('profile.edit'))
            <a class="nav-link"
               href="{{ route('profile.edit') }}">Perfil</a>
          @endif

          <a class="btn sm line"
             href="{{ route('auth.logout') }}"
             onclick="event.preventDefault();document.getElementById('logout-form').submit();">
            Salir
          </a>
          <form id="logout-form"
                class="inline"
                method="POST"
                action="{{ route('auth.logout') }}">
            @csrf
          </form>
        @else
          <a class="btn sm line"
             href="{{ route('auth.login') }}">Ingresar</a>
          @if (LRoute::has('auth.register'))
            <a class="btn sm"
               href="{{ route('auth.register') }}">Crear cuenta</a>
          @endif
        @endauth
      </nav>
    </div>
  </header>

  <main class="grid main-shell">
    @if (session('ok'))
      <div class="flash"
           role="status">✅ {{ session('ok') }}</div>
    @endif

    @if (session('error'))
      <div class="flash flash-warn"
           role="alert">
        ⚠️ {{ session('error') }}
      </div>
    @endif

    @if ($errors->any())
      <div class="flash flash-error"
           role="alert">
        <strong>Revisá:</strong>
        <ul style="margin:.3rem 0 0 1rem">
          @foreach ($errors->all() as $e)
            <li>{{ $e }}</li>
          @endforeach
        </ul>
      </div>
    @endif

    @yield('content')

    @auth
      @if(auth()->user()->hasAnyRole(['admin', 'moderator', 'staff']))
        <form method="post"
              action="{{ route('push.ping') }}"
              class="push-test-card">
          @csrf
          <button class="btn sm"
                  type="submit">🔔 Enviar notificación de prueba</button>
          <div class="muted-sm push-test-hint">
            Esta prueba le manda push a TODOS los navegadores suscriptos.
          </div>
        </form>
      @endif
    @endauth
  </main>

  <footer class="nav app-footer"
          role="contentinfo">
    <div class="app-footer-inner">
      <small class="muted">© {{ date('Y') }} · {{ config('app.name', 'La Taberna') }}</small>

      <div class="install-hint">
        📲 Agregá La Taberna a tu pantalla de inicio para usarla como app.
      </div>
    </div>
  </footer>

  {{-- Anti-doble submit --}}
  <script>
    document.addEventListener('DOMContentLoaded', function () {
      document.querySelectorAll('form').forEach(function (form) {
        form.addEventListener('submit', function (ev) {
          if (!form.checkValidity()) return;

          const btn = form.querySelector('button[type="submit"][data-once]');
          if (!btn) return;

          if (btn.dataset.pending === '1') {
            ev.preventDefault();
            return;
          }

          btn.dataset.pending = '1';
          btn.disabled = true;
          btn.setAttribute('aria-disabled', 'true');

          const originalText = (btn.textContent || '').trim();
          btn.dataset.original = originalText;
          btn.textContent = btn.getAttribute('data-label-pending') || 'Procesando…';

          const delay = parseInt(btn.getAttribute('data-delay') || '0', 10);
          if (delay > 0) {
            ev.preventDefault();
            setTimeout(() => {
              if (document.body.contains(form)) {
                form.submit();
              }
            }, delay);
          }
        }, { passive: false });
      });
    });
  </script>

  {{-- Countdown + auto-enable --}}
  <script>
    (function () {
      const meta = document.querySelector('meta[name="server-now-utc-ms"]');
      const serverNow = meta ? +meta.content : Date.now();
      const startClient = Date.now();
      const skew = startClient - serverNow;

      function fmt(ms) {
        if (ms <= 0) return '¡Ya abrió!';
        const s = Math.floor(ms / 1000);
        const d = Math.floor(s / 86400);
        const h = Math.floor((s % 86400) / 3600);
        const m = Math.floor((s % 3600) / 60);
        const ss = s % 60;

        if (d > 0) {
          return (
            d + 'd ' +
            String(h).padStart(2, '0') + ':' +
            String(m).padStart(2, '0') + ':' +
            String(ss).padStart(2, '0')
          );
        }

        return (
          String(h).padStart(2, '0') + ':' +
          String(m).padStart(2, '0') + ':' +
          String(ss).padStart(2, '0')
        );
      }

      function enableBtn(btn) {
        btn.disabled = false;
        btn.removeAttribute('aria-disabled');
        btn.classList.remove('is-disabled');

        const enabledTitle = btn.getAttribute('data-enabled-title') || 'Inscribirme';
        btn.setAttribute('title', enabledTitle);

        const txtNow = (btn.textContent || '').trim();
        if (/abre pronto/i.test(txtNow)) {
          btn.textContent = enabledTitle;
        }
      }

      function tick() {
        let pending = false;

        document.querySelectorAll('time[data-countdown-ts]').forEach(el => {
          const ts = +el.getAttribute('data-countdown-ts');
          if (!ts) return;

          const now = Date.now() - skew;
          const left = ts - now;

          let label = el.parentElement
            ? el.parentElement.querySelector('[data-countdown-label]')
            : null;
          if (!label) {
            label = el;
          }

          label.textContent = fmt(left);
          if (left > 0) {
            pending = true;
          }
        });

        document.querySelectorAll('button[data-activate-at-utc]').forEach(btn => {
          const ts = +btn.getAttribute('data-activate-at-utc');
          if (!ts) return;

          const now = Date.now() - skew;
          if (ts - now <= 0) {
            enableBtn(btn);
            btn.removeAttribute('data-activate-at-utc');
          } else {
            pending = true;
          }
        });

        if (pending) {
          setTimeout(tick, 1000);
        }
      }

      document.addEventListener('DOMContentLoaded', tick);
    })();
  </script>

  {{-- ===== MODAL GLOBAL (Confirmación por password) ===== --}}
  <dialog id="pwd-confirm"
          aria-labelledby="pwd-confirm-title"
          aria-describedby="pwd-confirm-desc">
    <form method="dialog"
          id="pwd-confirm-form"
          class="card"
          role="document"
          novalidate>
      <h3 id="pwd-confirm-title">Confirmar acción</h3>
      <p id="pwd-confirm-desc">Ingresá tu contraseña para confirmar.</p>

      {{-- Campo username oculto para evitar warnings de Chrome --}}
      <input type="text"
             name="username"
             autocomplete="username"
             tabindex="-1"
             aria-hidden="true"
             style="position:absolute;left:-9999px;width:1px;height:1px;opacity:0">

      <label class="muted-sm"
             for="pwd-confirm-input">Contraseña</label>
      <input id="pwd-confirm-input"
             type="password"
             autocomplete="current-password"
             required>

      <div class="modal-actions">
        <button value="cancel"
                type="button"
                id="pwd-confirm-cancel"
                class="btn line sm">Cancelar</button>
        <button value="ok"
                id="pwd-confirm-ok"
                class="btn sm">Confirmar</button>
      </div>
    </form>
  </dialog>

  {{-- JS unificado del modal de password + dropdown Admin + botón Instalar --}}
  <script>
    (function () {
      // ----- Confirmación por password -----
      const dlg = document.getElementById('pwd-confirm');
      const card = document.getElementById('pwd-confirm-form');
      const input = document.getElementById('pwd-confirm-input');
      const okBtn = document.getElementById('pwd-confirm-ok');
      const cancelBtn = document.getElementById('pwd-confirm-cancel');
      let pendingForm = null;

      function attachAndSubmit(formEl, pwd) {
        if (!formEl || formEl.dataset.pwdOk === '1') return;
        const hid = document.createElement('input');
        hid.type = 'hidden';
        hid.name = 'admin_password';
        hid.value = pwd;
        formEl.appendChild(hid);

        formEl.dataset.pwdOk = '1';
        formEl.submit();
      }

      function openFor(formEl) {
        if (formEl?.dataset.requireSelection === 'true') {
          const sel = formEl.dataset.selectionSelector || '.chk';
          const count = Array.from(document.querySelectorAll(sel))
            .filter(c => c.checked).length;
          if (count === 0) {
            alert('Seleccioná al menos un elemento.');
            return;
          }
        }

        pendingForm = formEl;

        if (dlg && typeof dlg.showModal === 'function') {
          input.value = '';
          dlg.showModal();
          setTimeout(() => {
            try { input.focus(); } catch (_) { }
          }, 10);
        } else {
          const pwd = prompt('Ingresá tu contraseña para confirmar:');
          if (pwd) {
            attachAndSubmit(formEl, pwd);
          }
        }
      }

      card?.addEventListener('submit', (e) => e.preventDefault());

      okBtn?.addEventListener('click', function (e) {
        const val = (input.value || '').trim();
        if (!val) {
          e.preventDefault();
          input.focus();
          return;
        }
        dlg?.close('ok');
        attachAndSubmit(pendingForm, val);
        pendingForm = null;
        input.value = '';
      });

      cancelBtn?.addEventListener('click', function (e) {
        e.preventDefault();
        dlg?.close('cancel');
        pendingForm = null;
        input.value = '';
      });

      dlg?.addEventListener('click', (e) => {
        const r = card.getBoundingClientRect();
        const inside =
          e.clientX >= r.left && e.clientX <= r.right &&
          e.clientY >= r.top && e.clientY <= r.bottom;
        if (!inside) {
          dlg.close('cancel');
          pendingForm = null;
          input.value = '';
        }
      });

      dlg?.addEventListener('cancel', () => {
        pendingForm = null;
        input.value = '';
      });

      document.addEventListener('submit', function (ev) {
        const f = ev.target;
        if (!(f instanceof HTMLFormElement)) return;
        if (!f.classList?.contains('js-need-pwd')) return;
        if (f.dataset.pwdOk === '1') return;

        ev.preventDefault();
        openFor(f);
      }, true);

      document.addEventListener('click', function (ev) {
        const btn = ev.target.closest('[data-pwd-target]');
        if (!btn) return;
        ev.preventDefault();
        const sel = btn.getAttribute('data-pwd-target');
        const f = sel ? document.querySelector(sel) : null;
        if (!f) return;
        openFor(f);
      });

      // ----- Dropdown Admin -----
      const adminWrap = document.querySelector('[data-admin-dropdown]');
      if (adminWrap) {
        const toggleBtn = adminWrap.querySelector('[data-admin-toggle]');
        const menu = adminWrap.querySelector('#admin-menu');

        if (toggleBtn && menu) {
          menu.hidden = true;
          toggleBtn.setAttribute('aria-expanded', 'false');

          function isOpen() {
            return toggleBtn.getAttribute('aria-expanded') === 'true';
          }
          function openMenu() {
            menu.hidden = false;
            toggleBtn.setAttribute('aria-expanded', 'true');
          }
          function closeMenu() {
            menu.hidden = true;
            toggleBtn.setAttribute('aria-expanded', 'false');
          }
          function toggleMenu() {
            if (isOpen()) closeMenu();
            else openMenu();
          }

          toggleBtn.addEventListener('click', (e) => {
            e.preventDefault();
            e.stopPropagation();
            toggleMenu();
          });

          document.addEventListener('click', (e) => {
            if (!isOpen()) return;
            if (!adminWrap.contains(e.target)) {
              closeMenu();
            }
          });

          document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
              closeMenu();
            }
          });
        }
      }

      // ---- Ocultar hint "instalá la app" si ya está instalada ----
      if (document.body.classList.contains('pwa-standalone')) {
        const hint = document.querySelector('.install-hint');
        if (hint) hint.style.display = 'none';
      }

      // ===== Botón "Instalar" =====
      (function () {
        const alreadyStandalone =
          (window.matchMedia && window.matchMedia('(display-mode: standalone)').matches) ||
          ('standalone' in navigator && navigator.standalone) ||
          window.location.search.includes('pwa=1');

        const installBtn = document.getElementById('pwa-install-btn');

        console.log('[PWA-install] standalone?', alreadyStandalone);
        console.log('[PWA-install] ua:', navigator.userAgent);

        if (!installBtn) {
          console.log('[PWA-install] no hay botón en el DOM');
          return;
        }

        if (alreadyStandalone) {
          installBtn.hidden = true;
          installBtn.classList.remove('is-visible');
          console.log('[PWA-install] ya instalada, oculto botón');
          return;
        }

        let deferredEvt = null;

        // Mostrar el botón en mobile por default (para pruebas)
        if (window.matchMedia && window.matchMedia('(max-width: 899px)').matches) {
          installBtn.hidden = false;
          installBtn.classList.add('is-visible');
          console.log('[PWA-install] forzado visible en mobile para test inicial');
        }

        // Evento oficial de Chrome/Android
        window.addEventListener('beforeinstallprompt', (e) => {
          console.log('[PWA-install] beforeinstallprompt fired');
          e.preventDefault();
          deferredEvt = e;

          installBtn.hidden = false;
          installBtn.classList.add('is-visible');
        }, { once: true });

        installBtn.addEventListener('click', async () => {
          console.log('[PWA-install] click, deferredEvt?', !!deferredEvt);

          if (deferredEvt) {
            deferredEvt.prompt();
            const { outcome } = await deferredEvt.userChoice;
            console.log('[PWA-install] userChoice', outcome);

            deferredEvt = null;

            // UX linda: fade out
            installBtn.textContent = '✅ Listo';
            installBtn.style.opacity = '0';
            setTimeout(() => {
              installBtn.hidden = true;
              installBtn.classList.remove('is-visible');
            }, 400);

            return;
          }

          // iOS Safari: guía manual
          if (/iphone|ipad|ipod/i.test(navigator.userAgent)) {
            alert('Para instalar:\n1. Tocá el botón Compartir.\n2. Elegí "Agregar a pantalla de inicio".');
            return;
          }

          // Android pero todavía no hay beforeinstallprompt
          alert('Todavía no puedo forzar la instalación automática. Probá "Agregar a pantalla principal" desde el menú del navegador.');
        });
      })();
    })();
  </script>

  @stack('scripts')
</body>

</html>