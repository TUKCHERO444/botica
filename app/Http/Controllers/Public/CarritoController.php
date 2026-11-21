<?php

namespace App\Http\Controllers\Public;

use App\Helpers\CantidadHelper;
use App\Http\Controllers\Controller;
use App\Models\Carrito;
use App\Models\CarritoItem;
use App\Models\Combo;
use App\Models\Producto;
use App\Models\ProductoPresentacion;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;

class CarritoController extends Controller
{
    public function index()
    {
        $cliente = auth()->guard('clientes')->user();
        $carrito = Carrito::where('cliente_id_cliente', $cliente->id_cliente)->first();

        $items = collect();
        $total = 0;

        if ($carrito) {
            $carrito->load('items.item', 'items.productoPresentacion.producto');
            $items = $carrito->items;
            $total = $items->sum('subtotal');
        }

        return view('public.carrito.index', compact('items', 'total'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'item_type' => 'required|in:producto,combo',
            'item_id' => 'required|integer',
            'producto_presentacion_id' => 'nullable|integer',
            'cantidad' => 'nullable|numeric|gt:0',
        ]);

        $itemType = $request->item_type;
        $itemId = (int) $request->item_id;
        $cantidad = (string) ($request->input('cantidad') ?? 1);
        $presentacion = null;
        $precio = 0;

        if ($itemType === 'producto') {
            $entity = Producto::visible()->find($itemId);

            if (! $entity) {
                return response()->json([
                    'success' => false,
                    'message' => 'El producto no está disponible.',
                ], 404);
            }

            if ($request->filled('producto_presentacion_id')) {
                $presentacion = ProductoPresentacion::where('producto_id_producto', $itemId)
                    ->where('activo', true)
                    ->find($request->producto_presentacion_id);

                if (! $presentacion) {
                    return response()->json([
                        'success' => false,
                        'message' => 'La presentación seleccionada no está disponible.',
                    ], 404);
                }
            } else {
                $presentacion = $entity->presentacionBase;
            }

            if ($presentacion && ! CantidadHelper::esMultiplo($cantidad, $presentacion->paso)) {
                return response()->json([
                    'success' => false,
                    'message' => "La cantidad debe ser múltiplo de {$presentacion->paso} para esta presentación.",
                ], 422);
            }

            $precio = $presentacion?->precio_venta ?? $entity->precio_venta;
        } else {
            $entity = Combo::where('activo', true)->find($itemId);

            if (! $entity) {
                return response()->json([
                    'success' => false,
                    'message' => 'El combo no está disponible.',
                ], 404);
            }

            $precio = $entity->precio_sugerido;
        }

        $cliente = auth()->guard('clientes')->user();
        $carrito = Carrito::firstOrCreate(['cliente_id_cliente' => $cliente->id_cliente]);

        $existingItem = CarritoItem::where('carrito_id_carrito', $carrito->id_carrito)
            ->where('item_type', $itemType)
            ->where('item_id', $itemId)
            ->where('producto_presentacion_id', $presentacion?->id_producto_presentacion)
            ->first();

        if ($existingItem) {
            $existingItem->cantidad = CantidadHelper::sumar($existingItem->cantidad, $cantidad);
            $existingItem->save();
        } else {
            CarritoItem::create([
                'carrito_id_carrito' => $carrito->id_carrito,
                'item_type' => $itemType,
                'item_id' => $itemId,
                'producto_presentacion_id' => $presentacion?->id_producto_presentacion,
                'cantidad' => $cantidad,
                'precio_unitario' => $precio,
            ]);
        }

        $carrito->load('items');
        $count = $carrito->items->sum(fn ($item) => (float) $item->cantidad);

        return response()->json([
            'success' => true,
            'message' => match ($itemType) {
                'producto' => 'Producto añadido al carrito.',
                'combo' => 'Combo añadido al carrito.',
            },
            'count' => $count,
        ]);
    }

