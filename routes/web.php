<?php declare(strict_types=1);

use Illuminate\Support\Facades\Route;

// App controllers
use App\Http\Controllers\ApiController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BlogCommentController;
use App\Http\Controllers\BlogController;
use App\Http\Controllers\BlogPostLikeController;
use App\Http\Controllers\BlogFeedController;
use App\Http\Controllers\InscripcionController;
use App\Http\Controllers\JornadaApartadoController;
use App\Http\Controllers\JornadaController;
use App\Http\Controllers\MesaController;
use App\Http\Controllers\ModeracionController;
use App\Http\Controllers\PanelController;
use App\Http\Controllers\PerfilController;
use App\Http\Controllers\RankingHonorController;
use App\Http\Controllers\UsuariosAprobacionController;
use App\Http\Controllers\PushController;

// Admin controllers
use App\Http\Controllers\Admin\UserAuditController;
use App\Http\Controllers\Admin\UsuarioAdminController;

// Middlewares
use App\Http\Middleware\EnsureUserIsActive;
use App\Http\Middleware\ETagDebil;

// Models para policies
use App\Models\Jornada;
use App\Models\Mesa;

/* --------------------- Reglas globales de parámetros -------------------- */
Route::pattern('mesa', '\d+');
Route::pattern('jornada', '\d+');
Route::pattern('apartado', '\d+');

/* --------------------------------- Home -------------------------------- */
Route::get('/', [BlogController::class, 'home'])->name('home');
Route::get('/blog', [BlogController::class, 'home'])->name('blog.index');
Route::get('/blog/comunidad', [BlogController::class, 'community'])->name('blog.community');
Route::get('/blog/atom', [BlogFeedController::class, 'atom'])->name('blog.atom');
Route::get('/blog/{post:slug}', [BlogController::class, 'show'])->name('blog.show');

/* ---------------------------------- Auth -------------------------------- */
Route::middleware('guest')->group(function () {
    Route::get('/entrar', [AuthController::class, 'showLogin'])->name('auth.login');
    Route::post('/entrar', [AuthController::class, 'login'])->name('auth.login.post');

    Route::get('/registro', [AuthController::class, 'showRegister'])->name('auth.register');
    Route::post('/registro', [AuthController::class, 'register'])->name('auth.register.post');
});
Route::permanentRedirect('/login', '/entrar')->name('login');

Route::post('/salir', [AuthController::class, 'logout'])
    ->middleware('auth')
    ->name('auth.logout');

/* ----------------------------- Jornadas público ------------------------- */
Route::get('/jornadas', [JornadaController::class, 'index'])->name('jornadas.index');
Route::get('/jornadas/{jornada}', [JornadaController::class, 'show'])->name('jornadas.show');

/* ---------------------- PUSH público necesario -------------------------- */
/* 1) El SW arma el contenido de la notificación */
Route::get('/push/compose-notification', [PushController::class, 'compose'])
    ->name('push.compose');

/* 2) GUARDAR SUSCRIPCIÓN — ***SIN auth*** para que funcione en móvil        */
/*    (el Service Worker y/o el navegador pueden no tener sesión vigente).   */
/*    Throttle defensivo y grupo 'web' para CSRF.                             */
Route::post('/push/subscribe', [PushController::class, 'subscribe'])
    ->name('push.subscribe')
    ->middleware('throttle:60,1');

/* --------------- Estado de cuenta (auth pero sin EnsureUserIsActive) ---- */
Route::middleware('auth')->group(function () {
    Route::view('/estado/pendiente', 'auth.account-pending')->name('auth.pending');
    Route::view('/estado/bloqueada', 'auth.account-locked')->name('auth.locked');
});

