<?php

namespace App\Http\Controllers\Admin\Traits;

use App\Models\Auditoria;
use Illuminate\Database\Eloquent\Model;

trait Audita
{
    protected function auditarCrear(Model $modelo, string $modulo, string $descripcion): void
    {
        Auditoria::create([
            'user_id'        => auth()->id(),
            'modulo'         => $modulo,
            'accion'         => 'crear',
            'registro_id'    => $modelo->getKey(),
            'descripcion'    => $descripcion,
            'valores_nuevos' => $modelo->getAttributes(),
            'ip_address'     => request()->ip(),
        ]);
    }

    protected function auditarActualizar(Model $modelo, string $modulo, string $descripcion): void
    {
        Auditoria::create([
            'user_id'            => auth()->id(),
            'modulo'             => $modulo,
            'accion'             => 'actualizar',
            'registro_id'        => $modelo->getKey(),
            'descripcion'        => $descripcion,
            'valores_anteriores' => $modelo->getOriginal(),
            'valores_nuevos'     => $modelo->getChanges(),
            'ip_address'         => request()->ip(),
        ]);
    }

    protected function auditarEliminar(Model $modelo, string $modulo, string $descripcion): void
    {
        Auditoria::create([
            'user_id'            => auth()->id(),
            'modulo'             => $modulo,
            'accion'             => 'eliminar',
            'registro_id'        => $modelo->getKey(),
            'descripcion'        => $descripcion,
            'valores_anteriores' => $modelo->getOriginal(),
            'ip_address'         => request()->ip(),
        ]);
    }

    protected function auditar(
        string $modulo,
        string $accion,
        ?int $registroId,
        string $descripcion,
        ?array $valoresAnteriores = null,
        ?array $valoresNuevos = null
    ): void {
        Auditoria::create([
            'user_id'            => auth()->id(),
            'modulo'             => $modulo,
            'accion'             => $accion,
            'registro_id'        => $registroId,
            'descripcion'        => $descripcion,
            'valores_anteriores' => $valoresAnteriores,
            'valores_nuevos'     => $valoresNuevos,
            'ip_address'         => request()->ip(),
        ]);
    }
}
