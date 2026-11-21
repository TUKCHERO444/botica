<?php

namespace App\Http\Requests\Admin;

use App\Models\AjusteInventario;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReporteRequest extends FormRequest
{
    public const TIPOS = [
        'ventas',
        'atenciones',
        'ingresos',
        'caja',
        'inventario',
        'lotes',
        'movimientos',
        'rentabilidad',
        'kardex',
        'clientes',
        'general',
        'auditoria',
        'pagos',
        'ventas_producto',
        'ventas_diario',
        'ventas_vendedor',
        'anulaciones',
        'compras_proveedor',
        'costos_producto',
        'devoluciones_proveedor',
        'merma',
        'rotacion',
        'margen_agrupado',
        'servicios',
        'clientes_rfm',
        'clientes_web',
        'cumpleanos',
        'sugerido_compra',
        'caja_origen',
        'resumen_gerencial',
    ];

    public const TIPOS_MOVIMIENTO = [
        'INGRESO',
        'VENTA',
        'ATENCION',
        'AJUSTE',
        'BAJA',
        'DEVOLUCION',
        'REVERSION',
    ];

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'tipo' => ['required', 'string', Rule::in(self::TIPOS)],
            'desde' => ['nullable', 'date'],
            'hasta' => ['nullable', 'date'],
            'mes' => ['nullable', 'integer', 'between:1,12'],
            'anio' => ['nullable', 'integer', 'between:2000,2100'],
            'usuario_id' => ['nullable', 'integer', 'exists:users,id'],
            'cliente_id' => ['nullable', 'integer', 'exists:cliente,id_cliente'],
            'proveedor_id' => ['nullable', 'integer', 'exists:proveedor,id_proveedor'],
            'producto_id' => ['nullable', 'integer', 'exists:producto,id_producto'],
            'categoria_id' => ['nullable', 'integer', 'exists:categoria,id_categoria'],
            'marca_id' => ['nullable', 'integer', 'exists:marca,id_marca'],
            'caja_id' => ['nullable', 'integer', 'exists:caja,id_caja'],
            'metodo_pago_id' => ['nullable', 'integer', 'exists:metodo_pago,id_metodo_pago'],
            'stock_menor_que' => ['nullable', 'numeric', 'min:0', 'max:999999'],
            'tipo_movimiento' => ['nullable', 'string', Rule::in(self::TIPOS_MOVIMIENTO)],
            'tipo_especial' => ['nullable', 'string', Rule::in(self::TIPOS_MOVIMIENTO)],
            'dias_vencimiento' => ['nullable', Rule::in(['vencidos', '30', '90', '180', '365'])],
            'limite_clientes' => ['nullable', 'integer', 'between:1,100'],
            'modulo' => ['nullable', 'string', 'max:50'],
            'accion' => ['nullable', 'string', 'max:50'],
            'pago' => ['nullable', 'string', Rule::in(['caja', 'externo'])],
            'tipo_pago' => ['nullable', 'string', Rule::in(['venta', 'atencion'])],
            'solo_activos' => ['nullable', 'boolean'],
            'formato' => ['nullable', 'string', Rule::in(['pdf', 'csv'])],
            'agrupar' => ['nullable', 'string', Rule::in(['categoria', 'marca', 'vendedor'])],
            'segmento' => ['nullable', 'string', Rule::in(['Nuevo', 'Activo', 'En riesgo', 'Inactivo', 'Perdido', 'Sin compras'])],
            'limite_rfm' => ['nullable', 'integer', 'between:1,500'],
            'dias_rotacion' => ['nullable', 'integer', 'between:1,365'],
            'dias_compra' => ['nullable', 'integer', 'between:1,365'],
            'dias_cumpleanos' => ['nullable', 'integer', 'between:1,365'],
            'tipo_ajuste' => ['nullable', 'string', Rule::in(array_keys(AjusteInventario::TIPOS))],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $desde = $this->input('desde');
            $hasta = $this->input('hasta');

            if (! $desde || ! $hasta) {
                return;
            }

            $inicio = strtotime((string) $desde);
            $fin = strtotime((string) $hasta);

            if ($inicio === false || $fin === false) {
                return;
            }

            if ($inicio > $fin) {
                $validator->errors()->add('hasta', 'La fecha "hasta" debe ser mayor o igual que "desde".');
            }

            if (($fin - $inicio) > 86400 * 730) {
                $validator->errors()->add('hasta', 'El rango máximo permitido es de 2 años.');
            }
        });
    }
}
