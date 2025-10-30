<?php declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\DescargaImagenRemota;
use App\Support\Validacion\Rules\UsuarioReservado;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Rule;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;

final class PerfilController extends Controller
{
    /** Rate limit: updates de perfil por usuario (por hora) */
    private const RL_UPDATES_PER_HOUR = 10;
    private const RL_WINDOW_SECONDS = 3600;

    /** Tamaño máximo de avatar en KB (host compartido) */
    private const AVATAR_MAX_KB = 512;

    public function edit(Request $r)
    {
        return view('profile.edit', ['user' => $r->user()]);
    }

    public function update(Request $r): RedirectResponse
    {
        $u = $r->user();

        // Rate-limit suave por usuario
        $rlKey = sprintf('rl:perfil:update:%d', (int) $u->id);
        if (RateLimiter::tooManyAttempts($rlKey, self::RL_UPDATES_PER_HOUR)) {
            return back()->with('error', 'Hiciste demasiados cambios seguidos. Probá más tarde.');
        }

        // Validación ligera y compatible con hosting compartido
        $data = $r->validate([
            'name' => ['required', 'string', 'max:100'],
            'username' => [
                'nullable',
                'string',
                'max:20',
                new UsuarioReservado,
                Rule::unique('usuarios', 'username')->ignore($u->id)->whereNull('deleted_at'),
            ],
            'bio' => ['nullable', 'string', 'max:1000'],
            'avatar' => ['nullable', 'file', 'mimetypes:image/jpeg,image/png', 'max:' . self::AVATAR_MAX_KB],
            'avatar_url' => ['nullable', 'url'],
            'remove_avatar' => ['sometimes', 'boolean'],
        ]);

        // Normalizaciones básicas
        $data['name'] = Str::of($data['name'] ?? $u->name)->squish()->toString();

        if (array_key_exists('username', $data)) {
            $username = Str::of((string) $data['username'])->trim()->lower()->toString();
            $data['username'] = ($username !== '') ? $username : null;
        }

        if (array_key_exists('bio', $data) && $data['bio'] !== null) {
            // texto llano; si usás Markdown/HTML podés quitar este squish
            $data['bio'] = Str::of((string) $data['bio'])->squish()->toString();
        }

        // ---- Avatar (reemplazo atómico) -----------------------------------
        $oldPath = $u->avatar_path;
        $newPath = null;

        if ($r->boolean('remove_avatar')) {
            // Se borra el archivo anterior después de guardar (post-commit)
            $data['avatar_path'] = null;
            // Nunca persistimos URL externa
            $data['avatar_url'] = null;

        } else {
            // No permitir archivo y URL a la vez
            if ($r->hasFile('avatar') && !empty($data['avatar_url'])) {
                throw ValidationException::withMessages(['avatar' => 'Elegí archivo o URL, no ambas.']);
            }

            if ($r->hasFile('avatar')) {
                try {
                    $newPath = $r->file('avatar')->store('avatars', 'public');
                } catch (\Throwable $e) {
                    throw ValidationException::withMessages(['avatar' => 'No se pudo guardar la imagen.']);
                }
                $data['avatar_path'] = $newPath;
                $data['avatar_url'] = null; // prioriza archivo local
            } elseif (!empty($data['avatar_url'])) {
                // Aceptamos solo http/https
                $sch = parse_url($data['avatar_url'], PHP_URL_SCHEME);
                if (!in_array($sch, ['http', 'https'], true)) {
                    throw ValidationException::withMessages(['avatar_url' => 'URL de imagen inválida.']);
                }

                $f = DescargaImagenRemota::descargar($data['avatar_url']);
                if ($f) {
                    $ext = $f['mime'] === 'image/png' ? 'png' : 'jpg';
                    $name = 'avatars/' . $u->id . '-' . time() . '-' . Str::random(6) . '.' . $ext;

                    try {
                        $ok = Storage::disk('public')->put($name, $f['data'], ['visibility' => 'public']);
                    } catch (\Throwable $e) {
                        $ok = false;
                    }

                    if (!$ok) {
                        throw ValidationException::withMessages(['avatar_url' => 'No se pudo guardar la imagen remota.']);
                    }

                    $newPath = $name;
                    $data['avatar_path'] = $newPath;
                    $data['avatar_url'] = null; // almacenamos local
                } else {
                    // URL inválida o imagen no aceptada → ignoramos sin romper
                    unset($data['avatar_url']);
                }
            } else {
                // No hubo cambios de avatar
                unset($data['avatar'], $data['avatar_url']);
            }
        }

        // Evitar UPDATE si no hay cambios reales
        $u->fill($data);
        if ($u->isDirty()) {
            // Guardamos en TX corta y borramos el archivo viejo después del commit
            DB::transaction(function () use ($u) {
                $u->save();
            }, 3);

            DB::afterCommit(function () use ($oldPath, $u) {
                // Si cambió el path o lo removimos, borrar el anterior
                if ($oldPath && $oldPath !== $u->avatar_path) {
                    try {
                        Storage::disk('public')->delete($oldPath);
                    } catch (\Throwable $e) {
                        // ignore en hosting compartido
                    }
                }
            });
        }

        // Hit de rate-limit solo en éxito
        RateLimiter::hit($rlKey, self::RL_WINDOW_SECONDS);

        return back()->with('ok', 'Perfil actualizado');
    }
}
