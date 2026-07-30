<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules;
use Inertia\Inertia;

class AgregarUsuarioController extends Controller
{
    public function __construct()
    {
        $this->middleware('can:usuarios.crear', ['only' => ['create', 'store']]);
    }
    
    public function create()
    {
        return Inertia::render('AgregarUsuario');
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|lowercase|email|max:255|unique:'.User::class,
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        $usuario = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
        ]);

        $usuario->assignRole('asesor');

        return redirect(route('dashboard', ['mensaje' => 'Usuario creado con exito']));
    }
}
