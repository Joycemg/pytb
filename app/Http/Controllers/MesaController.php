<?php declare(strict_types=1);

namespace App\Http\Controllers;

use App\Events\MesaCerrada;
use App\Models\Jornada;
use App\Models\JornadaApartado;
use App\Models\Mesa;
use App\Services\DescargaImagenRemota;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

final class MesaController extends Controller
{
    /** LISTADO (público): solo mesas de la jornada abierta actual */
    public function index(Request $r): View
    {
        $data = $r->validate([
            'q' => ['nullable', 'string', 'max:120'],
            'per_page' => ['nullable', 'integer', 'min:6', 'max:60'],
        ]);

        $perPage = $this->_perPageClamp($r, 12);
        $q = (string) ($data['q'] ?? '');
        $like = $q !== '' ? $this->_likeTerm($q, 80) : null;

        $jornada = Jornada::abierta()
            ->select(['id', 'estado', 'abierta_at'])
            ->orderByDesc('id')
            ->first();

        $hasOpenEnroll = Schema::hasColumn('mesas', 'inscripciones_abren_at');

        // Incluir FKs/fechas usadas en vistas y helpers
        $select = [
            'id',
            'jornada_id',
            'jornada_apartado_id',
            'title',
            'description',
            'capacity',
            'is_open',
            'opens_at',
            'image_path',
            'image_url',
            'manager_counts_as_player',
            'created_by',
            'manager_id',
        ];
        if ($hasOpenEnroll) {
            $select[] = 'inscripciones_abren_at';
        }

        $tables = Mesa::query()
            ->when($jornada, fn($q2) => $q2->where('jornada_id', $jornada->id))
            ->when(!$jornada, fn($q2) => $q2->whereRaw('1=0'))
            ->when($like !== null, function ($x) use ($like) {
                $x->where(function ($w) use ($like) {
                    $w->where('title', 'like', $like)
                        ->orWhere('description', 'like', $like);
                });
            })
            ->select($select)
            ->conConfirmadas() // <-- clave: agrega confirmadas_count
            ->with([
                'creador:id,name',
                'manager:id,name',
                'apartado:id,titulo'
            ])
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();

        return view('mesas.index', [
            'tables' => $tables,
            'jornada' => $jornada,
        ]);
    }

    /** SHOW (público) */
    public function show(Mesa $mesa): View
    {
        $userCols = Cache::remember('usuarios_tel_cols', 3600, function () {
            $cols = ['id', 'name'];
            try {
                foreach (Schema::getColumnListing('usuarios') as $c) {
                    if (preg_match('/(phone|cel|m[oó]vil|tel)/i', $c)) {
                        $cols[] = $c;
                    }
                }
            } catch (\Throwable) {
            }
            return array_values(array_unique($cols));
        });

        $mesa->load([
            'creador:id,name',
            'manager' => fn($q) => $q->select($userCols),
            'apartado:id,titulo',
            'inscripciones' => fn($q) => $q
                ->select(['id', 'mesa_id', 'user_id', 'is_waiting', 'created_at'])
                ->where('is_waiting', false)
                ->with(['usuario' => fn($uq) => $uq->select($userCols)])
                ->orderBy('id'),
            'jornada:id,titulo,estado,abierta_at,cerrada_at',
        ]);

        return view('mesas.show', compact('mesa'));
    }

    /** CREAR (solo admin/mod) */
    public function create(): RedirectResponse|View
    {
        $this->authorize('create', Mesa::class);

        $jornada = Jornada::abierta()
            ->select(['id', 'estado', 'abierta_at'])
            ->orderByDesc('id')
            ->first();

        if (!$jornada) {
            return redirect()->route('jornadas.index')
                ->with('error', 'No hay una jornada abierta. Pedí a un admin/mod abrir jornada.');
        }

        return view('mesas.create', ['jornada' => $jornada]);
    }

