<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Cliente;
use Illuminate\Database\QueryException;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Hash;
use App\Notifications\VerificarCorreoPortal;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class PortalCorreoVerificacionController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    private function cliente(Request $request)
    {
        abort_unless($request->user()->hasRole('cliente'), 403);
        return $request->user()->cliente()->firstOrFail();
    }

    public function notice(Request $request)
    {
        $cliente = $this->cliente($request);
        if (!$cliente->portal_requiere_verificacion_email || $request->user()->hasVerifiedEmail()) {
            return redirect()->route('portal.index');
        }

        return Inertia::render('PortalCliente/VerificarCorreo', [
            'email' => $request->user()->email,
            'enviado' => (bool) session('portal_correo_enviado'),
            'errorCorreo' => session('portal_correo_error'),
        ]);
    }

    public function send(Request $request)
    {
        $cliente = $this->cliente($request);
        if (!$cliente->portal_requiere_verificacion_email || $request->user()->hasVerifiedEmail()) {
            return redirect()->route('portal.index');
        }

        try {
            $request->user()->notify(new VerificarCorreoPortal);
        } catch (\Throwable $error) {
            report($error);
            if ($request->expectsJson()) {
                return response()->json(['message' => 'No pudimos enviar el correo. Tu cuenta sigue creada; intenta reenviarlo o comunícate con soporte.'], 422);
            }
            return back()->with('portal_correo_error', 'No pudimos enviar el correo. Tu cuenta sigue creada; intenta reenviarlo o comunícate con soporte.');
        }

        if ($request->expectsJson()) {
            return response()->json(['enviado' => true]);
        }
        return back()->with('portal_correo_enviado', true);
    }


    public function change(Request $request)
    {
        $clienteInicial = $this->cliente($request);
        $request->merge([
            'email' => mb_strtolower(trim((string) $request->input('email')), 'UTF-8'),
            'email_confirmation' => mb_strtolower(trim((string) $request->input('email_confirmation')), 'UTF-8'),
        ]);
        $datos = $request->validate([
            'email' => ['required', 'string', 'email', 'max:150', 'confirmed'],
            'current_password' => ['required', 'current_password:web'],
        ], [
            'email.confirmed' => 'Los dos correos no coinciden.',
            'email.email' => 'Ingresa un correo válido.',
            'current_password.current_password' => 'La contraseña actual no es correcta.',
            'current_password.required' => 'Confirma tu contraseña para cambiar el correo.',
        ]);

        try {
            $user = DB::transaction(function () use ($request, $clienteInicial, $datos) {
                // Mismo orden de bloqueo que activación y unificación.
                $cliente = Cliente::query()->lockForUpdate()->findOrFail($clienteInicial->id);
                $user = User::query()->lockForUpdate()->findOrFail($request->user()->id);
                abort_unless((int) $cliente->user_id === (int) $user->id, 403);
                if (!Hash::check($request->input('current_password'), $user->password)) {
                    throw ValidationException::withMessages(['current_password' => 'La contraseña actual no es correcta.']);
                }

                if (!$cliente->portal_requiere_verificacion_email || $user->hasVerifiedEmail()) {
                    throw ValidationException::withMessages([
                        'email' => 'Esta opción solo está disponible mientras el correo está pendiente de verificación.',
                    ]);
                }
                if ($user->email === $datos['email']) {
                    throw ValidationException::withMessages(['email' => 'Ingresa un correo diferente o usa Reenviar correo.']);
                }

                Validator::make($datos, [
                    'email' => [
                        Rule::unique('users', 'email')->ignore($user->id),
                        Rule::unique('clientes', 'email')->ignore($cliente->id),
                    ],
                ], ['email.unique' => 'Ese correo ya pertenece a otra cuenta o cliente.'])->validate();

                $user->forceFill(['email' => $datos['email'], 'email_verified_at' => null])->save();
                $cliente->forceFill(['email' => $datos['email']])->save();
                return $user;
            });
        } catch (QueryException $error) {
            if (($error->errorInfo[0] ?? null) === '23000') {
                throw ValidationException::withMessages(['email' => 'No se pudo guardar ese correo porque ya está en uso.']);
            }
            throw $error;
        }

        // No revierte el cambio de dirección si falla SMTP.
        try {
            $user->notify(new VerificarCorreoPortal);
        } catch (\Throwable $error) {
            report($error);
            return response()->json([
                'email' => $user->email, 'enviado' => false,
                'message' => 'El correo se actualizó, pero no pudimos enviar el enlace. Usa Reenviar correo o comunícate con soporte.',
            ]);
        }

        return response()->json([
            'email' => $user->email, 'enviado' => true,
            'message' => 'Correo actualizado. Revisa el enlace que enviamos a la nueva dirección.',
        ]);
    }

    public function verify(Request $request, int $id, string $hash)
    {
        $this->cliente($request);
        abort_unless($id === (int) $request->user()->id, 403);

        if (!$request->hasValidSignature()) {
            return redirect()->route('portal.correo.notice')
                ->with('portal_correo_error', 'El enlace venció o no es válido. Solicita uno nuevo.');
        }

        $cambio = DB::transaction(function () use ($id, $hash) {
            $user = User::query()->lockForUpdate()->findOrFail($id);
            if (!hash_equals(sha1($user->email), $hash)) {
                return null;
            }
            return !$user->hasVerifiedEmail() && $user->markEmailAsVerified();
        });

        if ($cambio === null) {
            return redirect()->route('portal.correo.notice')
                ->with('portal_correo_error', 'Este enlace corresponde a un correo anterior. Verifica tu dirección actual o solicita otro enlace.');
        }

        if ($cambio) {
            event(new Verified($request->user()->fresh()));
        }

        $request->session()->forget('url.intended');
        return redirect()->route('portal.index')
            ->with('success', 'Correo verificado. Ya puedes consultar tus juegos.');
    }
}
