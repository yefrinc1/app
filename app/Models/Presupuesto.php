<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Presupuesto extends Model
{
    use HasFactory;

    protected $table = 'presupuestos';

    protected $fillable = [
        'anio',
        'mes',
        'ventas_objetivo',
        'ingresos_objetivo',
        'utilidad_objetivo',
        'observaciones',
    ];

    protected $casts = [
        'anio' => 'integer',
        'mes' => 'integer',
        'ventas_objetivo' => 'integer',
        'ingresos_objetivo' => 'decimal:2',
        'utilidad_objetivo' => 'decimal:2',
    ];

    public function getNombreMesAttribute()
    {
        return [
            1 => 'Enero',
            2 => 'Febrero',
            3 => 'Marzo',
            4 => 'Abril',
            5 => 'Mayo',
            6 => 'Junio',
            7 => 'Julio',
            8 => 'Agosto',
            9 => 'Septiembre',
            10 => 'Octubre',
            11 => 'Noviembre',
            12 => 'Diciembre',
        ][$this->mes] ?? '';
    }
}