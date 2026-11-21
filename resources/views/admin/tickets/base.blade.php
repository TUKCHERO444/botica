<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $titulo ?? 'Ticket' }} - {{ config('ticket.negocio') }}</title>

    @php
        $ancho = (int) config('ticket.ancho_mm', 80);
        $fuenteTicket = $ancho <= 58 ? '9px' : '11px';
    @endphp

    <style>
        * { box-sizing: border-box; }

        html, body {
            margin: 0;
            padding: 0;
        }

        body {
            background: #e7e5e4;
            color: #1c1917;
            font-family: system-ui, -apple-system, "Segoe UI", sans-serif;
        }

        .barra {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: center;
            gap: 10px;
            padding: 16px;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            border-radius: 8px;
            border: 1px solid transparent;
            padding: 9px 16px;
            font-size: 14px;
            font-weight: 600;
            text-decoration: none;
            cursor: pointer;
            transition: background-color .15s, color .15s, border-color .15s;
        }

        .btn-volver {
            background: #fff;
            border-color: #d6d3d1;
            color: #57534e;
        }

        .btn-volver:hover {
            background: #f5f5f4;
            color: #292524;
        }

        .btn-imprimir {
            background: #059669;
            color: #fff;
        }

        .btn-imprimir:hover {
            background: #047857;
        }

        .aviso {
            max-width: 420px;
            margin: 0 auto 16px;
            padding: 12px 16px;
            border: 1px solid #a7f3d0;
            border-radius: 8px;
            background: #ecfdf5;
            color: #065f46;
            font-size: 14px;
            text-align: center;
        }

        .hoja {
            padding: 0 16px 48px;
        }

        .ticket {
            width: {{ $ancho }}mm;
            max-width: 100%;
            margin: 0 auto;
            padding: 4mm;
            background: #fff;
            border: 1px solid #d6d3d1;
            border-radius: 8px;
            box-shadow: 0 8px 24px rgba(0, 0, 0, .12);
            font-family: "Courier New", ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
            font-size: {{ $fuenteTicket }};
            line-height: 1.35;
            color: #000;
            overflow-wrap: anywhere;
        }

        .ticket .negocio {
            text-align: center;
        }

        .ticket .negocio h1 {
            margin: 0 0 2px;
            font-size: {{ $ancho <= 58 ? '13px' : '16px' }};
            line-height: 1.15;
            letter-spacing: .3px;
            text-transform: uppercase;
        }

        .ticket .negocio p {
            margin: 1px 0;
        }

        .ticket .tipo {
            margin-top: 6px;
            font-weight: bold;
            letter-spacing: .5px;
        }

        .separador {
            margin: 6px 0;
            border-top: 1px dashed #000;
        }

        .meta > div {
            display: flex;
            justify-content: space-between;
            gap: 8px;
        }

        .meta .etiqueta {
            flex-shrink: 0;
        }

        .meta .valor {
            text-align: right;
            overflow-wrap: anywhere;
        }

        .meta .valor.observacion {
            text-align: left;
            white-space: pre-wrap;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }

        thead {
            display: table-header-group;
        }

        tr {
            page-break-inside: avoid;
        }

        th {
            padding: 2px 0;
            font-size: .92em;
            text-align: left;
            text-transform: uppercase;
        }

        th.importe,
        td.importe,
        th.unit,
        td.unit {
            text-align: right;
        }

        th.cant,
        td.cant {
            width: 9%;
            text-align: left;
            vertical-align: top;
        }

        th.desc,
        td.desc {
            width: 46%;
            vertical-align: top;
        }

        th.unit,
        td.unit {
            width: 20%;
            vertical-align: top;
        }

        th.importe,
        td.importe {
            width: 25%;
            vertical-align: top;
        }

        tbody tr:first-child td {
            padding-top: 4px;
        }

        td.cant,
        td.desc,
        td.unit,
        td.importe {
            padding: 3px 0;
        }

        td.desc .nombre {
            font-weight: bold;
        }

        td.desc small {
            display: block;
            font-size: .92em;
        }

        .seccion-titulo {
            margin-top: 5px;
            font-weight: bold;
            letter-spacing: .3px;
            text-transform: uppercase;
        }

        .leyenda-precios {
            margin-top: 3px;
            font-size: .88em;
            text-align: right;
        }

        .totales {
            margin-top: 4px;
        }

        .totales > div {
            display: flex;
            justify-content: space-between;
            gap: 8px;
            padding: 1px 0;
        }

        .totales .total {
            margin-top: 4px;
            padding-top: 4px;
            border-top: 1px solid #000;
            font-size: 1.25em;
            font-weight: bold;
        }

        .pie {
            margin-top: 8px;
            text-align: center;
        }

        .pie .gracias {
            font-weight: bold;
        }

        .pie .legal {
            margin-top: 4px;
            font-size: .9em;
        }

        .anulada {
            margin: 6px 0;
            padding: 4px;
            border: 1px solid #000;
            font-weight: bold;
            text-align: center;
            letter-spacing: 1px;
        }

        @page {
            size: {{ $ancho }}mm auto;
            margin: 0;
        }

        @media print {
            body {
                background: #fff;
            }

            .no-imprimir {
                display: none !important;
            }

            .hoja {
                padding: 0;
            }

            .ticket {
                width: auto;
                max-width: none;
                margin: 0;
                padding: 2mm 3mm;
                border: none;
                border-radius: 0;
                box-shadow: none;
            }
        }
    </style>
</head>
<body>
    <div class="barra no-imprimir">
        <a href="{{ $volverUrl ?? url('/admin') }}" class="btn btn-volver">
            <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
            </svg>
            Volver al panel
        </a>
        <button type="button" class="btn btn-imprimir" onclick="window.print()">
            <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2V9a2 2 0 00-2-2H9a2 2 0 00-2 2v10a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/>
            </svg>
            Imprimir ticket
        </button>
    </div>

    @if (! empty($aviso))
        <div class="aviso no-imprimir">{{ $aviso }}</div>
    @endif

    <div class="hoja">
        <div class="ticket">
            <div class="negocio">
                <h1>{{ config('ticket.negocio') }}</h1>
                @if (config('ticket.ruc'))
                    <p>RUC: {{ config('ticket.ruc') }}</p>
                @endif
                @if (config('ticket.direccion'))
                    <p>{{ config('ticket.direccion') }}</p>
                @endif
                @if (config('ticket.telefono'))
                    <p>Tel.: {{ config('ticket.telefono') }}</p>
                @endif
                <p class="tipo">@yield('tipo_documento', 'TICKET')</p>
            </div>

            @yield('contenido')

            <div class="pie">
                <p class="gracias">{{ config('ticket.pie') }}</p>
                <p class="legal">Documento interno. No válido como comprobante electrónico.</p>
            </div>
        </div>
    </div>

    <script>
        window.addEventListener('load', function () {
            window.print();
        });
    </script>
</body>
</html>
