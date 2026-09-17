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
        // Each product has: [name, desc, precio_compra, precio_venta, categoria_key, marca_key]
        $productDefs = [
            ['Paracetamol 500mg',  'Analgésico y antipirético',      0.50,  1.00, 'Medicamentos',      'Genérico'],
            ['Ibuprofeno 400mg',   'Antiinflamatorio',              0.80,  1.50, 'Medicamentos',      'Bayer'],
            ['Amoxicilina 500mg',  'Antibiótico oral',              1.20,  2.50, 'Medicamentos',      'Pfizer'],
            ['Pañales x50',        'Pañales desechables talla M',  18.00, 25.00, 'Bebés',            'Johnson & Johnson'],
            ['Vitamina C 1000mg',  'Suplemento de vitamina C',      10.00, 18.00, 'Vitaminas',         'Natura'],
            ['Alcohol 70° 1L',     'Alcohol desinfectante',          4.00,  7.00, 'Primeros Auxilios', 'Genérico'],
            ['Jabón antibacterial', 'Jabón líquido antibacterial',     5.00,  9.00, 'Higiene',           'Natura'],
            ['Shampoo anticaspa',  'Shampoo tratamiento anticaspa', 12.00, 22.00, 'Higiene',           'Vichy'],
            ['Curitas x20',        'Curitas adhesivas',              2.00,  3.50, 'Primeros Auxilios', 'Johnson & Johnson'],
            ['Multivitamínico',    'Complejo multivitamínico',       25.00, 40.00, 'Vitaminas',         'Bayer'],
        ];

        // Insert products with temporary inventario=0, stock=0 (will update later)
        $prodIds = [];
        foreach ($productDefs as $p) {
            $prodIds[] = DB::table('producto')->insertGetId([
                'nombre' => $p[0],
                'descripcion' => $p[1],
                'precio_compra' => $p[2],
                'precio_venta' => $p[3],
                'estado' => 1,
                'inventario' => 0,
                'stock' => 0,
                'categoria_id_categoria' => $catIds[$p[4]],
                'marca_id_marca' => $marcaIds[$p[5]],
            ]);
        }

        $productoIngresado = array_fill(0, count($productDefs), 0);
        $productoConsumido = array_fill(0, count($productDefs), 0);
        $movimientos = [];
        $indiceMov = 0;

        // ── 7. INGRESOS ──
        $ingresoBase = Carbon::today()->subDays(60);

        $ingresosData = [
            // ING-001: first purchase from prov[0], 30 days ago
            [
                'correlativo' => 'ING-001',
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
            // ING-002: second purchase from prov[1], 45 days ago (15 after ING-001)
            [
                'correlativo' => 'ING-002',
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
            // ING-003: third purchase from prov[2], 2 days ago
            [
                'correlativo' => 'ING-003',
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

        foreach ($ingresosData as $ingData) {
            $ingId = DB::table('ingreso')->insertGetId([
                'correlativo' => $ingData['correlativo'],
                'proveedor_id_proveedor' => $ingData['proveedor_id'],
                'fecha' => $ingData['fecha'],
                'user_id' => $ingData['user_id'],
            ]);

            $detalleRows = [];
            foreach ($ingData['detalle'] as $det) {
                $prodIdx = $det['producto_idx'];

                $detalleRows[] = [
                    'ingreso_id_ingreso' => $ingId,
                    'producto_id_producto' => $prodIds[$prodIdx],
                    'cantidad' => $det['cantidad'],
                ];

                $productoIngresado[$prodIdx] += $det['cantidad'];

                $movimientos[] = [
                    'indice' => $indiceMov++,
                    'ts' => Carbon::parse($ingData['fecha']),
                    'producto_id' => $prodIds[$prodIdx],
                    'usuario_id' => $ingData['user_id'],
                    'tipo_movimiento' => 'INGRESO',
                    'referencia_tipo' => 'Ingreso',
                    'referencia_id' => $ingId,
                    'referencia_codigo' => $ingData['correlativo'],
                    'cantidad' => $det['cantidad'],
                    'observacion' => 'Ingreso registrado',
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
                    'sub_total' => $subTotal,
                    'cantidad' => $cantidad,
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
                'observacio' => $aData['observacio'],
                'total' => $total,
                'cliente_id_cliente' => $clienteIds[$aData['cliente_idx']],
                'user_id' => $aData['user_id'],
                'created_at' => $aData['created_at'],
                'updated_at' => $aData['created_at'],
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
                        'sub_total' => $subTotal,
                        'cantidad' => $cantidad,
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

        // ── UPDATE PRODUCT INVENTARIO / STOCK ──
        foreach ($productDefs as $prodIdx => $def) {
            $inventario = $productoIngresado[$prodIdx];
            $stock = $inventario - $productoConsumido[$prodIdx];

            DB::table('producto')->where('id_producto', $prodIds[$prodIdx])->update([
                'inventario' => $inventario,
                'stock' => $stock,
            ]);
        }

        // ── KARDEX MOVEMENTS (cronológico) ──
        usort($movimientos, function ($a, $b) {
            $cmp = $a['ts']->timestamp - $b['ts']->timestamp;

            return $cmp !== 0 ? $cmp : ($a['indice'] - $b['indice']);
        });

        $stockActual = [];

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
            'total_egresos' => 0,
            'monto_final' => null,
            'estado' => 'abierta',
            'apertura_user_id' => $adminId,
            'cierre_user_id' => null,
            'apertura_at' => $now,
            'cierre_at' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        // ── CAJA MOVIMIENTOS ──
        // For each venta, create an ingreso movement
        foreach ($ventasData as $i => $vData) {
            $vId = $ventaIds[$i];
            $total = DB::table('venta')->where('id_venta', $vId)->value('total');

            DB::table('caja_movimiento')->insert([
                'caja_id_caja' => $cajaId,
                'tipo' => 'ingreso',
                'monto' => $total,
                'descripcion' => null,
                'referencia_tipo' => 'venta',
                'referencia_id' => $vId,
                'user_id' => $vData['user_id'],
                'created_at' => $vData['created_at'],
            ]);

            DB::table('caja')->where('id_caja', $cajaId)->increment('total_ingresos', $total);
        }

        // For each atencion, create an ingreso movement
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
                'user_id' => $aData['user_id'],
                'created_at' => $aData['created_at'],
            ]);

            DB::table('caja')->where('id_caja', $cajaId)->increment('total_ingresos', $total);
        }
    }
}
