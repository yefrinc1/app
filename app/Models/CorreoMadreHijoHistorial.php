<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CorreoMadreHijoHistorial extends Model
{
    protected $table = 'correo_madre_hijos_historial';

    protected $fillable = [
        'correo_madre_id', 'correo_juego_id', 'es_cuenta_madre', 'correo',
        'contrasena', 'juego', 'fecha_nacimiento', 'accion', 'usuario_id',
    ];

    protected $casts = [
        'es_cuenta_madre' => 'boolean',
        'fecha_nacimiento' => 'datetime',
    ];

    public function madre()
    {
        return $this->belongsTo(CorreoMadre::class, 'correo_madre_id');
    }

    public function usuario()
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }
}
