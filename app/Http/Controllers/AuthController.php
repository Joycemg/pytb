<?php declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Usuario;
use App\Models\UserAudit;
use App\Support\Validacion\Rules\UsuarioReservado;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Illuminate\Support\Str;
use Illuminate\Database\QueryException;

final class AuthController extends Controller
{
    /** Intentos máximos de login antes de bloquear temporalmente */
    private const LOGIN_MAX_ATTEMPTS = 5;
    /** Ventana de bloqueo (segundos) para login */
    private const LOGIN_DECAY_SECONDS = 60;

    /** Intentos máx. de registro por huella (IP+UA) */
    private const REGISTER_MAX_ATTEMPTS = 10;
    /** Ventana de bloqueo (segundos) para registro */
    private const REGISTER_DECAY_SECONDS = 300;

    public function showRegister(): View
    {
        return view('auth.register');
    }

    public function register(Request $r): RedirectResponse
    {
        // Honeypot simple (campo oculto "hp")
        if (filled($r->input('hp'))) {
            return back()
                ->withErrors(['email' => 'No se pudo completar el registro.'])
                ->withInput();
        }

        // ── Rate limit por IP+UA ────────────────────────────────────────────
        $regKey = $this->registerThrottleKey($r);
        if (RateLimiter::tooManyAttempts($regKey, self::REGISTER_MAX_ATTEMPTS)) {
            $seconds = RateLimiter::availableIn($regKey);
            return back()
                ->withErrors(['email' => "Demasiados registros desde este dispositivo. Probá en {$seconds}s."])
                ->withInput();
        }

        // ── Normalizaciones mínimas previas ────────────────────────────────
        if ($r->has('email')) {
            $r->merge(['email' => mb_strtolower(trim((string) $r->input('email', '')))]);
        }
        if ($r->has('username')) {
            $r->merge(['username' => mb_strtolower(trim((string) $r->input('username', '')))]);
        }

        // ── Validación (ignora soft-deleted con whereNull('deleted_at')) ───
        $data = $r->validate([
            'name' => ['required', 'string', 'max:100'],
            'email' => [
                'required',
                'string',
                'email',
                'max:150',
                Rule::unique('usuarios', 'email')->whereNull('deleted_at'),
            ],
            'username' => [
                'nullable',
                'string',
                'max:20',
                'regex:/^[a-z0-9_.-]+$/i',
                Rule::unique('usuarios', 'username')->whereNull('deleted_at'),
                new UsuarioReservado,
            ],
            'celular' => ['required', 'string', 'max:30', 'regex:/^[0-9+()\-\s]{6,}$/'],
            'password' => ['required', Password::min(8)->letters()->numbers(), 'max:100', 'confirmed'],
            'hp' => ['nullable', 'string', 'max:0'], // honeypot
        ], [
            'celular.regex' => 'El celular debe tener al menos 6 dígitos/símbolos válidos (+() - y espacios).',
            'username.regex' => 'El usuario solo puede tener letras, números, punto, guion y guion bajo.',
        ]);

        // Limpiezas
        $data['name'] = Str::of($data['name'])->squish()->toString();
        $data['celular'] = Str::of($data['celular'])->squish()->toString();
        $data['username'] = isset($data['username']) && $data['username'] !== ''
            ? trim((string) $data['username']) : null;

        try {
            $u = Usuario::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'username' => $data['username'],
                'celular' => $data['celular'],
                'password' => Hash::make($data['password']),
            ]);
        } catch (QueryException $e) {
            // Condición de carrera UNIQUE (email/username)
            RateLimiter::hit($regKey, self::REGISTER_DECAY_SECONDS);
            return back()
                ->withErrors(['email' => 'Ese email o usuario ya está en uso.'])
                ->withInput();
        }

        // Éxito → limpiamos throttling de registro
        RateLimiter::clear($regKey);

        // ¿Requiere aprobación? (configurable)
        $requireApproval = (bool) config('auth.require_approval', true);
        if ($requireApproval) {
            // refresca badge "Pendientes (N)"
            Cache::forget('pending_users_count');

            // no inicia sesión: queda "pending" hasta que admin apruebe
            UserAudit::log('register.pending', null, $u->id, []);
            return redirect()->route('login')->with('ok', 'Cuenta creada. Un admin debe aprobar tu acceso.');
        }

        // Auto-login: regenera sesión (previene fijación)
        Auth::login($u);
        $r->session()->regenerate();

        // Opcional: verificación de email si implementás MustVerifyEmail
        if (method_exists($u, 'hasVerifiedEmail') && !$u->hasVerifiedEmail()) {
            try {
                $u->sendEmailVerificationNotification();
            } catch (\Throwable) {
                // noop
            }
        }

        UserAudit::log('register.ok', $u->id, $u->id, []);
        return redirect()->intended(route('home'))->with('ok', '¡Bienvenido/a!');
    }

    public function showLogin(): View
    {
        return view('auth.login');
    }

    public function login(Request $r): RedirectResponse
    {
        // Normaliza email y arma clave de rate limit (email + IP + UA)
        $email = mb_strtolower(trim((string) $r->input('email', '')));
        $key = $this->loginThrottleKey($email, $r);

        if (RateLimiter::tooManyAttempts($key, self::LOGIN_MAX_ATTEMPTS)) {
            $seconds = RateLimiter::availableIn($key);
            return back()
                ->withErrors(['email' => "Demasiados intentos. Probá de nuevo en {$seconds}s."])
                ->withInput();
        }

        $cred = $r->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            'remember' => ['nullable', 'boolean'],
        ]);

        $ok = Auth::attempt(
            ['email' => mb_strtolower($cred['email']), 'password' => $cred['password']],
            $r->boolean('remember')
        );

        if (!$ok) {
            RateLimiter::hit($key, self::LOGIN_DECAY_SECONDS);
            UserAudit::log('login.fail', null, null, ['email' => $email, 'ip' => $r->ip()]);
            return back()->withErrors(['email' => 'Credenciales inválidas'])->withInput();
        }

        /** @var \App\Models\Usuario $user */
        $user = $r->user();

        // Bloqueado
        if ($user->locked_at !== null) {
            Auth::logout();
            $r->session()->invalidate();
            $r->session()->regenerateToken();
            return back()->withErrors(['email' => 'Tu cuenta está bloqueada. Contactá a un administrador.']);
        }

        // ¿requiere aprobación y aún no aprobada?
        if ((bool) config('auth.require_approval', true) && $user->approved_at === null) {
            Auth::logout();
            $r->session()->invalidate();
            $r->session()->regenerateToken();
            return back()->withErrors(['email' => 'Cuenta pendiente de aprobación.']);
        }

        // OK: limpiamos throttling y regeneramos sesión
        RateLimiter::clear($key);
        $r->session()->regenerate();

        // Opcional: rehash si el algoritmo cambió
        if (Hash::needsRehash($user->getAuthPassword())) {
            try {
                $user->password = Hash::make($cred['password']);
                $user->save();
            } catch (\Throwable) {
                // noop
            }
        }

        // Persistir últimos datos de login (si esas columnas existen)
        try {
            if ($user->isFillable('last_login_at'))
                $user->last_login_at = now();
            if ($user->isFillable('last_login_ip'))
                $user->last_login_ip = (string) $r->ip();
            if ($user->isFillable('last_login_ua'))
                $user->last_login_ua = (string) $r->userAgent();
            if ($user->isDirty())
                $user->save();
        } catch (\Throwable) {
            // noop
        }

        UserAudit::log('login.ok', $user->id, $user->id, ['ip' => $r->ip()]);
        return redirect()->intended(route('home'))->with('ok', 'Sesión iniciada');
    }

    public function logout(Request $r): RedirectResponse
    {
        $uid = optional($r->user())->id;

        Auth::logout();
        $r->session()->invalidate();
        $r->session()->regenerateToken();

        if ($uid) {
            UserAudit::log('logout', $uid, $uid, []);
        }

        return redirect()->route('home')->with('ok', 'Sesión cerrada');
    }

    /* =================== Helpers =================== */

    private function loginThrottleKey(string $email, Request $r): string
    {
        return 'login:' . sha1(
            mb_strtolower($email) . '|' . ($r->ip() ?? '0.0.0.0') . '|' . ($r->userAgent() ?? 'na')
        );
    }

    private function registerThrottleKey(Request $r): string
    {
        return 'register:' . sha1(($r->ip() ?? '0.0.0.0') . '|' . ($r->userAgent() ?? 'na'));
    }
}
