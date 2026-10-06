<?php

namespace App\Services;

use App\Models\Cliente;
use App\Models\Ventas;
use Illuminate\Support\Str;

class VincularVentasAntiguasService
{
    public function vincularCliente(Cliente $cliente): int
    {
        $vinculadas = 0;

        Ventas::query()
            ->whereNull('cliente_id')
            ->select(['id', 'cliente'])
            ->orderBy('id')
            ->chunkById(250, function ($ventas) use ($cliente, &$vinculadas) {
                foreach ($ventas as $venta) {
                    if ($this->coincide($venta->cliente, $cliente) && $this->coincidenciaEsUnica($venta->cliente, $cliente)) {
                        $vinculadas += Ventas::whereKey($venta->id)->whereNull('cliente_id')->update(['cliente_id' => $cliente->id]);
                    }
                }
            });

        return $vinculadas;
    }

    public function vincularTodas(): array
    {
        $resultado = ['vinculadas' => 0, 'sin_coincidencia' => 0, 'ambiguas' => 0];

        Ventas::query()->whereNull('cliente_id')->select(['id', 'cliente'])->orderBy('id')
            ->chunkById(250, function ($ventas) use (&$resultado) {
                foreach ($ventas as $venta) {
                    $candidatos = $this->buscarCandidatos($venta->cliente);

                    if ($candidatos->count() === 1) {
                        $resultado['vinculadas'] += Ventas::whereKey($venta->id)->whereNull('cliente_id')
                            ->update(['cliente_id' => $candidatos->first()->id]);
                    } elseif ($candidatos->isEmpty()) {
                        $resultado['sin_coincidencia']++;
                    } else {
                        $resultado['ambiguas']++;
                    }
                }
            });

        return $resultado;
    }

    private function buscarCandidatos(?string $identificador)
    {
        $identificador = trim((string) $identificador);
        $numeros = preg_replace('/\D+/', '', $identificador);

        if (strlen($numeros) >= 7 && strlen($numeros) <= 15) {
            $telefono = strlen($numeros) > 10 ? substr($numeros, -10) : $numeros;
            return Cliente::query()->where('telefono', $telefono)->get(['id', 'telefono', 'usuario', 'email']);
        }

        $texto = Str::lower(ltrim($identificador, '@'));
        return Cliente::query()->where(function ($query) use ($texto) {
            $query->whereRaw('LOWER(usuario) = ?', [$texto])
                ->orWhereRaw('LOWER(email) = ?', [$texto]);
        })->get(['id', 'telefono', 'usuario', 'email']);
    }

    private function coincide(?string $identificador, Cliente $cliente): bool
    {
        return $this->buscarCandidatos($identificador)->contains('id', $cliente->id);
    }

    private function coincidenciaEsUnica(?string $identificador, Cliente $cliente): bool
    {
        $candidatos = $this->buscarCandidatos($identificador);
        return $candidatos->count() === 1 && (int) $candidatos->first()->id === (int) $cliente->id;
    }
}
