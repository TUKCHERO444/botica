<div class="grid grid-cols-2 md:grid-cols-3 xl:grid-cols-4 gap-4 sm:gap-5 lg:gap-6">
    @forelse ($productos as $prod)
        <x-product-card :producto="$prod" />
    @empty
        <div class="col-span-full text-center py-16">
            <svg class="w-12 h-12 mx-auto text-stone-300 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>
            </svg>
            <p class="text-stone-400 font-medium">No se encontraron productos</p>
            <p class="text-stone-400 text-sm mt-1">Intentá con otros filtros o términos de búsqueda</p>
        </div>
    @endforelse
</div>

{{ $productos->links() }}
