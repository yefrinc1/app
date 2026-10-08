<?php

namespace App\Http\Controllers;

use App\Models\User;
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
            abort_unless(hash_equals(sha1($user->email), $hash), 403);
            return !$user->hasVerifiedEmail() && $user->markEmailAsVerified();
        });

        if ($cambio) {
            event(new Verified($request->user()->fresh()));
        }

        $request->session()->forget('url.intended');
        return redirect()->route('portal.index')
            ->with('success', 'Correo verificado. Ya puedes consultar tus juegos.');
    }
}