    public function store(Request $r): RedirectResponse
    {
        $this->authorize('create', Mesa::class);

        $jornada = Jornada::abierta()->select(['id', 'estado'])->orderByDesc('id')->first();
        if (!$jornada) {
            return back()->with('error', 'No hay una jornada abierta.')->withInput();
        }

        $hasOpenEnroll = Schema::hasColumn('mesas', 'inscripciones_abren_at');

        $rules = [
            'title' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:2000'],
            'manager_id' => ['required', 'exists:usuarios,id'],
            'capacity' => ['required', 'integer', 'min:1', 'max:100'],
            'manager_counts_as_player' => ['sometimes', 'boolean'],
            'opens_at' => ['nullable', 'date'],
            'image' => ['nullable', 'file', 'mimetypes:image/jpeg,image/png', 'max:1024'],
            'image_url' => ['nullable', 'url'],
            'jornada_apartado_id' => ['nullable', 'exists:jornada_apartados,id'],
        ];
        if ($hasOpenEnroll) {
            $rules['inscripciones_abren_at'] = ['nullable', 'date'];
        }

        $data = $r->validate($rules);

        // Normalizaciones
        $data['title'] = Str::of($data['title'])->squish()->toString();
        if (array_key_exists('description', $data) && $data['description'] !== null) {
            $data['description'] = Str::of((string) $data['description'])->squish()->toString();
        }
        $data['manager_counts_as_player'] = $r->boolean('manager_counts_as_player');
        $data['single_vote'] = true;
        $data['created_by'] = $r->user()->id;
        $data['jornada_id'] = $jornada->id;

        if (array_key_exists('opens_at', $data)) {
            $data['opens_at'] = $data['opens_at'] ? now()->parse($data['opens_at']) : null;
        }
        if ($hasOpenEnroll && array_key_exists('inscripciones_abren_at', $data)) {
            $data['inscripciones_abren_at'] = $data['inscripciones_abren_at'] ? now()->parse($data['inscripciones_abren_at']) : null;
        } else {
            unset($data['inscripciones_abren_at']); // evita insertar columna inexistente
        }

        // Validar apartado dentro de la jornada actual
        if (!empty($data['jornada_apartado_id'])) {
            $okApartado = JornadaApartado::where('id', $data['jornada_apartado_id'])
                ->where('jornada_id', $jornada->id)
                ->exists();
            if (!$okApartado) {
                return back()->with('error', 'El apartado no pertenece a la jornada actual.')->withInput();
            }
        }

        // Imagen local o remota
        if ($r->hasFile('image') && filled($data['image_url'] ?? null)) {
            return back()->with('error', 'Elegí archivo o URL, no ambas.')->withInput();
        }
        if ($r->hasFile('image')) {
            try {
                $data['image_path'] = $r->file('image')->store('mesas', 'public');
                $data['image_url'] = null;
            } catch (\Throwable) {
                return back()->with('error', 'No se pudo guardar la imagen.')->withInput();
            }
        } elseif (filled($data['image_url'] ?? null)) {
            $remoteUrl = trim((string) $data['image_url']);
            $sch = parse_url($remoteUrl, PHP_URL_SCHEME);
            if (!in_array($sch, ['http', 'https'], true)) {
                return back()->with('error', 'URL de imagen inválida.')->withInput();
            }
            $f = \App\Services\DescargaImagenRemota::descargar($remoteUrl);
            if ($f) {
                $ext = $f['mime'] === 'image/png' ? 'png' : 'jpg';
                $path = 'mesas/' . $r->user()->id . '-' . time() . '.' . $ext;
                try {
                    Storage::disk('public')->put($path, $f['data']);
                    $data['image_path'] = $path;
                    $data['image_url'] = null;
                } catch (\Throwable) {
                    return back()->with('error', 'No se pudo guardar la imagen remota.')->withInput();
                }
            } else {
                $data['image_url'] = $remoteUrl;
                $data['image_path'] = null;
            }
        }

        /** @var Mesa $mesa */
        $mesa = Mesa::create($data);

        // Auto-cierre si no queda cupo
        if ($this->capacityLeft($mesa) <= 0) {
            $wasFirstClose = empty($mesa->closed_at);
            $mesa->is_open = false;
            $mesa->closed_at ??= now();
            $mesa->save();

            DB::afterCommit(function () use ($mesa, $wasFirstClose) {
                event(new MesaCerrada($mesa->fresh(), firstClose: $wasFirstClose));
            });
        }

        return redirect()->route('mesas.show', $mesa)->with('ok', 'Mesa creada');
    }

