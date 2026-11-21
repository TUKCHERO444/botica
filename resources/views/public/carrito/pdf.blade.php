<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Cotización — Carrito de Compras</title>
    <style>
        body {
            font-family: 'DejaVu Sans', sans-serif;
            font-size: 12px;
            color: #333;
            line-height: 1.5;
        }
        .header {
            text-align: center;
            border-bottom: 2px solid #059669;
            padding-bottom: 15px;
            margin-bottom: 20px;
        }
        .header h1 {
            color: #059669;
            margin: 0;
            font-size: 22px;
        }
        .header p {
            color: #666;
            margin: 5px 0 0;
            font-size: 11px;
        }
        .cliente-info {
            margin-bottom: 20px;
            padding: 12px;
            background: #f9fafb;
            border-radius: 6px;
        }
        .cliente-info h3 {
            margin: 0 0 8px;
            font-size: 13px;
            color: #333;
        }
        .cliente-info p {
            margin: 2px 0;
            font-size: 11px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        table th {
            background: #059669;
            color: white;
            padding: 8px 10px;
            text-align: left;
            font-size: 11px;
            text-transform: uppercase;
        }
        table td {
            padding: 8px 10px;
            border-bottom: 1px solid #e5e7eb;
            font-size: 11px;
        }
        table tr:last-child td {
            border-bottom: none;
        }
        .total-row td {
            font-weight: bold;
            font-size: 14px;
            border-top: 2px solid #059669;
            padding-top: 10px;
        }
        .text-right {
            text-align: right;
        }
        .text-center {
            text-align: center;
        }
        .footer {
            text-align: center;
            color: #999;
            font-size: 10px;
            border-top: 1px solid #e5e7eb;
            padding-top: 15px;
            margin-top: 30px;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>{{ config('app.name', 'Botica') }}</h1>
        <p>Cotización generada el {{ now()->format('d/m/Y H:i') }}</p>
    </div>

    <div class="cliente-info">
        <h3>Datos del Cliente</h3>
        <p><strong>Nombre:</strong> {{ $cliente->nombre_completo ?: '—' }}</p>
        <p><strong>DNI:</strong> {{ $cliente->dni ?? '—' }}</p>
        <p><strong>Teléfono:</strong> {{ $cliente->telefono ?? '—' }}</p>
        <p><strong>Email:</strong> {{ $cliente->email ?? '—' }}</p>
    </div>

    <table>
        <thead>
            <tr>
                <th>#</th>
                <th>Tipo</th>
                <th>Producto / Combo</th>
                <th class="text-center">Cant.</th>
                <th class="text-right">Precio Unit.</th>
                <th class="text-right">Subtotal</th>
            </tr>
        </thead>
        <tbody>
            @foreach($items as $i => $item)
                <tr>
                    <td>{{ $i + 1 }}</td>
                    <td>{{ $item->item_type === 'combo' ? 'Combo' : 'Producto' }}</td>
                    <td>{{ $item->nombre ?? '—' }}</td>
                    <td class="text-center">{{ \App\Helpers\CantidadHelper::formatear($item->cantidad) }}</td>
                    <td class="text-right">S/ {{ number_format($item->precio_unitario, 2) }}</td>
                    <td class="text-right">S/ {{ number_format($item->subtotal, 2) }}</td>
                </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr class="total-row">
                <td colspan="5" class="text-right">Total:</td>
                <td class="text-right">S/ {{ number_format($total, 2) }}</td>
            </tr>
        </tfoot>
    </table>

    <div class="footer">
        <p>Este documento es una cotización generada por el cliente a través de {{ config('app.name', 'Botica') }}.</p>
        <p>Los precios están sujetos a verificación y disponibilidad de stock.</p>
    </div>
</body>
</html>
