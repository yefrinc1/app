<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CorreoGlobales extends Model
{
    use HasFactory;

    protected $table = 'correo_globales';

    protected $guarded = [];
}