    /** EDITAR */
    public function edit(Mesa $mesa): View
    {
        $this->authorize('update', $mesa);
        $mesa->load('jornada:id,titulo,estado');
        return view('mesas.edit', compact('mesa'));
    }

    public function update(Request $request, Mesa $mesa): RedirectResponse
    {
        if ($request->has('is_open') && !$request->has('toggle_open')) {
            $this->authorize('close', $mesa);

            $targetOpen = $request->boolean('is_open');
            $capBase = $this->capacidadEfectiva($mesa);
            $confirmados = (int) $mesa->inscripciones()->where('is_waiting', false)->toBase()->count();

            if ($targetOpen) {
                $mesa->load('jornada:id,estado');
                $estaAbierta = $mesa->jornada
                    ? (method_exists($mesa->jornada, 'estaAbierta') ? $mesa->jornada->estaAbierta() : ($mesa->jornada->estado === 'abierta'))
                    : false;

                if (!$estaAbierta) {
                    return back()->with('error', 'No se puede abrir: la mesa pertenece a una jornada cerrada o inexistente.');
                }
                if ($confirmados >= $capBase) {
                    return back()->with('error', 'La mesa está llena. Se abrirá automáticamente cuando alguien quite su voto.');
                }
            }

            if ($mesa->is_open !== $targetOpen) {
                $firstClose = (!$targetOpen) && empty($mesa->closed_at);

                $mesa->is_open = $targetOpen;
                if (!$targetOpen && empty($mesa->closed_at))
                    $mesa->closed_at = now();
                $mesa->save();

                if (!$targetOpen) {
                    DB::afterCommit(function () use ($mesa, $firstClose) {
                        event(new MesaCerrada($mesa->fresh(), firstClose: $firstClose));
                    });
                }
            }

            return back()->with('ok', $targetOpen ? 'Mesa abierta' : 'Mesa cerrada');
        }

        $this->authorize('update', $mesa);

        $u = $request->user();
        $esAdminMod = method_exists($u, 'hasAnyRole') ? $u->hasAnyRole(['admin', 'moderator']) : false;
        $esManager = (int) $u->id === (int) ($mesa->manager_id ?? 0);
        $hasOpenEnroll = Schema::hasColumn('mesas', 'inscripciones_abren_at');

        if ($esAdminMod) {
            $rules = [
                'title' => ['sometimes', 'string', 'max:120'],
                'description' => ['sometimes', 'nullable', 'string', 'max:2000'],
                'capacity' => ['sometimes', 'integer', 'min:1', 'max:100'],
                'manager_id' => ['sometimes', 'required', 'exists:usuarios,id'],
                'image' => ['sometimes', 'nullable', 'file', 'mimetypes:image/jpeg,image/png', 'max:1024'],
                'image_url' => ['sometimes', 'nullable', 'url'],
                'manager_counts_as_player' => ['sometimes', 'boolean'],
                'jornada_apartado_id' => ['sometimes', 'nullable', 'exists:jornada_apartados,id'],
                'opens_at' => ['sometimes', 'nullable', 'date'],
            ];
            if ($hasOpenEnroll) {
                $rules['inscripciones_abren_at'] = ['sometimes', 'nullable', 'date'];
            }
        } elseif ($esManager) {
            $rules = [
                'title' => ['sometimes', 'string', 'max:120'],
                'description' => ['sometimes', 'nullable', 'string', 'max:2000'],
                'capacity' => ['sometimes', 'integer', 'min:1', 'max:100'],
            ];
        } else {
            $rules = [
                'description' => ['sometimes', 'nullable', 'string', 'max:2000'],
                'capacity' => ['sometimes', 'integer', 'min:1', 'max:100'],
            ];
        }

        $data = $request->validate($rules);

        if (array_key_exists('title', $data)) {
            $data['title'] = Str::of((string) $data['title'])->squish()->toString();
        }
        if (array_key_exists('description', $data) && $data['description'] !== null) {
            $data['description'] = Str::of((string) $data['description'])->squish()->toString();
        }

        if ($esAdminMod && array_key_exists('manager_counts_as_player', $data)) {
            $data['manager_counts_as_player'] = $request->boolean('manager_counts_as_player');
        }
        if ($esAdminMod && array_key_exists('opens_at', $data)) {
            $data['opens_at'] = $data['opens_at'] ? now()->parse($data['opens_at']) : null;
        }
        if ($esAdminMod) {
            if ($hasOpenEnroll && array_key_exists('inscripciones_abren_at', $data)) {
                $data['inscripciones_abren_at'] = $data['inscripciones_abren_at'] ? now()->parse($data['inscripciones_abren_at']) : null;
            } else {
                unset($data['inscripciones_abren_at']); // evita update de columna inexistente
            }
        }

        if ($esAdminMod && array_key_exists('jornada_apartado_id', $data) && !empty($data['jornada_apartado_id'])) {
            $okApartado = JornadaApartado::where('id', $data['jornada_apartado_id'])
                ->where('jornada_id', $mesa->jornada_id)
                ->exists();
            if (!$okApartado) {
                return back()->with('error', 'El apartado seleccionado no pertenece a la misma jornada.')->withInput();
            }
        }

        if (array_key_exists('manager_id', $data) && empty($data['manager_id'])) {
            return back()->with('error', 'La mesa debe tener un manager.')->withInput();
        }

        // Imagen (solo admin/mod)
        if ($esAdminMod && ($request->hasFile('image') || filled($data['image_url'] ?? null))) {
            if ($request->hasFile('image') && filled($data['image_url'] ?? null)) {
                return back()->with('error', 'Elegí archivo o URL, no ambas.')->withInput();
            }

            $newPath = null;
            $useRemoteUrl = null;
            $oldPath = $mesa->image_path;

            if ($request->hasFile('image')) {
                try {
                    $newPath = $request->file('image')->store('mesas', 'public');
                } catch (\Throwable) {
                    return back()->with('error', 'No se pudo actualizar la imagen.')->withInput();
                }
            } elseif (filled($data['image_url'])) {
                $remoteUrl = trim((string) $data['image_url']);
                $sch = parse_url($remoteUrl, PHP_URL_SCHEME);
                if (!in_array($sch, ['http', 'https'], true)) {
                    return back()->with('error', 'URL de imagen inválida.')->withInput();
                }
                $f = DescargaImagenRemota::descargar($remoteUrl);
                if ($f) {
                    $ext = $f['mime'] === 'image/png' ? 'png' : 'jpg';
                    $path = 'mesas/' . $u->id . '-' . time() . '.' . $ext;
                    try {
                        Storage::disk('public')->put($path, $f['data']);
                        $newPath = $path;
                    } catch (\Throwable) {
                        return back()->with('error', 'No se pudo guardar la imagen remota.')->withInput();
                    }
                } else {
                    $useRemoteUrl = $remoteUrl;
                }
            }

            unset($data['image'], $data['image_url']);

            if ($newPath !== null) {
                $data['image_path'] = $newPath;
                $data['image_url'] = null;
            } elseif ($useRemoteUrl !== null) {
                $data['image_path'] = null;
                $data['image_url'] = $useRemoteUrl;
            }

            $mesa->fill($data);
            if (!$mesa->isDirty() && $newPath === null && $useRemoteUrl === null) {
                return back()->with('ok', 'No hubo cambios');
            }
            $mesa->save();

            if (($newPath !== null || $useRemoteUrl !== null) && $oldPath) {
                try {
                    Storage::disk('public')->delete($oldPath);
                } catch (\Throwable) {
                }
            }

            $mesa->refresh();
            if ($this->capacityLeft($mesa) <= 0 && $mesa->is_open) {
                $firstClose = empty($mesa->closed_at);
                $mesa->is_open = false;
                $mesa->closed_at ??= now();
                $mesa->save();

                DB::afterCommit(function () use ($mesa, $firstClose) {
                    event(new MesaCerrada($mesa->fresh(), firstClose: $firstClose));
                });
            }

            return redirect()->route('mesas.show', $mesa)->with('ok', 'Cambios guardados');
        }

        unset($data['image'], $data['image_url']);
        if (!$esAdminMod) {
            unset($data['manager_id'], $data['manager_counts_as_player'], $data['jornada_apartado_id'], $data['opens_at'], $data['inscripciones_abren_at']);
        }

        $mesa->fill($data);

        if (!$mesa->isDirty()) {
            return back()->with('ok', 'No hubo cambios');
        }

        $mesa->save();

        $mesa->refresh();
        if ($this->capacityLeft($mesa) <= 0 && $mesa->is_open) {
            $firstClose = empty($mesa->closed_at);
            $mesa->is_open = false;
            $mesa->closed_at ??= now();
            $mesa->save();

            DB::afterCommit(function () use ($mesa, $firstClose) {
                event(new MesaCerrada($mesa->fresh(), firstClose: $firstClose));
            });
        }

        return redirect()->route('mesas.show', $mesa)->with('ok', 'Cambios guardados');
    }

