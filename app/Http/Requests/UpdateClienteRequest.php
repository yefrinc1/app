<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateClienteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('clientes.editar') ?? false;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'nombre' => $this->limpiarTexto($this->input('nombre')),
            'codigo_pais' => $this->soloNumeros($this->input('codigo_pais')),
            'telefono' => $this->soloNumeros($this->input('telefono')),
            'usuario' => $this->normalizarUsuario($this->input('usuario')),
            'email' => $this->normalizarEmail($this->input('email')),
            'notas' => $this->limpiarTexto($this->input('notas')),
        ]);
    }

    public function rules(): array
    {
        $cliente = $this->route('cliente');

        return [
            'nombre' => ['nullable', 'string', 'max:120', 'required_without_all:telefono,usuario,email'],
            'codigo_pais' => ['nullable', 'string', 'max:5', 'regex:/^[0-9]+$/'],
            'telefono' => ['nullable', 'string', 'max:20', 'regex:/^[0-9]+$/', Rule::unique('clientes', 'telefono')->ignore($cliente?->id)->whereNull('deleted_at')],
            'usuario' => ['nullable', 'string', 'max:100', Rule::unique('clientes', 'usuario')->ignore($cliente?->id)->whereNull('deleted_at')],
            'email' => ['nullable', 'email', 'max:150', Rule::unique('clientes', 'email')->ignore($cliente?->id)->whereNull('deleted_at')],
            'notas' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function messages(): array
    {
        return [
            'nombre.required_without_all' => 'Registra por lo menos el nombre, teléfono, Instagram o correo.',
            'codigo_pais.regex' => 'El indicativo solo puede contener números.',
            'telefono.regex' => 'El teléfono solo puede contener números.',
            'telefono.unique' => 'Ya existe otro cliente con este teléfono.',
            'usuario.unique' => 'Ya existe otro cliente con este usuario de Instagram.',
            'email.unique' => 'Ya existe otro cliente con este correo.',
        ];
    }

    private function limpiarTexto(mixed $valor): ?string
    {
        $valor = trim((string) ($valor ?? ''));
        return $valor === '' ? null : $valor;
    }

    private function soloNumeros(mixed $valor): ?string
    {
        $valor = preg_replace('/\D+/', '', (string) ($valor ?? ''));
        return $valor === '' ? null : $valor;
    }

    private function normalizarUsuario(mixed $valor): ?string
    {
        $valor = ltrim(strtolower(trim((string) ($valor ?? ''))), '@');
        return $valor === '' ? null : $valor;
    }

    private function normalizarEmail(mixed $valor): ?string
    {
        $valor = strtolower(trim((string) ($valor ?? '')));
        return $valor === '' ? null : $valor;
    }
}
