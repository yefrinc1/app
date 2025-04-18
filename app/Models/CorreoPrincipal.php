<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CorreoPrincipal extends Model
{
    use HasFactory;

    protected $table = 'correo_principal'; // Nombre de la tabla en la base de datos

    protected $fillable = ['id', 'correo', 'contrasena', 'disponible']; // Campos permitidos para asignación masiva
}