    public function cerrar(Request $request, Mesa $mesa): RedirectResponse
    {
        $this->authorize('close', $mesa);

        if (!$mesa->is_open) {
            return back()->with('ok', 'La mesa ya estaba cerrada');
        }

        $firstClose = empty($mesa->closed_at);
        $mesa->closed_at ??= now();
        $mesa->is_open = false;
        $mesa->save();

        DB::afterCommit(function () use ($mesa, $firstClose) {
            event(new MesaCerrada($mesa->fresh(), firstClose: $firstClose));
        });

        return back()->with('ok', 'Mesa cerrada');
    }

    public function abrir(Request $request, Mesa $mesa): RedirectResponse
    {
        $this->authorize('close', $mesa);

        $capBase = $this->capacidadEfectiva($mesa);
        $confirmados = (int) $mesa->inscripciones()->where('is_waiting', false)->toBase()->count();

        $mesa->load('jornada:id,estado');
        $estaAbierta = $mesa->jornada
            ? (method_exists($mesa->jornada, 'estaAbierta') ? $mesa->jornada->estaAbierta() : ($mesa->jornada->estado === 'abierta'))
            : false;

        if (!$estaAbierta) {
            return back()->with('error', 'No se puede abrir: la mesa pertenece a una jornada cerrada o inexistente.');
        }

        if ($confirmados >= $capBase) {
            return back()->with('error', 'La mesa está llena. Se abrirá automáticamente cuando alguien quite su voto.');
        }

        if (!$mesa->is_open) {
            $mesa->is_open = true;
            $mesa->save();
            return back()->with('ok', 'Mesa abierta');
        }

        return back()->with('ok', 'La mesa ya estaba abierta');
    }

