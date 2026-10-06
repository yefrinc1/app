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
        $termino = trim((string) $termino);

        if ($termino === '') {
            return $query;
        }

        $numeros = preg_replace('/\D/', '', $termino);

        return $query->where(function ($consulta) use ($termino, $numeros) {
            $consulta->where('nombre', 'like', "%{$termino}%")
                ->orWhere('usuario', 'like', "%{$termino}%")
                ->orWhere('email', 'like', "%{$termino}%");

            if ($numeros !== '') {
                $consulta->orWhere('telefono', 'like', "%{$numeros}%");
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
