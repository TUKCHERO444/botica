<?php

namespace Database\Seeders;

use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class TablasBaseSeeder extends Seeder
{
    public function run(): void
    {
        $now = Carbon::now();
        $adminId = 1;
        $vendedorId = 2;

        // ── 1. CATEGORIAS ──
        $categorias = [
            ['nombre' => 'Medicamentos',     'descripcion' => 'Medicamentos genéricos y de marca', 'cantidad' => 0, 'estado' => 1],
            ['nombre' => 'Cuidado Personal', 'descripcion' => 'Productos de higiene y cuidado',    'cantidad' => 0, 'estado' => 1],
            ['nombre' => 'Bebés',            'descripcion' => 'Pañales, leche y accesorios',       'cantidad' => 0, 'estado' => 1],
            ['nombre' => 'Vitaminas',        'descripcion' => 'Suplementos vitamínicos',           'cantidad' => 0, 'estado' => 1],
            ['nombre' => 'Primeros Auxilios', 'descripcion' => 'Vendas, gasas y curitas',           'cantidad' => 0, 'estado' => 1],
            ['nombre' => 'Higiene',          'descripcion' => 'Jabones, shampoos y desodorantes',  'cantidad' => 0, 'estado' => 1],
        ];
        $catIds = [];
        foreach ($categorias as $c) {
            $catIds[$c['nombre']] = DB::table('categoria')->insertGetId($c);
        }

        // ── 2. MARCAS ──
        $marcas = [
            ['nombre' => 'Genérico',          'descripcion' => 'Medicamento genérico',  'cantidad' => 0, 'estado' => 1],
            ['nombre' => 'Bayer',             'descripcion' => 'Laboratorio Bayer',     'cantidad' => 0, 'estado' => 1],
            ['nombre' => 'Pfizer',            'descripcion' => 'Laboratorio Pfizer',    'cantidad' => 0, 'estado' => 1],
            ['nombre' => 'Johnson & Johnson', 'descripcion' => 'Cuidado personal',     'cantidad' => 0, 'estado' => 1],
            ['nombre' => 'Natura',            'descripcion' => 'Cosméticos naturales',  'cantidad' => 0, 'estado' => 1],
            ['nombre' => 'Vichy',             'descripcion' => 'Dermocosmética',        'cantidad' => 0, 'estado' => 1],
        ];
        $marcaIds = [];
        foreach ($marcas as $m) {
            $marcaIds[$m['nombre']] = DB::table('marca')->insertGetId($m);
        }

        // ── 3. CLIENTES ──
        $clientes = [
            ['dni' => '12345678', 'nombre' => 'Ana',           'apellido_paterno' => 'López',   'apellido_materno' => 'García'],
            ['dni' => '23456789', 'nombre' => 'Carlos',        'apellido_paterno' => 'Mendoza', 'apellido_materno' => 'Ríos'],
            ['dni' => '34567890', 'nombre' => 'María',         'apellido_paterno' => 'Torres',  'apellido_materno' => null],
            ['dni' => '45678901', 'nombre' => 'Pedro',         'apellido_paterno' => 'Díaz',    'apellido_materno' => 'Paredes'],
            ['dni' => '56789012', 'nombre' => 'Rosa',          'apellido_paterno' => 'Quispe',  'apellido_materno' => null],
            ['dni' => null,       'nombre' => 'Cliente Generico', 'apellido_paterno' => null,    'apellido_materno' => null],
        ];
        $clienteIds = [];
        foreach ($clientes as $c) {
            $clienteIds[] = DB::table('cliente')->insertGetId($c);
        }

        // ── 4. PROVEEDORES ──
        $proveedores = [
            ['ruc' => '20100088880', 'razon_social' => 'Distribuidora Farmacéutica EIRL',    'condicion' => 'Habilitado', 'estado' => 1],
            ['ruc' => '20100123456', 'razon_social' => 'Laboratorios Peruanos SA',          'condicion' => 'Habilitado', 'estado' => 1],
            ['ruc' => '20456789012', 'razon_social' => 'Importadora Médica del Perú SAC',   'condicion' => 'Habilitado', 'estado' => 1],
            ['ruc' => '20512345678', 'razon_social' => 'Comercializadora de Insumos EIRL',  'condicion' => 'Habilitado', 'estado' => 1],
        ];
        $provIds = [];
        foreach ($proveedores as $p) {
            $provIds[] = DB::table('proveedor')->insertGetId($p);
        }

        // ── 5. SERVICIOS ──
        $servicios = [
            ['nombre' => 'Toma de presión',    'descripcion' => 'Control de presión arterial',                 'precio' => 5.00],
            ['nombre' => 'Vacunación',          'descripcion' => 'Aplicación de vacunas',                       'precio' => 25.00],
            ['nombre' => 'Control de glucosa',  'descripcion' => 'Medición de nivel de glucosa',               'precio' => 8.00],
            ['nombre' => 'Inyección',           'descripcion' => 'Aplicación de inyección intramuscular',      'precio' => 7.00],
        ];
        $servIds = [];
        foreach ($servicios as $s) {
            $servIds[] = DB::table('servicio')->insertGetId($s);
        }

        // ── 6. PRODUCTOS ──
        // Each product has: [name, desc, precio_compra, precio_venta, categoria_key, marca_key, unidad_codigo]
        $productDefs = [
            ['Paracetamol 500mg',  'Analgésico y antipirético',      0.50,  1.00, 'Medicamentos',      'Genérico', 'TAB'],
            ['Ibuprofeno 400mg',   'Antiinflamatorio',              0.80,  1.50, 'Medicamentos',      'Bayer', 'TAB'],
            ['Amoxicilina 500mg',  'Antibiótico oral',              1.20,  2.50, 'Medicamentos',      'Pfizer', 'TAB'],
            ['Pañales x50',        'Pañales desechables talla M',  18.00, 25.00, 'Bebés',            'Johnson & Johnson', 'UND'],
            ['Vitamina C 1000mg',  'Suplemento de vitamina C',      10.00, 18.00, 'Vitaminas',         'Natura', 'TAB'],
            ['Alcohol 70° 1L',     'Alcohol desinfectante',          4.00,  7.00, 'Primeros Auxilios', 'Genérico', 'UND'],
            ['Jabón antibacterial', 'Jabón líquido antibacterial',     5.00,  9.00, 'Higiene',           'Natura', 'UND'],
            ['Shampoo anticaspa',  'Shampoo tratamiento anticaspa', 12.00, 22.00, 'Higiene',           'Vichy', 'UND'],
            ['Curitas x20',        'Curitas adhesivas',              2.00,  3.50, 'Primeros Auxilios', 'Johnson & Johnson', 'UND'],
            ['Multivitamínico',    'Complejo multivitamínico',       25.00, 40.00, 'Vitaminas',         'Bayer', 'TAB'],
        ];

        $unidadIds = DB::table('unidad_medida')->pluck('id_unidad_medida', 'codigo');
        $presentacionBaseIds = DB::table('unidad_medida')->pluck('presentacion_base_id', 'codigo');
        $presentacionIds = DB::table('presentacion')->pluck('id_presentacion', 'nombre');

        // Insert products with temporary inventario=0, stock=0 (will update later)
        $prodIds = [];
        $productoPresentacionBaseIds = [];
        foreach ($productDefs as $indice => $p) {
            $prodId = DB::table('producto')->insertGetId([
                'nombre' => $p[0],
                'descripcion' => $p[1],
                'precio_compra' => $p[2],
                'precio_venta' => $p[3],
                'estado' => 1,
                'inventario' => 0,
                'stock' => 0,
                'stock_minimo' => $p[6] === 'TAB' ? 20 : 5,
                'unidad_base_id' => $unidadIds[$p[6]],
                'categoria_id_categoria' => $catIds[$p[4]],
                'marca_id_marca' => $marcaIds[$p[5]],
            ]);

            $prodIds[] = $prodId;

            $productoPresentacionBaseIds[$indice] = DB::table('producto_presentacion')->insertGetId([
                'producto_id_producto' => $prodId,
                'presentacion_id' => $presentacionBaseIds[$p[6]],
                'factor' => 1,
                'precio_venta' => $p[3],
                'paso' => 1,
                'es_base' => true,
                'activo' => true,
                'orden' => 0,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            if ($p[6] === 'TAB' && $indice < 3) {
                DB::table('producto_presentacion')->insert([
                    [
                        'producto_id_producto' => $prodId,
                        'presentacion_id' => $presentacionIds['Blíster'],
                        'factor' => 10,
                        'precio_venta' => round($p[3] * 10, 2),
                        'paso' => 1,
                        'es_base' => false,
                        'activo' => true,
                        'orden' => 1,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ],
                    [
                        'producto_id_producto' => $prodId,
                        'presentacion_id' => $presentacionIds['Caja'],
                        'factor' => 100,
                        'precio_venta' => round($p[3] * 100 * 0.9, 2),
                        'paso' => 1,
                        'es_base' => false,
                        'activo' => true,
                        'orden' => 2,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ],
                ]);
            }
        }

        $productoIngresado = array_fill(0, count($productDefs), 0);
        $productoConsumido = array_fill(0, count($productDefs), 0);
        $movimientos = [];
        $indiceMov = 0;

        // ── 7. INGRESOS ──
        $ingresoBase = Carbon::today()->subDays(60);

        $ingresosData = [
            // CMP-00000001: first purchase from prov[0], 60 days ago
            [
                'correlativo' => 'CMP-00000001',
                'proveedor_id' => $provIds[0],
                'fecha' => $ingresoBase->toDateString(),
                'user_id' => $adminId,
                'detalle' => [
                    ['producto_idx' => 0, 'cantidad' => 200],
                    ['producto_idx' => 1, 'cantidad' => 150],
                    ['producto_idx' => 2, 'cantidad' => 100],
                    ['producto_idx' => 6, 'cantidad' => 80],
                ],
            ],
            // CMP-00000002: second purchase from prov[1], 45 days ago
            [
                'correlativo' => 'CMP-00000002',
                'proveedor_id' => $provIds[1],
                'fecha' => $ingresoBase->copy()->addDays(15)->toDateString(),
                'user_id' => $adminId,
                'detalle' => [
                    ['producto_idx' => 0, 'cantidad' => 200],
                    ['producto_idx' => 1, 'cantidad' => 150],
                    ['producto_idx' => 2, 'cantidad' => 100],
                    ['producto_idx' => 7, 'cantidad' => 60],
                ],
            ],
            // CMP-00000003: third purchase from prov[2], 2 days ago
            [
                'correlativo' => 'CMP-00000003',
                'proveedor_id' => $provIds[2],
                'fecha' => Carbon::today()->subDays(2)->toDateString(),
                'user_id' => $adminId,
                'detalle' => [
                    ['producto_idx' => 4, 'cantidad' => 100],
                    ['producto_idx' => 5, 'cantidad' => 80],
                    ['producto_idx' => 8, 'cantidad' => 150],
                    ['producto_idx' => 9, 'cantidad' => 40],
                    ['producto_idx' => 3, 'cantidad' => 50],
                ],
            ],
        ];

        foreach ($ingresosData as $indiceIngreso => $ingData) {
            $total = 0;
            foreach ($ingData['detalle'] as $det) {
                $total += $det['cantidad'] * $productDefs[$det['producto_idx']][2];
            }

            $documento = 'F001-'.str_pad((string) ($indiceIngreso + 1), 8, '0', STR_PAD_LEFT);

            $ingId = DB::table('ingreso')->insertGetId([
                'correlativo' => $ingData['correlativo'],
                'estado' => 'recibida',
                'proveedor_id_proveedor' => $ingData['proveedor_id'],
                'fecha' => $ingData['fecha'],
                'tipo_documento' => 'factura',
                'numero_documento' => $documento,
                'subtotal' => $total,
                'total' => $total,
                'fecha_recepcion' => $ingData['fecha'],
                'recepcion_user_id' => $ingData['user_id'],
                'pagado_desde_caja' => false,
                'user_id' => $ingData['user_id'],
                'created_at' => Carbon::parse($ingData['fecha']),
                'updated_at' => Carbon::parse($ingData['fecha']),
            ]);

            $detalleRows = [];
            foreach ($ingData['detalle'] as $det) {
                $prodIdx = $det['producto_idx'];
                $costo = $productDefs[$prodIdx][2];

                $detalleRows[] = [
                    'ingreso_id_ingreso' => $ingId,
                    'producto_id_producto' => $prodIds[$prodIdx],
                    'producto_presentacion_id' => $productoPresentacionBaseIds[$prodIdx],
                    'cantidad' => $det['cantidad'],
                    'factor' => 1,
                    'cantidad_base' => $det['cantidad'],
                    'costo_unitario' => $costo,
                    'costo_unitario_base' => $costo,
                    'costo_total' => round($det['cantidad'] * $costo, 2),
                ];

                $productoIngresado[$prodIdx] += $det['cantidad'];

                $movimientos[] = [
                    'indice' => $indiceMov++,
                    'ts' => Carbon::parse($ingData['fecha']),
                    'producto_id' => $prodIds[$prodIdx],
                    'usuario_id' => $ingData['user_id'],
                    'tipo_movimiento' => 'INGRESO',
                    'referencia_tipo' => 'Compra',
                    'referencia_id' => $ingId,
                    'referencia_codigo' => $ingData['correlativo'],
                    'cantidad' => $det['cantidad'],
                    'observacion' => 'Recepción de compra',
                ];
            }
            DB::table('detalle_ingreso')->insert($detalleRows);
        }

        // ── 8. VENTAS ──
        $ventaBase = Carbon::today()->subDays(7);

        // Define ventas with their products and quantities
        // Each venta entry: correlativo, total, cliente_idx, user_id, created_at
        // detalles: [{producto_idx, cantidad}] — sub_total = cantidad * precio_venta
        $ventasData = [
            [
                'correlativo' => 'V001-000001', 'cliente_idx' => 0, 'user_id' => $adminId,
                'created_at' => $ventaBase->copy()->addDays(1),
                'detalles' => [
                    ['producto_idx' => 0, 'cantidad' => 10],
                    ['producto_idx' => 4, 'cantidad' => 1],
                ],
            ],
            [
                'correlativo' => 'V001-000002', 'cliente_idx' => 1, 'user_id' => $adminId,
                'created_at' => $ventaBase->copy()->addDays(3),
                'detalles' => [
                    ['producto_idx' => 1, 'cantidad' => 2],
                    ['producto_idx' => 6, 'cantidad' => 3],
                ],
            ],
            [
                'correlativo' => 'V001-000003', 'cliente_idx' => 2, 'user_id' => $vendedorId,
                'created_at' => Carbon::today()->subHours(5),
                'detalles' => [
                    ['producto_idx' => 2, 'cantidad' => 10],
                    ['producto_idx' => 4, 'cantidad' => 1],
                    ['producto_idx' => 7, 'cantidad' => 1],
                ],
            ],
            [
                'correlativo' => 'V001-000004', 'cliente_idx' => 4, 'user_id' => $adminId,
                'created_at' => $now,
                'detalles' => [
                    ['producto_idx' => 5, 'cantidad' => 1],
                    ['producto_idx' => 8, 'cantidad' => 1],
                ],
            ],
        ];

        $ventaIds = [];

        foreach ($ventasData as $vData) {
            $total = collect($vData['detalles'])->sum(fn ($d) => $d['cantidad'] * $productDefs[$d['producto_idx']][3]);

            $vId = DB::table('venta')->insertGetId([
                'correlativo' => $vData['correlativo'],
                'total' => $total,
                'cliente_id_cliente' => $clienteIds[$vData['cliente_idx']],
                'user_id' => $vData['user_id'],
                'created_at' => $vData['created_at'],
                'updated_at' => $vData['created_at'],
            ]);
            $ventaIds[] = $vId;

            foreach ($vData['detalles'] as $det) {
                $prodIdx = $det['producto_idx'];
                $cantidad = $det['cantidad'];
                $precio = $productDefs[$prodIdx][3];
                $subTotal = $cantidad * $precio;

                DB::table('detalle_venta')->insert([
                    'venta_id_venta' => $vId,
                    'producto_id_producto' => $prodIds[$prodIdx],
                    'producto_presentacion_id' => $productoPresentacionBaseIds[$prodIdx],
                    'sub_total' => $subTotal,
                    'cantidad' => $cantidad,
                    'factor' => 1,
                    'cantidad_base' => $cantidad,
                    'precio_unitario' => $precio,
                ]);

                $productoConsumido[$prodIdx] += $cantidad;

                $movimientos[] = [
                    'indice' => $indiceMov++,
                    'ts' => $vData['created_at'],
                    'producto_id' => $prodIds[$prodIdx],
                    'usuario_id' => $vData['user_id'],
                    'tipo_movimiento' => 'VENTA',
                    'referencia_tipo' => 'Venta',
                    'referencia_id' => $vId,
                    'referencia_codigo' => $vData['correlativo'],
                    'cantidad' => -$cantidad,
                    'observacion' => 'Venta registrada',
                ];
            }
        }

        // ── 9. ATENCIONES ──
        $atenBase = Carbon::today()->subDays(5);

        $atencionesData = [
            [
                'observacio' => 'Control rutinario', 'cliente_idx' => 3, 'user_id' => $adminId,
                'created_at' => $atenBase->copy()->addDays(1),
                'productos' => [],
                'servicios' => [
                    ['servicio_idx' => 0, 'subtotal' => 5.00],
                    ['servicio_idx' => 2, 'subtotal' => 8.00],
                ],
            ],
            [
                'observacio' => 'Atención de emergencia', 'cliente_idx' => 5, 'user_id' => $vendedorId,
                'created_at' => Carbon::today()->subHours(3),
                'productos' => [
                    ['producto_idx' => 1, 'cantidad' => 2],
                    ['producto_idx' => 5, 'cantidad' => 1],
                ],
                'servicios' => [
                    ['servicio_idx' => 3, 'subtotal' => 7.00],
                ],
            ],
        ];

        $atencionIds = [];

        foreach ($atencionesData as $aData) {
            $total = 0;

            if (! empty($aData['productos'])) {
                $total += collect($aData['productos'])->sum(fn ($d) => $d['cantidad'] * $productDefs[$d['producto_idx']][3]);
            }
            if (! empty($aData['servicios'])) {
                $total += collect($aData['servicios'])->sum('subtotal');
            }

            $aId = DB::table('atencion')->insertGetId([
                'correlativo' => 'TEMP',
                'estado' => 'confirmada',
                'observacio' => $aData['observacio'],
                'total' => $total,
                'cliente_id_cliente' => $clienteIds[$aData['cliente_idx']],
                'user_id' => $aData['user_id'],
                'created_at' => $aData['created_at'],
                'updated_at' => $aData['created_at'],
            ]);

            DB::table('atencion')->where('id_atencion', $aId)->update([
                'correlativo' => 'A001-'.str_pad((string) $aId, 8, '0', STR_PAD_LEFT),
            ]);

            $atencionIds[] = $aId;

            if (! empty($aData['productos'])) {
                foreach ($aData['productos'] as $det) {
                    $prodIdx = $det['producto_idx'];
                    $cantidad = $det['cantidad'];
                    $precio = $productDefs[$prodIdx][3];
                    $subTotal = $cantidad * $precio;

                    DB::table('atencion_producto')->insert([
                        'atencion_id_atencion' => $aId,
                        'producto_id_producto' => $prodIds[$prodIdx],
                        'producto_presentacion_id' => $productoPresentacionBaseIds[$prodIdx],
                        'sub_total' => $subTotal,
                        'cantidad' => $cantidad,
                        'factor' => 1,
                        'cantidad_base' => $cantidad,
                        'precio_unitario' => $precio,
                    ]);

                    $productoConsumido[$prodIdx] += $cantidad;

                    $movimientos[] = [
                        'indice' => $indiceMov++,
                        'ts' => $aData['created_at'],
                        'producto_id' => $prodIds[$prodIdx],
                        'usuario_id' => $aData['user_id'],
                        'tipo_movimiento' => 'ATENCION',
                        'referencia_tipo' => 'Atencion',
                        'referencia_id' => $aId,
                        'referencia_codigo' => '#'.$aId,
                        'cantidad' => -$cantidad,
                        'observacion' => 'Atención registrada',
                    ];
                }
            }

            if (! empty($aData['servicios'])) {
                foreach ($aData['servicios'] as $s) {
                    DB::table('atencion_servicio')->insert([
                        'atencion_id_atencion' => $aId,
                        'servicio_id_servicio' => $servIds[$s['servicio_idx']],
                        'subtotal' => $s['subtotal'],
                    ]);
                }
            }
        }

        // ── LOTES DE DEMOSTRACIÓN ──
        $lotePorProducto = [];

        foreach ($productDefs as $prodIdx => $def) {
            $inventario = $productoIngresado[$prodIdx];
            $stock = $inventario - $productoConsumido[$prodIdx];

            DB::table('producto')->where('id_producto', $prodIds[$prodIdx])->update([
                'inventario' => $inventario,
                'stock' => $stock,
            ]);

            if ($stock <= 0) {
                continue;
            }

            $codigoLote = 'LOTE-DEMO-'.($prodIdx + 1);
            $vencimiento = Carbon::today()->addYear()->toDateString();

            $loteId = DB::table('producto_lote')->insertGetId([
                'producto_id' => $prodIds[$prodIdx],
                'codigo_lote' => $codigoLote,
                'fecha_vencimiento' => $vencimiento,
                'estado' => 'disponible',
                'cantidad_inicial_base' => $inventario,
                'cantidad_disponible_base' => $stock,
                'es_inicial' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            DB::table('producto_lote')
                ->where('id', $loteId)
                ->update(['correlativo' => sprintf('LOTE-%08d', $loteId)]);

            $lotePorProducto[$prodIds[$prodIdx]] = [
                'id' => $loteId,
                'codigo' => $codigoLote,
                'vencimiento' => $vencimiento,
            ];

            DB::table('lote_ingreso')->insert([
                'producto_lote_id' => $loteId,
                'ingreso_id' => null,
                'detalle_ingreso_id' => null,
                'producto_presentacion_id' => $productoPresentacionBaseIds[$prodIdx],
                'cantidad_presentacion' => $stock,
                'factor' => 1,
                'cantidad_base' => $stock,
                'cantidad_disponible_base' => $stock,
                'costo_unitario_base' => $def[2],
                'costo_total' => round($stock * $def[2], 2),
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        // ── KARDEX MOVEMENTS (cronológico) ──
        usort($movimientos, function ($a, $b) {
            $cmp = $a['ts']->timestamp - $b['ts']->timestamp;

            return $cmp !== 0 ? $cmp : ($a['indice'] - $b['indice']);
        });

        $stockActual = [];
        $presentacionPorProducto = array_combine($prodIds, $productoPresentacionBaseIds);

        foreach ($movimientos as $mov) {
            $stockAnterior = $stockActual[$mov['producto_id']] ?? 0;
            $stockNuevo = $stockAnterior + $mov['cantidad'];

            $stockActual[$mov['producto_id']] = $stockNuevo;

            DB::table('kardex_movimientos')->insert([
                'producto_id' => $mov['producto_id'],
                'usuario_id' => $mov['usuario_id'],
                'tipo_movimiento' => $mov['tipo_movimiento'],
                'referencia_tipo' => $mov['referencia_tipo'],
                'referencia_id' => $mov['referencia_id'],
                'referencia_codigo' => $mov['referencia_codigo'],
                'cantidad' => $mov['cantidad'],
                'stock_anterior' => $stockAnterior,
                'stock_nuevo' => $stockNuevo,
                'producto_presentacion_id' => $presentacionPorProducto[$mov['producto_id']] ?? null,
                'cantidad_presentacion' => abs($mov['cantidad']),
                'factor' => 1,
                'producto_lote_id' => $lotePorProducto[$mov['producto_id']]['id'] ?? null,
                'lote_codigo' => $lotePorProducto[$mov['producto_id']]['codigo'] ?? null,
                'fecha_vencimiento' => $lotePorProducto[$mov['producto_id']]['vencimiento'] ?? null,
                'costo_unitario_base' => null,
                'observacion' => $mov['observacion'],
                'created_at' => $mov['ts'],
                'updated_at' => $mov['ts'],
            ]);
        }

        // ── CAJA ──
        // Create an open caja for today
        $cajaId = DB::table('caja')->insertGetId([
            'monto_inicial' => 200.00,
            'total_ingresos' => 0,
            'total_ingresos_efectivo' => 0,
            'total_ingresos_digital' => 0,
            'total_egresos' => 0,
            'total_egresos_efectivo' => 0,
            'total_egresos_digital' => 0,
            'monto_final' => null,
            'estado' => 'abierta',
            'abierta_unica' => 1,
            'apertura_user_id' => $adminId,
            'cierre_user_id' => null,
            'apertura_at' => $now,
            'cierre_at' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        // ── CAJA MOVIMIENTOS ──
        $metodoEfectivoId = DB::table('metodo_pago')->where('nombre', 'Efectivo')->value('id_metodo_pago');
        $metodoYapeId = DB::table('metodo_pago')->where('nombre', 'Yape')->value('id_metodo_pago');

        // For each venta, create one movement per payment method.
        foreach ($ventasData as $i => $vData) {
            $vId = $ventaIds[$i];
            $total = (float) DB::table('venta')->where('id_venta', $vId)->value('total');

            $movimientos = [
                ['metodo' => $metodoEfectivoId, 'monto' => $total, 'afecta' => true],
            ];

            if ($metodoYapeId && $i === 0) {
                $yape = round($total * 0.4, 2);
                $movimientos = [
                    ['metodo' => $metodoYapeId, 'monto' => $yape, 'afecta' => false],
                    ['metodo' => $metodoEfectivoId, 'monto' => round($total - $yape, 2), 'afecta' => true],
                ];
            }

            foreach ($movimientos as $movimiento) {
                DB::table('caja_movimiento')->insert([
                    'caja_id_caja' => $cajaId,
                    'tipo' => 'ingreso',
                    'monto' => $movimiento['monto'],
                    'descripcion' => null,
                    'referencia_tipo' => 'venta',
                    'referencia_id' => $vId,
                    'metodo_pago_id' => $movimiento['metodo'],
                    'afecta_efectivo' => $movimiento['afecta'],
                    'categoria' => 'venta',
                    'naturaleza' => 'operativo',
                    'user_id' => $vData['user_id'],
                    'created_at' => $vData['created_at'],
                ]);

                DB::table('caja')->where('id_caja', $cajaId)->increment('total_ingresos', $movimiento['monto']);

                DB::table('caja')->where('id_caja', $cajaId)->increment(
                    $movimiento['afecta'] ? 'total_ingresos_efectivo' : 'total_ingresos_digital',
                    $movimiento['monto']
                );
            }
        }

        // For each atencion, create an ingreso movement (efectivo).
        foreach ($atencionesData as $i => $aData) {
            $aId = $atencionIds[$i];
            $total = DB::table('atencion')->where('id_atencion', $aId)->value('total');

            DB::table('caja_movimiento')->insert([
                'caja_id_caja' => $cajaId,
                'tipo' => 'ingreso',
                'monto' => $total,
                'descripcion' => null,
                'referencia_tipo' => 'atencion',
                'referencia_id' => $aId,
                'metodo_pago_id' => $metodoEfectivoId,
                'afecta_efectivo' => true,
                'categoria' => 'atencion',
                'naturaleza' => 'operativo',
                'user_id' => $aData['user_id'],
                'created_at' => $aData['created_at'],
            ]);

            DB::table('caja')->where('id_caja', $cajaId)->increment('total_ingresos', $total);
            DB::table('caja')->where('id_caja', $cajaId)->increment('total_ingresos_efectivo', $total);
        }

        // ── PAGOS DE DEMOSTRACIÓN ──

        if ($metodoEfectivoId) {
            foreach ($ventaIds as $i => $vId) {
                $total = (float) DB::table('venta')->where('id_venta', $vId)->value('total');
                $pagos = [['metodo' => $metodoEfectivoId, 'monto' => $total]];

                if ($metodoYapeId && $i === 0) {
                    $yape = round($total * 0.4, 2);
                    $pagos = [
                        ['metodo' => $metodoYapeId, 'monto' => $yape],
                        ['metodo' => $metodoEfectivoId, 'monto' => round($total - $yape, 2)],
                    ];
                }

                foreach ($pagos as $pago) {
                    DB::table('pago')->insert([
                        'referencia_tipo' => 'Venta',
                        'referencia_id' => $vId,
                        'metodo_pago_id' => $pago['metodo'],
                        'monto' => $pago['monto'],
                        'user_id' => $ventasData[$i]['user_id'],
                        'created_at' => $ventasData[$i]['created_at'],
                        'updated_at' => $ventasData[$i]['created_at'],
                    ]);
                }
            }

            foreach ($atencionIds as $i => $aId) {
                $total = (float) DB::table('atencion')->where('id_atencion', $aId)->value('total');

                DB::table('pago')->insert([
                    'referencia_tipo' => 'Atencion',
                    'referencia_id' => $aId,
                    'metodo_pago_id' => $metodoEfectivoId,
                    'monto' => $total,
                    'user_id' => $atencionesData[$i]['user_id'],
                    'created_at' => $atencionesData[$i]['created_at'],
                    'updated_at' => $atencionesData[$i]['created_at'],
                ]);
            }
        }
    }
}
