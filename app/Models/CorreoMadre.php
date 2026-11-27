<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CorreoMadre extends Model
{
    use HasFactory;

    protected $table = 'correo_madre';

    protected $guarded = [];

    public const CUPO_HIJOS = 6;

    public function correosJuegos()
    {
        return $this->hasMany(CorreoJuego::class, 'id_correo_madre');
    }

    public function historialHijos()
    {
        return $this->hasMany(CorreoMadreHijoHistorial::class, 'correo_madre_id')->latest();
    }

    public function cuentaComoHijo()
    {
        return $this->hasOne(CorreoJuego::class, 'id_correo_madre')->where('es_cuenta_madre', 1);
    }

    public function codigosVerificacion()
    {
        return $this->hasMany(CodigoVerificacion::class, 'id_correo_madre');
    }

    public function cantidadHijosReales(): int
    {
        return $this->correosJuegos()->hijosReales()->count();
    }

    public function sincronizarDisponibilidad(): void
    {
        $this->update([
            'disponible' => $this->cantidadHijosReales() < self::CUPO_HIJOS ? 1 : 0,
        ]);
    }

    public function registrarHijoHistorial(CorreoJuego $hijo, string $accion, ?int $usuarioId = null): void
    {
        CorreoMadreHijoHistorial::create([
            'correo_madre_id' => $this->id,
            'correo_juego_id' => $hijo->id,
            'es_cuenta_madre' => (bool) $hijo->es_cuenta_madre,
            'correo' => $hijo->correo,
            'contrasena' => $hijo->contrasena,
            'juego' => $hijo->juego,
            'fecha_nacimiento' => $hijo->fecha_nacimiento,
            'accion' => $accion,
            'usuario_id' => $usuarioId,
        ]);
    }
}
