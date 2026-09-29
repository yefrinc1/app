<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Cliente extends Model
{
    use SoftDeletes;

    protected $table = 'clientes';

    protected $fillable = [
        'nombre', 'codigo_pais', 'telefono', 'usuario', 'email', 'notas',
    ];

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
}
