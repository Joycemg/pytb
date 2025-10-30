<?php declare(strict_types=1);

// app/Http/Controllers/JornadaApartadoController.php

namespace App\Http\Controllers;

use App\Models\Jornada;
use App\Models\JornadaApartado;
use App\Models\UserAudit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\Rule;
use Illuminate\Support\Str;
use Illuminate\Database\QueryException;

final class JornadaApartadoController extends Controller
{
    /** Reintentos ante deadlocks/locks */
    private const TX_RETRIES = 3;

    /** Clamp de orden permitido */
    private const ORDEN_MIN = 0;
    private const ORDEN_MAX = 255;

    /** Rate limits (por actor+jornada/apartado) */
    private const RL_CREATE_ATTEMPTS = 10;   // /h
    private const RL_UPDATE_ATTEMPTS = 20;   // /h
    private const RL_DELETE_ATTEMPTS = 10;   // /h
    private const RL_WINDOW_SECONDS = 3600;

    public function store(Request $r, Jornada $jornada): RedirectResponse
    {
        $this->authorize('open', $jornada);

        // RL por actor+jornada
        $rlKey = sprintf('rl:apartado:create:%d:%d', (int) $r->user()->id, (int) $jornada->id);
        if (RateLimiter::tooManyAttempts($rlKey, self::RL_CREATE_ATTEMPTS)) {
            return back()->with('error', 'Demasiadas creaciones. Probá más tarde.')->withInput();
        }

        $data = $r->validate([
            'titulo' => ['required', 'string', 'max:120'],
            'orden' => ['nullable', 'integer', 'min:' . self::ORDEN_MIN, 'max:' . self::ORDEN_MAX],
            'activo' => ['sometimes', 'boolean'],
        ]);

        // Normalizaciones
        $titulo = Str::of($data['titulo'])->squish()->toString();
        if ($titulo === '') {
            return back()->withErrors(['titulo' => 'El título no puede quedar vacío.'])->withInput();
        }
        $activo = array_key_exists('activo', $data) ? $this->boolOr($r, 'activo', true) : true;

        // (Opcional) Evitar títulos duplicados dentro de la jornada
        // Si tenés índice único (jornada_id, titulo), esto evitará errores 500 por carrera.
        $dupTitulo = JornadaApartado::where('jornada_id', $jornada->id)
            ->whereRaw('LOWER(titulo) = ?', [mb_strtolower($titulo)])
            ->exists();
        if ($dupTitulo) {
            return back()->withErrors(['titulo' => 'Ya existe un apartado con ese título en la jornada.'])->withInput();
        }

        $attempts = 0;
        $nuevoId = null;

        while ($attempts < self::TX_RETRIES) {
            $attempts++;
            try {
                DB::transaction(function () use ($jornada, $data, $titulo, $activo, &$nuevoId) {
                    // Calculamos orden en forma segura
                    $orden = $data['orden'] ?? null;

                    if ($orden === null) {
                        // “MAX+1” sin carreras: bloqueamos el último
                        $ultimo = JornadaApartado::where('jornada_id', $jornada->id)
                            ->orderByDesc('orden')
                            ->lockForUpdate()
                            ->first();
                        $orden = ($ultimo?->orden ?? (self::ORDEN_MIN - 1)) + 1;
                    } else {
                        $orden = max(self::ORDEN_MIN, min(self::ORDEN_MAX, (int) $orden));
                        // Si hay colisión, desplazamos hacia abajo para “hacer lugar”
                        JornadaApartado::where('jornada_id', $jornada->id)
                            ->where('orden', '>=', $orden)
                            ->lockForUpdate()
                            ->increment('orden'); // UPDATE … SET orden = orden + 1
                    }

                    // Clamp final por seguridad
                    $orden = max(self::ORDEN_MIN, min(self::ORDEN_MAX, (int) $orden));

                    $ap = JornadaApartado::create([
                        'jornada_id' => $jornada->id,
                        'titulo' => $titulo,
                        'orden' => $orden,
                        'activo' => $activo,
                    ]);

                    $nuevoId = (int) $ap->id;
                }, 5);
                RateLimiter::hit($rlKey, self::RL_WINDOW_SECONDS);
                if ($nuevoId) {
                    UserAudit::log('apartado.create', $r->user()->id, $nuevoId, ['jornada_id' => $jornada->id]);
                }
                break; // OK
            } catch (QueryException $qe) {
                // Si tenés índices únicos (p.ej. (jornada_id, orden) o (jornada_id, titulo)), traducimos a error de UX
                if ($this->isDuplicateKey($qe)) {
                    return back()->withErrors(['titulo' => 'Conflicto de datos (duplicado). Probá con otro título/orden.'])->withInput();
                }
                if ($attempts >= self::TX_RETRIES) {
                    return back()->with('error', 'No se pudo crear el apartado. Intentá nuevamente.')->withInput();
                }
                usleep(random_int(80_000, 180_000));
            } catch (\Throwable) {
                if ($attempts >= self::TX_RETRIES) {
                    return back()->with('error', 'No se pudo crear el apartado. Intentá nuevamente.')->withInput();
                }
                usleep(random_int(80_000, 180_000));
            }
        }

        return back()->with('ok', 'Apartado creado');
    }

