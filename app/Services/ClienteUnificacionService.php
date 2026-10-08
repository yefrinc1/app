<?php

namespace App\Services;

use App\Models\Cliente;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ClienteUnificacionService
{
    private const TABLAS = ['pedidos', 'ventas', 'instalacion_evidencias', 'portal_juego_accesos'];
    private const CAMPOS = ['nombre', 'codigo_pais', 'telefono', 'usuario', 'email', 'notas'];

    public function vistaPrevia(int $principalId, int $duplicadoId): array
    {
        [$principal, $duplicado] = $this->clientes($principalId, $duplicadoId);
        return $this->resumen($principal, $duplicado);
    }

    private function clientes(int $principalId, int $duplicadoId, bool $bloquear = false): array
    {
        if ($principalId === $duplicadoId) {
            throw ValidationException::withMessages(['clientes' => 'Selecciona dos clientes distintos.']);
        }

        $query = Cliente::query()->whereIn('id', [$principalId, $duplicadoId])->orderBy('id');
        if ($bloquear) {
            $query->lockForUpdate();
        }
        $clientes = $query->get()->keyBy('id');
        $principal = $clientes->get($principalId);
        $duplicado = $clientes->get($duplicadoId);

        if (!$principal || !$duplicado) {
            throw ValidationException::withMessages(['clientes' => 'Un cliente ya fue archivado o no existe. Vuelve a buscarlo.']);
        }
        if ($principal->user_id && $duplicado->user_id) {
            throw ValidationException::withMessages(['clientes' => 'Ambos clientes tienen cuenta del portal. Debes revisar sus accesos antes de unificarlos.']);
        }

        return [$principal, $duplicado];
    }

    private function conteos(int $id): array
    {
        $conteos = [];
        foreach (self::TABLAS as $tabla) {
            $conteos[$tabla] = Schema::hasTable($tabla)
                ? DB::table($tabla)->where('cliente_id', $id)->count()
                : 0;
        }
        return $conteos;
    }

    private function datos(Cliente $cliente): array
    {
        $datos = $cliente->only(array_merge(['id', 'user_id'], self::CAMPOS));
        $datos['portal_activo'] = (bool) $cliente->user_id;
        $datos['correo_acceso'] = $cliente->cuentaPortal?->email;
        return $datos;
    }

    private function firma(Cliente $principal, Cliente $duplicado): string
    {
        return hash('sha256', json_encode([
            $principal->getAttributes(), $duplicado->getAttributes(),
            $this->conteos($principal->id), $this->conteos($duplicado->id),
        ], JSON_THROW_ON_ERROR));
    }

    private function resumen(Cliente $principal, Cliente $duplicado): array
    {
        return [
            'principal' => $this->datos($principal),
            'duplicado' => $this->datos($duplicado),
            'transferencias' => $this->conteos($duplicado->id),
            'firma' => $this->firma($principal, $duplicado),
            'correo_acceso' => ($principal->user_id ? $principal : $duplicado)->cuentaPortal?->email,
        ];
    }

    public function unificar(array $datos, int $operadorId): int
    {
        return DB::transaction(function () use ($datos, $operadorId) {
            [$principal, $duplicado] = $this->clientes(
                (int) $datos['principal_id'], (int) $datos['duplicado_id'], true
            );

            if (!hash_equals($this->firma($principal, $duplicado), $datos['firma'])) {
                throw ValidationException::withMessages(['clientes' => 'Los registros cambiaron. Revisa nuevamente la vista previa.']);
            }

            // Detiene la operación si el proyecto añadió relaciones no cubiertas.
            $referencias = DB::select(
                "SELECT TABLE_NAME AS tabla, COLUMN_NAME AS columna
                 FROM information_schema.KEY_COLUMN_USAGE
                 WHERE TABLE_SCHEMA = DATABASE() AND REFERENCED_TABLE_NAME = 'clientes'"
            );
            foreach ($referencias as $referencia) {
                if (!in_array($referencia->tabla, self::TABLAS, true) || $referencia->columna !== 'cliente_id') {
                    throw ValidationException::withMessages(['clientes' => 'Existe una relación adicional en '.$referencia->tabla.'. Debe integrarse antes de unificar.']);
                }
            }
            $columnas = DB::select(
                "SELECT TABLE_NAME AS tabla FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE() AND COLUMN_NAME = 'cliente_id'"
            );
            foreach ($columnas as $columna) {
                if (!in_array($columna->tabla, self::TABLAS, true)) {
                    throw ValidationException::withMessages(['clientes' => 'Existe una tabla adicional con cliente_id: '.$columna->tabla.'. Debe integrarse antes de unificar.']);
                }
            }

            $elegidos = [];
            foreach (['nombre', 'usuario', 'email'] as $campo) {
                $origen = $datos['selecciones'][$campo] === 'principal' ? $principal : $duplicado;
                $elegidos[$campo] = $origen->$campo;
            }
            $telefono = $datos['selecciones']['telefono'] === 'principal' ? $principal : $duplicado;
            $elegidos['telefono'] = preg_replace('/\D+/', '', (string) $telefono->telefono) ?: null;
            $elegidos['codigo_pais'] = preg_replace('/\D+/', '', (string) $telefono->codigo_pais) ?: null;
            $elegidos['usuario'] = Cliente::normalizarUsuario($elegidos['usuario']);
            $elegidos['email'] = !empty($elegidos['email']) ? mb_strtolower(trim($elegidos['email']), 'UTF-8') : null;
            $notas = array_filter(array_unique([$principal->notas, $duplicado->notas]));
            $elegidos['notas'] = implode("\n\n", $notas) ?: null;

            $reglas = [];
            foreach (['telefono', 'usuario', 'email'] as $campo) {
                $reglas[$campo] = ['nullable', Rule::unique('clientes', $campo)
                    ->where(fn ($q) => $q->whereNotIn('id', [$principal->id, $duplicado->id])->whereNull('deleted_at'))];
            }
            Validator::make($elegidos, $reglas, [
                'telefono.unique' => 'El teléfono elegido pertenece a un tercer cliente.',
                'usuario.unique' => 'El usuario elegido pertenece a un tercer cliente.',
                'email.unique' => 'El correo elegido pertenece a un tercer cliente.',
            ])->validate();

            $antes = ['principal' => $this->datos($principal), 'duplicado' => $this->datos($duplicado)];
            $transferidas = [];
            foreach (self::TABLAS as $tabla) {
                $transferidas[$tabla] = Schema::hasTable($tabla)
                    ? DB::table($tabla)->where('cliente_id', $duplicado->id)->update(['cliente_id' => $principal->id])
                    : 0;
            }

            $userId = $principal->user_id ?: $duplicado->user_id;
            $activatedAt = $principal->user_id ? $principal->portal_activated_at : $duplicado->portal_activated_at;

            // Libera los identificadores antes de asignarlos al registro conservado.
            DB::table('clientes')->where('id', $duplicado->id)->update([
                'telefono' => null, 'usuario' => null, 'email' => null, 'user_id' => null,
                'portal_token_hash' => null, 'portal_token_expires_at' => null,
                'portal_activated_at' => null, 'deleted_at' => now(), 'updated_at' => now(),
            ]);
            $principal->fill($elegidos);
            $principal->user_id = $userId;
            $principal->portal_activated_at = $activatedAt;
            $principal->portal_token_hash = null;
            $principal->portal_token_expires_at = null;
            $principal->save();

            DB::table('cliente_unificaciones')->insert([
                'principal_id_original' => $principal->id,
                'duplicado_id_original' => $duplicado->id,
                'operador_id_original' => $operadorId,
                'motivo' => $datos['motivo'],
                'antes' => json_encode($antes, JSON_THROW_ON_ERROR),
                'despues' => json_encode($this->datos($principal->fresh()), JSON_THROW_ON_ERROR),
                'transferencias' => json_encode($transferidas, JSON_THROW_ON_ERROR),
                'created_at' => now(), 'updated_at' => now(),
            ]);

            return $principal->id;
        }, 3);
    }
}
