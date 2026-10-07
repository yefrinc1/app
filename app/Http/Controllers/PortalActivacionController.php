<?php

namespace App\Http\Controllers;

use App\Models\Cliente;
use App\Models\User;
use App\Services\VincularVentasAntiguasService;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Role;

class PortalActivacionController extends Controller
{
    public function __construct(private VincularVentasAntiguasService $vinculador)
    {
    }

    public function show(string $token): Response
    {
        $cliente = $this->clienteValido($token);

        return Inertia::render('PortalCliente/Activar', [
            'token' => $token,
            'cliente' => [
                'nombre' => $cliente->nombre,
                'codigo_pais' => $cliente->codigo_pais ?: '57',
                'telefono' => $cliente->telefono,
                'usuario' => $this->normalizarUsuario($cliente->usuario),
                'email' => $cliente->email,
                'contacto' => $this->contactoProtegido($cliente),
            ],
            'expiresAt' => $cliente->portal_token_expires_at?->toIso8601String(),
        ]);
    }

    public function store(Request $request, string $token): RedirectResponse
    {
        $clienteInicial = $this->clienteValido($token);

        $request->merge([
            'name' => trim((string) $request->input('name')),
            'codigo_pais' => preg_replace('/\D/', '', (string) $request->input('codigo_pais')),
            'telefono' => preg_replace('/\D/', '', (string) $request->input('telefono')),
            'usuario' => $this->normalizarUsuario($request->input('usuario')),
            'email' => strtolower(trim((string) $request->input('email'))),
        ]);

        $datos = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'codigo_pais' => ['required', 'digits_between:1,5'],
            'telefono' => [
                'required', 'digits_between:7,15',
                Rule::unique('clientes', 'telefono')->ignore($clienteInicial->id),
            ],
            'usuario' => [
                'required', 'string', 'max:255', 'regex:/^[a-z0-9._]+$/i',
                Rule::unique('clientes', 'usuario')->ignore($clienteInicial->id),
            ],
            'email' => [
                'required', 'string', 'lowercase', 'email', 'max:255',
                Rule::unique('users', 'email'),
                Rule::unique('clientes', 'email')->ignore($clienteInicial->id),
            ],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        $user = DB::transaction(function () use ($clienteInicial, $datos, $token) {
            $cliente = Cliente::query()->lockForUpdate()->findOrFail($clienteInicial->id);

            if ($cliente->user_id || ! hash_equals((string) $cliente->portal_token_hash, hash('sha256', $token))
                || ! $cliente->portal_token_expires_at || $cliente->portal_token_expires_at->isPast()) {
                throw ValidationException::withMessages([
                    'token' => 'Este enlace ya fue utilizado o venció. Solicita uno nuevo a MRJUEGOZ.',
                ]);
            }

            $user = User::create([
                'name' => $datos['name'],
                'email' => strtolower($datos['email']),
                'password' => Hash::make($datos['password']),
            ]);

            Role::firstOrCreate(['name' => 'cliente', 'guard_name' => 'web']);
            $user->assignRole('cliente');

            $cliente->forceFill([
                'nombre' => $datos['name'],
                'codigo_pais' => $datos['codigo_pais'],
                'telefono' => $datos['telefono'],
                'usuario' => $datos['usuario'],
                'email' => $datos['email'],
                'user_id' => $user->id,
                'portal_token_hash' => null,
                'portal_token_expires_at' => null,
                'portal_activated_at' => now(),
            ])->save();

            $this->vinculador->vincularCliente($cliente);
            event(new Registered($user));

            return $user;
        });

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('portal.index')->with('success', 'Tu cuenta fue activada correctamente.');
    }

    private function clienteValido(string $token): Cliente
    {
        $cliente = Cliente::query()->where('portal_token_hash', hash('sha256', $token))->first();

        abort_unless(
            $cliente && ! $cliente->user_id && $cliente->portal_token_expires_at?->isFuture(),
            404,
            'Este enlace de activación no existe, ya fue utilizado o venció.'
        );

        return $cliente;
    }

    private function contactoProtegido(Cliente $cliente): string
    {
        if ($cliente->telefono) {
            return 'Teléfono terminado en '.substr($cliente->telefono, -4);
        }

        $usuario = $this->normalizarUsuario($cliente->usuario);
        if ($usuario !== '') {
            return '@'.mb_substr($usuario, 0, 2, 'UTF-8').'***';
        }

        return 'Ficha de cliente #'.$cliente->id;
    }

    private function normalizarUsuario(?string $usuario): string
    {
        return Cliente::normalizarUsuario($usuario) ?? '';
    }
}
