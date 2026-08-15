function getCartCount() {
    fetch('/carrito-count')
        .then(r => r.json())
        .then(d => updateBadge(d.count))
        .catch(() => {});
}

function updateBadge(count) {
    document.querySelectorAll('[id^="cart-badge"]').forEach(badge => {
        if (count > 0) {
            badge.textContent = count;
            badge.classList.remove('hidden');
        } else {
            badge.classList.add('hidden');
        }
    });
}

document.querySelectorAll('.add-to-cart-btn').forEach(btn => {
    btn.addEventListener('click', function () {
        const itemType = this.dataset.itemType;
        const itemId = this.dataset.itemId;
        const originalText = this.innerHTML;

        this.disabled = true;
        this.innerHTML = '<svg class="animate-spin w-5 h-5" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>';

        fetch('/carrito', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                'Accept': 'application/json',
            },
            body: JSON.stringify({ item_type: itemType, item_id: itemId }),
        })
        .then(r => r.json())
        .then(d => {
            if (d.success) {
                updateBadge(d.count);
                showToast(d.message, 'success');
            } else {
                showToast(d.message || 'Error al añadir al carrito.', 'error');
            }
        })
        .catch(() => {
            showToast('Error de conexión. Intenta de nuevo.', 'error');
        })
        .finally(() => {
            this.disabled = false;
            this.innerHTML = originalText;
        });
    });
});

const cartPage = document.getElementById('cart-page');
if (cartPage) {
    cartPage.querySelectorAll('.qty-minus').forEach(btn => {
        btn.addEventListener('click', function () {
            const container = this.closest('.cart-item');
            const qtyEl = container.querySelector('.qty-value');
            let qty = parseInt(qtyEl.textContent);
            if (qty > 1) {
                updateQuantity(container, qty - 1);
            }
        });
    });

    cartPage.querySelectorAll('.qty-plus').forEach(btn => {
        btn.addEventListener('click', function () {
            const container = this.closest('.cart-item');
            const qtyEl = container.querySelector('.qty-value');
            let qty = parseInt(qtyEl.textContent);
            if (qty < 99) {
                updateQuantity(container, qty + 1);
            }
        });
    });

    cartPage.querySelectorAll('.remove-item').forEach(btn => {
        btn.addEventListener('click', function () {
            if (!confirm('¿Eliminar este item del carrito?')) return;
            const container = this.closest('.cart-item');
            const itemId = container.dataset.itemId;

            fetch('/carrito/' + itemId, {
                method: 'DELETE',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                    'Accept': 'application/json',
                },
            })
            .then(r => r.json())
            .then(d => {
                if (d.success) {
                    container.remove();
                    updateBadge(d.count);
                    updateCartTotal();
                    if (document.querySelectorAll('.cart-item').length === 0) {
                        location.reload();
                    }
                }
            })
            .catch(() => {});
        });
    });
}

function updateQuantity(container, newQty) {
    const itemId = container.dataset.itemId;
    const qtyEl = container.querySelector('.qty-value');
    const subtotalEl = container.querySelector('.item-subtotal');
    const minusBtn = container.querySelector('.qty-minus');

    fetch('/carrito/' + itemId, {
        method: 'PUT',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
            'Accept': 'application/json',
        },
        body: JSON.stringify({ cantidad: newQty }),
    })
    .then(r => r.json())
    .then(d => {
        if (d.success) {
            qtyEl.textContent = newQty;
            subtotalEl.textContent = 'S/ ' + d.subtotal;
            minusBtn.disabled = newQty <= 1;
            updateCartTotal(d.total);
            updateBadge(d.count);
        }
    })
    .catch(() => {});
}

function updateCartTotal(total) {
    const totalEl = document.querySelector('.cart-total');
    if (total) {
        totalEl.textContent = 'S/ ' + total;
    } else {
        let sum = 0;
        document.querySelectorAll('.item-subtotal').forEach(el => {
            sum += parseFloat(el.textContent.replace('S/ ', '').replace(',', '')) || 0;
        });
        if (totalEl) totalEl.textContent = 'S/ ' + sum.toFixed(2);
    }
}

function showToast(message, type) {
    const existing = document.querySelector('.cart-toast');
    if (existing) existing.remove();

    const toast = document.createElement('div');
    toast.className = 'cart-toast fixed top-4 right-4 z-50 px-5 py-3 rounded-lg shadow-lg text-white text-sm font-medium transition-all duration-300 ' +
        (type === 'success' ? 'bg-emerald-600' : 'bg-red-600');
    toast.textContent = message;
    document.body.appendChild(toast);

    setTimeout(() => {
        toast.style.opacity = '0';
        toast.style.transform = 'translateX(100%)';
        setTimeout(() => toast.remove(), 300);
    }, 3000);
}