/* ---------------- Zona autenticada + cuenta activa ---------------------- */
Route::middleware(['auth', EnsureUserIsActive::class])
    ->scopeBindings()
    ->group(function () {

        /* ===== Jornadas ===== */
        Route::post('/jornadas/abrir', [JornadaController::class, 'abrir'])
            ->middleware('can:open,' . Jornada::class)
            ->name('jornadas.abrir');

        Route::post('/jornadas/{jornada}/cerrar', [JornadaController::class, 'cerrar'])
            ->middleware('can:close,jornada')
            ->name('jornadas.cerrar');

        Route::get('/jornadas/{jornada}/estado', [JornadaController::class, 'estadoModeracion'])
            ->name('jornadas.estado');

        /* ===== Apartados ===== */
        Route::post('/jornadas/{jornada}/apartados', [JornadaApartadoController::class, 'store'])
            ->middleware('can:open,' . Jornada::class)
            ->name('jornadas.apartados.store');

        Route::put('/jornadas/{jornada}/apartados/{apartado}', [JornadaApartadoController::class, 'update'])
            ->middleware('can:open,' . Jornada::class)
            ->name('jornadas.apartados.update');

        Route::delete('/jornadas/{jornada}/apartados/{apartado}', [JornadaApartadoController::class, 'destroy'])
            ->middleware('can:open,' . Jornada::class)
            ->name('jornadas.apartados.destroy');

        /* ===== Mesas (admin/mod) ===== */
        Route::get('/mesas/create', [MesaController::class, 'create'])
            ->middleware('can:create,' . Mesa::class)
            ->name('mesas.create');

        Route::post('/mesas', [MesaController::class, 'store'])
            ->middleware('can:create,' . Mesa::class)
            ->name('mesas.store');

        Route::get('/mesas/{mesa}/edit', [MesaController::class, 'edit'])
            ->middleware('can:update,mesa')
            ->name('mesas.edit');

        Route::put('/mesas/{mesa}', [MesaController::class, 'update'])
            ->middleware('can:update,mesa')
            ->name('mesas.update');

        Route::put('/mesas/{mesa}/cerrar', [MesaController::class, 'cerrar'])
            ->middleware('can:close,mesa')
            ->name('mesas.cerrar');

        Route::put('/mesas/{mesa}/abrir', [MesaController::class, 'abrir'])
            ->middleware('can:close,mesa')
            ->name('mesas.abrir');

        Route::delete('/mesas/{mesa}', [MesaController::class, 'destroy'])
            ->middleware('can:delete,mesa')
            ->name('mesas.destroy');

        /* ===== Inscripciones ===== */
        Route::post('/mesas/{mesa}/inscribirme', [InscripcionController::class, 'store'])
            ->name('inscripciones.store');

        Route::delete('/mesas/{mesa}/mi-inscripcion', [InscripcionController::class, 'destroy'])
            ->name('inscripciones.destroy');

        /* ===== Moderación por mesa ===== */
        Route::post('/moderacion/mesa/{mesa}/confirmar', [ModeracionController::class, 'confirmarMesa'])
            ->middleware('can:update,mesa')
            ->name('moderacion.confirmarMesa');

        /* ===== Blog ===== */
        Route::get('/blog/comunidad/enviar', [BlogController::class, 'communityCreate'])
            ->name('blog.community.create');

        Route::post('/blog/comunidad', [BlogController::class, 'communityStore'])
            ->name('blog.community.store');

        Route::get('/blog/comunidad/mis-aportes', [BlogController::class, 'communityMine'])
            ->name('blog.community.mine');

        Route::get('/blog/comunidad/{post}/editar', [BlogController::class, 'communityEdit'])
            ->whereNumber('post')
            ->name('blog.community.edit');

        Route::put('/blog/comunidad/{post}', [BlogController::class, 'communityUpdate'])
            ->whereNumber('post')
            ->name('blog.community.update');

        Route::delete('/blog/comunidad/{post}', [BlogController::class, 'communityDestroy'])
            ->whereNumber('post')
            ->name('blog.community.destroy');

        Route::post('/blog/{post:slug}/comentarios', [BlogCommentController::class, 'store'])
            ->name('blog.comments.store');

        Route::post('/blog/{post:slug}/me-gusta', BlogPostLikeController::class)
            ->name('blog.likes.toggle');

        Route::get('/panel/blog', [BlogController::class, 'manage'])
            ->name('blog.manage');

        Route::get('/panel/blog/nueva', [BlogController::class, 'create'])
            ->name('blog.create');

        Route::post('/panel/blog', [BlogController::class, 'store'])
            ->name('blog.store');

        Route::get('/panel/blog/{post}/editar', [BlogController::class, 'edit'])
            ->name('blog.edit');

        Route::put('/panel/blog/{post}', [BlogController::class, 'update'])
            ->name('blog.update');

        Route::delete('/panel/blog/{post}', [BlogController::class, 'destroy'])
            ->name('blog.destroy');

        Route::post('/panel/blog/{post}/aprobar', [BlogController::class, 'approve'])
            ->name('blog.approve');

        Route::delete('/panel/blog/{post}/adjuntos/{attachment}', [BlogController::class, 'destroyAttachment'])
            ->name('blog.attachments.destroy');

        /* ===== Panel / Ranking / Perfil ===== */
        Route::get('/panel', [PanelController::class, 'show'])->name('dashboard');
        Route::get('/ranking', [RankingHonorController::class, 'index'])->name('ranking');
        Route::get('/perfil', [PerfilController::class, 'edit'])->name('profile.edit');
        Route::post('/perfil', [PerfilController::class, 'update'])->name('profile.update');

        /* ===== Aprobación rápida (pendientes) ===== */
        Route::get('/usuarios/pendientes', [UsuariosAprobacionController::class, 'index'])
            ->name('usuarios.pendientes');

        Route::post('/usuarios/{usuario}/aprobar', [UsuariosAprobacionController::class, 'aprobar'])
            ->name('usuarios.aprobar');

        /* ===== PUSH protegidas (requiere usuario activo) ===== */
        // Disparar notificación a todos (controller valida roles admin/mod/staff)
        Route::match(['GET', 'POST'], '/push/ping', [PushController::class, 'ping'])
            ->name('push.ping');

        /* ===== ADMIN ===== */
        Route::prefix('admin')
            ->name('admin.')
            ->middleware('can:viewAdmin,' . \App\Models\Usuario::class)
            ->group(function () {

                // Usuarios
                Route::get('/usuarios', [UsuarioAdminController::class, 'index'])->name('usuarios.index');
                Route::get('/usuarios/{usuario}/edit', [UsuarioAdminController::class, 'edit'])->name('usuarios.edit');
                Route::put('/usuarios/{usuario}', [UsuarioAdminController::class, 'update'])->name('usuarios.update');

                Route::post('/usuarios/bulk', [UsuarioAdminController::class, 'bulk'])->name('usuarios.bulk');
                Route::post('/usuarios/{usuario}/aprobar', [UsuarioAdminController::class, 'approve'])->name('usuarios.aprobar');
                Route::post('/usuarios/{usuario}/role', [UsuarioAdminController::class, 'setRole'])->name('usuarios.role');
                Route::post('/usuarios/{usuario}/password', [UsuarioAdminController::class, 'resetPassword'])->name('usuarios.password');
                Route::post('/usuarios/{usuario}/lock', [UsuarioAdminController::class, 'lock'])->name('usuarios.lock');
                Route::post('/usuarios/{usuario}/unlock', [UsuarioAdminController::class, 'unlock'])->name('usuarios.unlock');

                // Auditoría
                Route::get('/auditoria', [UserAuditController::class, 'index'])->name('auditoria.index');
                Route::get('/auditoria/export', [UserAuditController::class, 'exportCsv'])->name('auditoria.export');
            });
    });