    public function update(Request $r, Jornada $jornada, JornadaApartado $apartado): RedirectResponse
    {
        $this->authorize('open', $jornada);

        if ((int) $apartado->jornada_id !== (int) $jornada->id) {
            abort(404);
        }

        // RL por actor+apartado
        $rlKey = sprintf('rl:apartado:update:%d:%d', (int) $r->user()->id, (int) $apartado->id);
        if (RateLimiter::tooManyAttempts($rlKey, self::RL_UPDATE_ATTEMPTS)) {
            return back()->with('error', 'Demasiadas modificaciones. Probá más tarde.')->withInput();
        }

        $data = $r->validate([
            'titulo' => ['sometimes', 'string', 'max:120'],
            'orden' => ['sometimes', 'integer', 'min:' . self::ORDEN_MIN, 'max:' . self::ORDEN_MAX],
            'activo' => ['sometimes', 'boolean'],
        ]);

        if (array_key_exists('titulo', $data)) {
            $data['titulo'] = Str::of((string) $data['titulo'])->squish()->toString();
            if ($data['titulo'] === '') {
                return back()->withErrors(['titulo' => 'El título no puede quedar vacío.'])->withInput();
            }
            // (Opcional) verificar duplicado de título dentro de la jornada
            $dupTitulo = JornadaApartado::where('jornada_id', $jornada->id)
                ->whereRaw('LOWER(titulo) = ?', [mb_strtolower($data['titulo'])])
                ->where('id', '!=', $apartado->id)
                ->exists();
            if ($dupTitulo) {
                return back()->withErrors(['titulo' => 'Ya existe un apartado con ese título en la jornada.'])->withInput();
            }
        }

        if (array_key_exists('activo', $data)) {
            $data['activo'] = $this->boolOr($r, 'activo');
        }

        // Si no se cambia el orden, actualizamos “in place”
        $cambiaOrden = array_key_exists('orden', $data) && ((int) $data['orden'] !== (int) $apartado->orden);

        $attempts = 0;
        while ($attempts < self::TX_RETRIES) {
            $attempts++;
            try {
                if ($cambiaOrden) {
                    DB::transaction(function () use ($jornada, $apartado, $data) {
                        $nuevo = max(self::ORDEN_MIN, min(self::ORDEN_MAX, (int) $data['orden']));
                        $actual = (int) $apartado->orden;

                        // Aseguramos rango válido conociendo el max existente
                        $maxOrden = (int) (JornadaApartado::where('jornada_id', $jornada->id)->lockForUpdate()->max('orden') ?? 0);
                        $nuevo = min($nuevo, $maxOrden);

                        if ($nuevo > $actual) {
                            // Baja uno a los que están entre (actual, nuevo]
                            JornadaApartado::where('jornada_id', $jornada->id)
                                ->whereBetween('orden', [$actual + 1, $nuevo])
                                ->lockForUpdate()
                                ->decrement('orden');
                        } elseif ($nuevo < $actual) {
                            // Sube uno a los que están entre [nuevo, actual)
                            JornadaApartado::where('jornada_id', $jornada->id)
                                ->whereBetween('orden', [$nuevo, $actual - 1])
                                ->lockForUpdate()
                                ->increment('orden');
                        }

                        $apartado->orden = $nuevo;
                        // Otros campos
                        if (array_key_exists('titulo', $data))
                            $apartado->titulo = $data['titulo'];
                        if (array_key_exists('activo', $data))
                            $apartado->activo = (bool) $data['activo'];

                        if ($apartado->isDirty())
                            $apartado->save();
                    }, 5);
                } else {
                    // Sin cambio de orden: update directo (sin TX pesada)
                    $apartado->fill($data);
                    if ($apartado->isDirty())
                        $apartado->save();
                }

                RateLimiter::hit($rlKey, self::RL_WINDOW_SECONDS);
                UserAudit::log('apartado.update', $r->user()->id, $apartado->id, [
                    'jornada_id' => $jornada->id,
                    'fields' => array_keys($data),
                ]);
                break;
            } catch (QueryException $qe) {
                if ($this->isDuplicateKey($qe)) {
                    return back()->withErrors(['orden' => 'Conflicto de orden/título. Probá otro valor.'])->withInput();
                }
                if ($attempts >= self::TX_RETRIES) {
                    return back()->with('error', 'No se pudo actualizar el apartado. Intentá nuevamente.')->withInput();
                }
                usleep(random_int(80_000, 180_000));
            } catch (\Throwable) {
                if ($attempts >= self::TX_RETRIES) {
                    return back()->with('error', 'No se pudo actualizar el apartado. Intentá nuevamente.')->withInput();
                }
                usleep(random_int(80_000, 180_000));
            }
        }

        return back()->with('ok', 'Apartado actualizado');
    }

