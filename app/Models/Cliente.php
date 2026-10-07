<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Cliente extends Model
{
    use SoftDeletes;

    protected $table = 'clientes';

    protected $fillable = [
        'nombre', 'codigo_pais', 'telefono', 'usuario', 'email', 'notas', 'user_id',
    ];

    protected $hidden = ['portal_token_hash'];

    protected $casts = [
        'portal_token_expires_at' => 'datetime',
        'portal_activated_at' => 'datetime',
    ];

    public static function normalizarUsuario(mixed $valor): ?string
    {
        $usuario = preg_replace('/[\p{Cf}\p{Z}\s]+/u', '', (string) ($valor ?? '')) ?? '';
        $usuario = ltrim(mb_strtolower($usuario, 'UTF-8'), '@');

        return $usuario === '' ? null : $usuario;
    }

    public function setUsuarioAttribute(mixed $valor): void
    {
        $this->attributes['usuario'] = self::normalizarUsuario($valor);
    }

    public function cuentaPortal()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function ventas()
    {
        return $this->hasMany(Ventas::class);
    }

    public function pedidos()
    {
        return $this->hasMany(Pedido::class);
    }

    public function scopeBuscar($query, ?string $termino)
    {
        $termino = trim(preg_replace('/\p{Cf}+/u', '', (string) $termino) ?? '');

        if ($termino === '') {
            return $query;
        }

        $numeros = preg_match('/^\+?[0-9\s().-]+$/', $termino)
            ? preg_replace('/\D/', '', $termino)
            : '';
        $usuario = self::normalizarUsuario($termino);
        // Un escape explícito conserva guiones bajos y porcentajes literales.
        $patron = static fn ($valor) => '%'.str_replace(
            ['!', '%', '_'], ['!!', '!%', '!_'], $valor
        ).'%';

        return $query->where(function ($consulta) use ($termino, $numeros, $usuario, $patron) {
            $consulta->whereRaw("nombre LIKE ? ESCAPE '!'", [$patron($termino)])
                ->orWhereRaw("email LIKE ? ESCAPE '!'", [$patron($termino)]);

            if ($usuario !== null) {
                $consulta->orWhereRaw("usuario LIKE ? ESCAPE '!'", [$patron($usuario)]);
            }

            if ($numeros !== '') {
                $consulta->orWhere('telefono', 'like', "%{$numeros}%")
                    ->orWhereRaw(
                        "CONCAT(COALESCE(codigo_pais, ''), COALESCE(telefono, '')) LIKE ?",
                        ["%{$numeros}%"]
                    );
            }
        });
    }

    public function scopeDatosIncompletos($query)
    {
        return $query->where(function ($grupo) {
            $grupo->where(function ($consulta) {
                $consulta->whereNull('nombre')->orWhere('nombre', '');
            })->orWhere(function ($consulta) {
                $consulta->where(function ($q) {
                    $q->whereNull('email')->orWhere('email', '');
                })->where(function ($q) {
                    $q->whereNull('usuario')->orWhere('usuario', '');
                });
            });
        });
    }

    public function scopeDatosCompletos($query)
    {
        return $query->whereNotNull('nombre')->where('nombre', '!=', '')
            ->where(function ($consulta) {
                $consulta->where(function ($q) {
                    $q->whereNotNull('email')->where('email', '!=', '');
                })->orWhere(function ($q) {
                    $q->whereNotNull('usuario')->where('usuario', '!=', '');
                });
            });
    }
}