    public function destroy(Request $request, Mesa $mesa): RedirectResponse
    {
        $this->authorize('delete', $mesa);

        $request->validate(['password' => ['required', 'current_password:web']]);

        $imagePath = $mesa->image_path;

        try {
            DB::transaction(function () use ($mesa) {
                $mesa->inscripciones()->delete();
                $mesa->delete();
            }, 5);
        } catch (\Throwable) {
            return back()->with('error', 'No se pudo eliminar la mesa. Intentá nuevamente.');
        }

        if ($imagePath) {
            try {
                Storage::disk('public')->delete($imagePath);
            } catch (\Throwable) {
            }
        }

        return redirect()->route('mesas.index')->with('ok', 'Mesa eliminada');
    }

    /* ===================== helpers privados ===================== */

    private function capacidadEfectiva(Mesa $m): int
    {
        if (method_exists($m, 'capacidadEfectiva')) {
            return max(0, (int) $m->capacidadEfectiva());
        }
        return max(0, (int) $m->capacity - ((bool) $m->manager_counts_as_player ? 1 : 0));
    }

    private function capacityLeft(Mesa $m): int
    {
        if (method_exists($m, 'capacityLeft')) {
            return (int) $m->capacityLeft();
        }
        $capBase = $this->capacidadEfectiva($m);
        $confirmados = (int) $m->inscripciones()->where('is_waiting', false)->toBase()->count();
        return max(0, $capBase - $confirmados);
    }

    private function _perPageClamp(Request $r, int $default = 12): int
    {
        $pp = (int) ($r->input('per_page', $default) ?? $default);
        if ($pp < 6)
            $pp = 6;
        if ($pp > 60)
            $pp = 60;
        return $pp;
    }

    private function _likeTerm(string $q, int $maxLen = 80): string
    {
        $t = trim($q);
        $t = function_exists('mb_substr') ? mb_substr($t, 0, $maxLen, 'UTF-8') : substr($t, 0, $maxLen);
        $tEsc = str_replace(['%', '_'], ['\\%', '\\_'], $t);
        return '%' . $tEsc . '%';
    }
}