    public function destroy(Request $r, Jornada $jornada, JornadaApartado $apartado): RedirectResponse
    {
        $this->authorize('open', $jornada);

        if ((int) $apartado->jornada_id !== (int) $jornada->id) {
            abort(404);
        }

        // RL por actor+apartado
        $rlKey = sprintf('rl:apartado:delete:%d:%d', (int) $r->user()->id, (int) $apartado->id);
        if (RateLimiter::tooManyAttempts($rlKey, self::RL_DELETE_ATTEMPTS)) {
            return back()->with('error', 'Demasiadas eliminaciones. Probá más tarde.');
        }

        if ($apartado->mesas()->exists()) {
            return back()->with('error', 'No se puede borrar: el apartado tiene mesas asociadas.');
        }

        $attempts = 0;
        while ($attempts < self::TX_RETRIES) {
            $attempts++;
            try {
                DB::transaction(function () use ($jornada, $apartado) {
                    $ordenBorrado = (int) $apartado->orden;
                    $idBorrar = (int) $apartado->id;

                    // Borrar
                    JornadaApartado::where('id', $idBorrar)->limit(1)->delete();

                    // Compactar orden: bajar uno a todos los que estaban por debajo
                    JornadaApartado::where('jornada_id', $jornada->id)
                        ->where('orden', '>', $ordenBorrado)
                        ->lockForUpdate()
                        ->decrement('orden');
                }, 5);

                RateLimiter::hit($rlKey, self::RL_WINDOW_SECONDS);
                UserAudit::log('apartado.delete', $r->user()->id, $apartado->id, ['jornada_id' => $jornada->id]);
                break;
            } catch (\Throwable) {
                if ($attempts >= self::TX_RETRIES) {
                    return back()->with('error', 'No se pudo eliminar el apartado. Intentá nuevamente.');
                }
                usleep(random_int(80_000, 180_000));
            }
        }

        return back()->with('ok', 'Apartado eliminado');
    }

    /* ===================== helpers ===================== */

    private function isDuplicateKey(QueryException $e): bool
    {
        // MySQL/MariaDB duplicate key: 1062; SQLite: 23000 (driver-dependent)
        $code = (int) ($e->errorInfo[1] ?? 0);
        $sqlState = (string) ($e->errorInfo[0] ?? '');
        return $code === 1062 || $sqlState === '23000';
    }
}
