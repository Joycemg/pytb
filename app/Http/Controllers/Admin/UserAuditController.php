<?php declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\UserAudit;
use App\Models\Usuario;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class UserAuditController extends Controller
{
    private const DEFAULT_PER_PAGE = 40;

    public function index(Request $r): View
    {
        $this->authorize('view', UserAudit::class);

        // Validación suave de query params
        $data = $r->validate([
            'q' => ['nullable', 'string', 'max:120'],
            'action' => ['nullable', 'string', 'max:64'],
            'actor_id' => ['nullable', 'integer', 'min:1'],
            'target_id' => ['nullable', 'integer', 'min:1'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'per_page' => ['nullable', 'integer'],
        ]);

        $perPage = $this->perPageFrom($r, self::DEFAULT_PER_PAGE);

        $audits = $this->applyFilters(UserAudit::query(), $data)
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();

        // ===== Mapear actores/targets (sin N+1) =====
        $ids = [];
        foreach ($audits->items() as $a) {
            if (!empty($a->actor_id))
                $ids[] = (int) $a->actor_id;
            if (!empty($a->target_id))
                $ids[] = (int) $a->target_id;
        }
        $ids = array_values(array_unique($ids));

        $usersMap = Usuario::whereIn('id', $ids)
            ->get(['id', 'name', 'role'])
            ->keyBy('id'); // id => Usuario

        return view('admin.auditoria.index', [
            'audits' => $audits,
            'q' => (string) ($data['q'] ?? ''),
            'action' => (string) ($data['action'] ?? ''),
            'actor_id' => $data['actor_id'] ?? null,
            'target_id' => $data['target_id'] ?? null,
            'from' => $data['from'] ?? null,
            'to' => $data['to'] ?? null,
            'per_page' => $perPage,
            'usersMap' => $usersMap,
        ]);
    }

    public function exportCsv(Request $r): StreamedResponse
    {
        $this->authorize('export', UserAudit::class);

        $data = $r->validate([
            'q' => ['nullable', 'string', 'max:120'],
            'action' => ['nullable', 'string', 'max:64'],
            'actor_id' => ['nullable', 'integer', 'min:1'],
            'target_id' => ['nullable', 'integer', 'min:1'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
        ]);

        $filename = 'user_audits_' . now()->format('Ymd_His') . '.csv';

        $query = $this->applyFilters(
            UserAudit::query()->select(['id', 'actor_id', 'target_id', 'action', 'meta', 'ip', 'ua', 'created_at']),
            $data
        )->orderBy('id');

        return response()->streamDownload(function () use ($query) {
            @ini_set('output_buffering', 'off');
            @ini_set('zlib.output_compression', '0');
            @set_time_limit(0);

            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // BOM UTF-8

            fputcsv($out, ['id', 'actor_id', 'target_id', 'action', 'meta', 'ip', 'ua', 'created_at']);

            $query->chunkById(500, function ($rows) use ($out) {
                foreach ($rows as $a) {
                    $meta = $a->meta ? json_encode($a->meta, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : '';
                    fputcsv($out, [
                        $a->id,
                        $a->actor_id,
                        $a->target_id,
                        (string) $a->action,
                        $meta,
                        (string) $a->ip,
                        (string) $a->ua,
                        $a->created_at?->format('c'),
                    ]);
                }
                fflush($out);
            });

            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Cache-Control' => 'no-store, max-age=0, must-revalidate',
            'Pragma' => 'no-cache',
            'X-Accel-Buffering' => 'no',
        ]);
    }

    /** Aplica filtros comunes */
    private function applyFilters($qb, array $data)
    {
        $q = trim((string) ($data['q'] ?? ''));
        $action = trim((string) ($data['action'] ?? ''));
        $actor = $data['actor_id'] ?? null;
        $target = $data['target_id'] ?? null;

        $from = $this->dateOr($data['from'] ?? null, false); // startOfDay
        $to = $this->dateOr($data['to'] ?? null, true);    // endOfDay

        return $qb
            ->when($q !== '', function ($qq) use ($q) {
                $like = $this->likeTerm($q, 80);
                $qq->where(function ($w) use ($like) {
                    $w->where('ua', 'like', $like)
                        ->orWhere('ip', 'like', $like);
                });
            })
            ->when($action !== '', fn($qq) => $qq->where('action', $action))
            ->when($actor !== null, fn($qq) => $qq->where('actor_id', (int) $actor))
            ->when($target !== null, fn($qq) => $qq->where('target_id', (int) $target))
            ->when($from, fn($qq) => $qq->where('created_at', '>=', $from))
            ->when($to, fn($qq) => $qq->where('created_at', '<=', $to));
    }
}
