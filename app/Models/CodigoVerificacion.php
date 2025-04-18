<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CodigoVerificacion extends Model
{
    use HasFactory;

    protected $table = 'codigo_verificacion';

    protected $guarded = [];

    public static function separarCodigos($codigos) {
        $codigosVF = explode("\n", $codigos);
        $codigosOrganizados = array_filter(array_map('trim', $codigosVF), function($value) {
            return $value !== "";
        });
        return $codigosOrganizados;
    }
}