/* --------------------------- Mesas públicas ----------------------------- */
Route::get('/mesas', [MesaController::class, 'index'])->name('mesas.index');
Route::get('/mesas/{mesa}', [MesaController::class, 'show'])->name('mesas.show');

/* --------------------------------- API ---------------------------------- */
Route::prefix('api')->group(function () {
    Route::get('/ping', [ApiController::class, 'ping'])
        ->middleware([ETagDebil::class, 'throttle:60,1'])
        ->name('api.ping');
});

/* ------------------------------ Fallback 404 ---------------------------- */
Route::fallback(function () {
    return response()->view('errors.404', [], 404);
});

/* ------------- Utilidad para generar un par VAPID una vez -------------- */
Route::get('/vapid/gen-once', function () {
    $conf = ['private_key_type' => OPENSSL_KEYTYPE_EC, 'curve_name' => 'prime256v1'];
    $res = openssl_pkey_new($conf);
    openssl_pkey_export($res, $privPem);
    $details = openssl_pkey_get_details($res);

    $pubPem = $details['key']; // PEM pública

    // convertir la public key PEM a raw (X+Y) y luego a base64url
    $pemNoHeaders = preg_replace('/-----(BEGIN|END) PUBLIC KEY-----|\s+/', '', $pubPem);
    $der = base64_decode($pemNoHeaders);

    // BIT STRING que arranca con 0x03 0x42 0x00 0x04
    $pos = strpos($der, "\x03\x42\x00\x04");
    if ($pos === false)
        return response('No pude parsear la pubkey', 500);

    $q = substr($der, $pos + 4, 65); // 0x04 + X(32) + Y(32)
    $xy = substr($q, 1);             // saco 0x04
    $b64url = rtrim(strtr(base64_encode($xy), '+/', '-_'), '=');

    return response()->json([
        'PUBLIC_KEY_BASE64URL' => $b64url,
        'PRIVATE_KEY_PEM' => $privPem,
    ]);
});
