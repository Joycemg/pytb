<?php declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\SoftDeletes;

final class Usuario extends Authenticatable
{
    use HasFactory, Notifiable, SoftDeletes;

    protected $table = 'usuarios';

    protected $fillable = [
        'name',
        'username',
        'email',
        'celular',
        'password',
        'bio',
        'avatar_path',
        'avatar_url',
        'honor',
        'role',
        'roles',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected $casts = [
        'id' => 'integer',
        'honor' => 'integer',
        'email_verified_at' => 'datetime',
        'approved_at' => 'datetime',
        'approved_by' => 'integer',
        'locked_at' => 'datetime',
        'role' => 'string',
        'deleted_at' => 'datetime',
    ];

    /* ───────── Relaciones ───────── */
    public function inscripciones(): HasMany
    {
        return $this->hasMany(Inscripcion::class, 'user_id');
    }

    public function eventosHonor(): HasMany
    {
        return $this->hasMany(EventoHonor::class, 'user_id');
    }

    /* ───────── Mutators (sin ternarios anidados) ───────── */
    protected function email(): Attribute
    {
        return Attribute::set(function ($v) {
            if ($v === null) {
                return null;
            }
            $s = mb_strtolower(trim((string) $v), 'UTF-8');
            return $s !== '' ? $s : null;
        });
    }

    protected function username(): Attribute
    {
        return Attribute::set(function ($v) {
            if ($v === null) {
                return null;
            }
            $s = mb_strtolower(trim((string) $v), 'UTF-8');
            return $s !== '' ? $s : null;
        });
    }

    protected function name(): Attribute
    {
        return Attribute::set(function ($v) {
            if ($v === null) {
                return null;
            }
            return Str::of((string) $v)->squish()->toString();
        });
    }

    protected function celular(): Attribute
    {
        return Attribute::set(function ($v) {
            if ($v === null) {
                return null;
            }
            return Str::of((string) $v)->squish()->toString();
        });
    }

    /* ───────── Atributos calculados ───────── */
    protected function honorTotal(): Attribute
    {
        return Attribute::get(function (): int {
            $sum = (int) $this->eventosHonor()->counted()->sum('delta');
            return $sum !== 0 ? $sum : (int) ($this->honor ?? 0);
        });
    }

    protected function avatarUrlComputed(): Attribute
    {
        return Attribute::get(function (): ?string {
            if (!empty($this->avatar_path)) {
                return asset('storage/' . ltrim((string) $this->avatar_path, '/'));
            }
            if (!empty($this->avatar_url)) {
                return (string) $this->avatar_url;
            }
            return asset((string) config('app.default_avatar', 'images/avatar-default.svg'));
        });
    }

    /* ───────── Utilidades de roles ───────── */
    private ?array $rolesCache = null;

    public function rolesArray(): array
    {
        if ($this->rolesCache !== null) {
            return $this->rolesCache;
        }

        $out = [];
        if (is_string($this->roles) && $this->roles !== '') {
            $out = array_filter(array_map(function ($r) {
                return mb_strtolower(trim((string) $r), 'UTF-8');
            }, explode(',', $this->roles)));
        } elseif (is_string($this->role) && $this->role !== '') {
            $out = [mb_strtolower(trim($this->role), 'UTF-8')];
        }

        return $this->rolesCache = array_values(array_unique($out));
    }

    public function hasAnyRole(array $roles): bool
    {
        $wanted = array_map(function ($r) {
            return mb_strtolower(trim((string) $r), 'UTF-8');
        }, $roles);

        return count(array_intersect($wanted, $this->rolesArray())) > 0;
    }

    public function hasRole(string $role): bool
    {
        return $this->hasAnyRole([$role]);
    }

    public function hasAbility(string $ability): bool
    {
        return in_array(mb_strtolower(trim($ability), 'UTF-8'), $this->rolesArray(), true);
    }

    public function isAdmin(): bool
    {
        $env = (string) env('AUTH_ADMIN_ROLES', 'admin');
        $adminRoles = array_filter(array_map(function ($r) {
            return mb_strtolower(trim((string) $r), 'UTF-8');
        }, explode(',', $env)));

        return count(array_intersect($adminRoles, $this->rolesArray())) > 0;
    }

    public function rolPrincipal(): ?string
    {
        if (!empty($this->role)) {
            return mb_strtolower((string) $this->role, 'UTF-8');
        }
        $arr = $this->rolesArray();
        return $arr[0] ?? null;
    }

    public function jerarquia(): int
    {
        $r = $this->rolPrincipal();
        return $r === 'admin' ? 3 : ($r === 'moderator' ? 2 : 1);
    }

    /* ───────── Estados y scopes ───────── */
    public function estaAprobado(): bool
    {
        return $this->approved_at !== null && $this->locked_at === null;
    }

    public function estaBloqueado(): bool
    {
        return $this->locked_at !== null;
    }

    public function scopePendientes($q)
    {
        return $q->whereNull($this->getTable() . '.approved_at');
    }

    public function scopeAprobados($q)
    {
        return $q->whereNotNull($this->getTable() . '.approved_at');
    }

    public function scopeBloqueados($q)
    {
        return $q->whereNotNull($this->getTable() . '.locked_at');
    }

    public function scopeActivos($q)
    {
        $t = $this->getTable();
        return $q->whereNotNull($t . '.approved_at')->whereNull($t . '.locked_at');
    }

    public function scopeDeRol($q, string $role)
    {
        $role = mb_strtolower(trim($role), 'UTF-8');
        $t = $this->getTable();
        return $q->where(function ($w) use ($t, $role) {
            $w->where($t . '.role', $role)
                ->orWhereRaw('FIND_IN_SET(?, ' . $t . '.roles)', [$role]);
        });
    }

    public function scopeBuscar($q, ?string $term)
    {
        $t = trim((string) $term);
        if ($t === '') {
            return $q;
        }

        $t = mb_substr($t, 0, 80);
        $like = '%' . addcslashes($t, '%_') . '%';
        $digits = preg_replace('/\D+/', '', $t) ?? '';
        $tbl = $this->getTable();

        return $q->where(function ($w) use ($like, $digits, $tbl) {
            $w->where($tbl . '.name', 'like', $like)
                ->orWhere($tbl . '.username', 'like', $like)
                ->orWhere($tbl . '.email', 'like', $like);

            if ($digits !== '') {
                $w->orWhereRaw("
                    REPLACE(
                      REPLACE(
                        REPLACE(
                          REPLACE(
                            REPLACE(COALESCE($tbl.celular,''), '+', ''),
                          '-', ''),
                        ' ', ''),
                      '(', ''),
                    ')', '') LIKE ?
                ", ['%' . $digits . '%']);
            }
        });
    }
}
