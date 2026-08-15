<div class="flex items-center justify-between mb-6">
    <h1 class="text-2xl font-bold text-stone-800">Sliders — Portada</h1>
</div>

<div class="bg-white rounded-xl shadow-sm border border-stone-200 overflow-hidden">
    <div class="flex border-b border-stone-200">
        <button type="button" data-slider-tab="buscados"
            class="tab-btn px-6 py-3 text-sm font-medium text-emerald-700 border-b-2 border-emerald-600 transition">
            Más buscados
        </button>
        <button type="button" data-slider-tab="vendidos"
            class="tab-btn px-6 py-3 text-sm font-medium text-stone-500 hover:text-stone-700 border-b-2 border-transparent transition">
            Más vendidos
        </button>
        <button type="button" data-slider-tab="descuentos"
            class="tab-btn px-6 py-3 text-sm font-medium text-stone-500 hover:text-stone-700 border-b-2 border-transparent transition">
            En descuento
        </button>
    </div>

    <div class="p-6">
        <div class="flex items-center justify-between mb-4">
            <p class="text-sm text-stone-500" id="slider-count">0 productos</p>
            <button type="button" id="btn-agregar"
                class="px-4 py-2 text-sm bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg transition">
                + Agregar producto
            </button>
        </div>

        <div id="slider-content">
            <p class="text-sm text-stone-400 py-8 text-center">Selecciona un slider para ver sus productos.</p>
        </div>
    </div>
</div>

<div id="modal-agregar" class="fixed inset-0 z-50 hidden bg-black/50 flex items-center justify-center">
    <div class="bg-white rounded-xl shadow-xl w-full max-w-2xl mx-4 max-h-[90vh] overflow-y-auto p-6">
        <div class="flex items-center justify-between mb-4">
            <h2 class="text-lg font-bold text-stone-800">Agregar producto</h2>
            <button type="button" class="cerrar-modal text-stone-400 hover:text-stone-600">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>

        <div class="mb-4">
            <input type="text" id="buscar-producto-input" placeholder="Buscar productos..."
                class="w-full rounded-lg border border-stone-300 px-4 py-2 text-sm focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 outline-none">
        </div>

        <div id="resultados-busqueda" class="space-y-2 max-h-60 overflow-y-auto">
            <p class="text-sm text-stone-400 text-center py-4">Escribe para buscar productos.</p>
        </div>

        <div class="flex justify-end gap-3 mt-4 pt-4 border-t border-stone-200">
            <button type="button" class="cerrar-modal px-4 py-2 text-sm text-stone-600 hover:text-stone-800 transition">Cancelar</button>
        </div>
    </div>
</div>

@vite('resources/js/admin/sliders/index.js')