    public function update(Request $request, CarritoItem $item)
    {
        $this->authorizeItem($item);

        $request->validate([
            'cantidad' => 'required|numeric|min:0.001|max:99',
        ]);

        $cantidad = (string) $request->cantidad;

        if ($item->productoPresentacion
            && ! CantidadHelper::esMultiplo($cantidad, $item->productoPresentacion->paso)) {
            return response()->json([
                'success' => false,
                'message' => "La cantidad debe ser múltiplo de {$item->productoPresentacion->paso}.",
            ], 422);
        }

        $item->update(['cantidad' => $cantidad]);

        $carrito = $item->carrito;
        $carrito->load('items');
        $total = $carrito->items->sum(fn ($i) => (float) $i->subtotal);
        $count = $carrito->items->sum(fn ($i) => (float) $i->cantidad);

        return response()->json([
            'success' => true,
            'subtotal' => number_format($item->subtotal, 2),
            'precio_unitario' => number_format($item->precio_unitario, 2),
            'total' => number_format($total, 2),
            'count' => $count,
        ]);
    }

    public function destroy(CarritoItem $item)
    {
        $this->authorizeItem($item);

        $item->delete();

        $cliente = auth()->guard('clientes')->user();
        $carrito = Carrito::where('cliente_id_cliente', $cliente->id_cliente)->first();

        if ($carrito) {
            $carrito->load('items');
            $count = $carrito->items->sum('cantidad');

            if ($carrito->items->isEmpty()) {
                $carrito->delete();
            }
        } else {
            $count = 0;
        }

        return response()->json([
            'success' => true,
            'message' => 'Item eliminado del carrito.',
            'count' => $count,
        ]);
    }

    public function count()
    {
        $count = 0;
        $cliente = auth()->guard('clientes')->user();

        if ($cliente) {
            $carrito = Carrito::where('cliente_id_cliente', $cliente->id_cliente)->first();
            $count = $carrito ? $carrito->items()->sum('cantidad') : 0;
        }

        return response()->json(['count' => $count]);
    }

    public function pdf()
    {
        $cliente = auth()->guard('clientes')->user();
        $carrito = Carrito::where('cliente_id_cliente', $cliente->id_cliente)->first();

        if (! $carrito || ! $carrito->items()->exists()) {
            return redirect()->route('public.carrito.index')
                ->withErrors('El carrito está vacío.');
        }

        $carrito->load('items.item', 'items.productoPresentacion.producto');
        $items = $carrito->items;
        $total = $items->sum('subtotal');

        $pdf = Pdf::loadView('public.carrito.pdf', compact('items', 'cliente', 'total'));

        return $pdf->download('carrito-'.now()->format('Ymd-His').'.pdf');
    }

    public function whatsapp()
    {
        $cliente = auth()->guard('clientes')->user();
        $carrito = Carrito::where('cliente_id_cliente', $cliente->id_cliente)->first();

        if (! $carrito || ! $carrito->items()->exists()) {
            return redirect()->route('public.carrito.index')
                ->withErrors('El carrito está vacío.');
        }

        $carrito->load('items.item', 'items.productoPresentacion.producto');
        $items = $carrito->items;
        $total = $items->sum('subtotal');

        $lines = [];
        $lines[] = 'Hola, quisiera cotizar los siguientes productos:';
        $lines[] = '';

        foreach ($items as $i => $item) {
            $num = $i + 1;
            $nombre = $item->nombre ?? 'Producto';
            $lines[] = "{$num}. {$nombre} x".CantidadHelper::formatear($item->cantidad).' — S/ '.number_format($item->subtotal, 2);
        }

        $lines[] = '';
        $lines[] = 'Total: S/ '.number_format($total, 2);
        $lines[] = '';
        $lines[] = 'Mis datos:';
        $lines[] = '- Nombre: '.($cliente->nombre_completo ?: '—');
        $lines[] = '- DNI: '.($cliente->dni ?? '—');
        $lines[] = '- Teléfono: '.($cliente->telefono ?? '—');
        $lines[] = '- Email: '.($cliente->email ?? '—');

        $message = implode("\n", $lines);

        $phone = config('services.whatsapp.phone');
        $url = 'https://wa.me/'.$phone.'?text='.rawurlencode($message);

        return redirect()->away($url);
    }

    private function authorizeItem(CarritoItem $item): void
    {
        $cliente = auth()->guard('clientes')->user();
        $carrito = Carrito::where('cliente_id_cliente', $cliente->id_cliente)->first();

        if (! $carrito || $item->carrito_id_carrito !== $carrito->id_carrito) {
            abort(403, 'Este item no pertenece a tu carrito.');
        }
    }
}
