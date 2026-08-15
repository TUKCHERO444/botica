<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Carrito;
use App\Models\CarritoItem;
use App\Models\Combo;
use App\Models\Producto;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;

class CarritoController extends Controller
{
    public function index()
    {
        $cliente = auth()->guard('clientes')->user();
        $carrito = $cliente->carrito;

        $items = collect();
        $total = 0;

        if ($carrito) {
            $carrito->load('items.item');
            $items = $carrito->items;
            $total = $items->sum('subtotal');
        }

        return view('public.carrito.index', compact('items', 'total'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'item_type' => 'required|in:producto,combo',
            'item_id'   => 'required|integer',
        ]);

        $itemType = $request->item_type;
        $itemId = (int) $request->item_id;

        $entity = match ($itemType) {
            'producto' => Producto::visible()->find($itemId),
            'combo'    => Combo::where('activo', true)->find($itemId),
            default    => null,
        };

        if (!$entity) {
            return response()->json([
                'success' => false,
                'message' => 'El producto o combo no está disponible.',
            ], 404);
        }

        $cliente = auth()->guard('clientes')->user();
        $carrito = $cliente->carrito;

        if (!$carrito) {
            $carrito = Carrito::create(['cliente_id_cliente' => $cliente->id_cliente]);
        }

        $existingItem = CarritoItem::where('carrito_id_carrito', $carrito->id_carrito)
            ->where('item_type', $itemType)
            ->where('item_id', $itemId)
            ->first();

        if ($existingItem) {
            $existingItem->increment('cantidad');
        } else {
            CarritoItem::create([
                'carrito_id_carrito' => $carrito->id_carrito,
                'item_type'          => $itemType,
                'item_id'            => $itemId,
                'cantidad'           => 1,
            ]);
        }

        $carrito->load('items');
        $count = $carrito->items->sum('cantidad');

        return response()->json([
            'success' => true,
            'message' => match ($itemType) {
                'producto' => 'Producto añadido al carrito.',
                'combo'    => 'Combo añadido al carrito.',
            },
            'count' => $count,
        ]);
    }

    public function update(Request $request, CarritoItem $item)
    {
        $this->authorizeItem($item);

        $request->validate([
            'cantidad' => 'required|integer|min:1|max:99',
        ]);

        $item->update(['cantidad' => (int) $request->cantidad]);

        $carrito = $item->carrito;
        $carrito->load('items');
        $total = $carrito->items->sum('subtotal');
        $count = $carrito->items->sum('cantidad');

        return response()->json([
            'success'  => true,
            'subtotal' => number_format($item->subtotal, 2),
            'precio_unitario' => number_format($item->precio_unitario, 2),
            'total'    => number_format($total, 2),
            'count'    => $count,
        ]);
    }

    public function destroy(CarritoItem $item)
    {
        $this->authorizeItem($item);

        $item->delete();

        $cliente = auth()->guard('clientes')->user();
        $carrito = $cliente->carrito;

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
            'count'   => $count,
        ]);
    }

    public function count()
    {
        $count = 0;
        $cliente = auth()->guard('clientes')->user();

        if ($cliente && $cliente->carrito) {
            $count = $cliente->carrito->items()->sum('cantidad');
        }

        return response()->json(['count' => $count]);
    }

    public function pdf()
    {
        $cliente = auth()->guard('clientes')->user();
        $carrito = $cliente->carrito;

        if (!$carrito || !$carrito->items()->exists()) {
            return redirect()->route('public.carrito.index')
                ->withErrors('El carrito está vacío.');
        }

        $carrito->load('items.item');
        $items = $carrito->items;
        $total = $items->sum('subtotal');

        $pdf = Pdf::loadView('public.carrito.pdf', compact('items', 'cliente', 'total'));

        return $pdf->download('carrito-' . now()->format('Ymd-His') . '.pdf');
    }

    public function whatsapp()
    {
        $cliente = auth()->guard('clientes')->user();
        $carrito = $cliente->carrito;

        if (!$carrito || !$carrito->items()->exists()) {
            return redirect()->route('public.carrito.index')
                ->withErrors('El carrito está vacío.');
        }

        $carrito->load('items.item');
        $items = $carrito->items;
        $total = $items->sum('subtotal');

        $lines = [];
        $lines[] = 'Hola, quisiera cotizar los siguientes productos:';
        $lines[] = '';

        foreach ($items as $i => $item) {
            $num = $i + 1;
            $nombre = $item->nombre ?? 'Producto';
            $lines[] = "{$num}. {$nombre} x{$item->cantidad} — S/ " . number_format($item->subtotal, 2);
        }

        $lines[] = '';
        $lines[] = 'Total: S/ ' . number_format($total, 2);
        $lines[] = '';
        $lines[] = 'Mis datos:';
        $lines[] = '- Nombre: ' . ($cliente->nombre_completo ?: '—');
        $lines[] = '- DNI: ' . ($cliente->dni ?? '—');
        $lines[] = '- Teléfono: ' . ($cliente->telefono ?? '—');
        $lines[] = '- Email: ' . ($cliente->email ?? '—');

        $message = implode("\n", $lines);

        $phone = config('services.whatsapp.phone');
        $url = 'https://wa.me/' . $phone . '?text=' . rawurlencode($message);

        return redirect()->away($url);
    }

    private function authorizeItem(CarritoItem $item): void
    {
        $cliente = auth()->guard('clientes')->user();

        if (!$cliente->carrito || $item->carrito_id_carrito !== $cliente->carrito->id_carrito) {
            abort(403, 'Este item no pertenece a tu carrito.');
        }
    }
}
