let dragSource = null;

export function initGestorImagenes() {
    const gestor = document.getElementById('gestor-imagenes');
    if (!gestor) return;

    const slots = gestor.querySelectorAll('.slot-imagen');

    slots.forEach(slot => {
        const fileInput = slot.querySelector('input[type="file"]');
        const btnEliminar = slot.querySelector('.btn-eliminar-imagen');
        const indicador = slot.querySelector('.indicador-arrastre');

        if (fileInput) {
            fileInput.addEventListener('change', function (e) {
                const file = e.target.files[0];
                if (!file) return;
                slot.classList.remove('border-dashed', 'border-stone-300', 'hover:border-emerald-400', 'text-stone-400', 'hover:text-emerald-500');
                slot.classList.add('border-stone-300', 'bg-stone-50');

                const existingImg = slot.querySelector('img');
                if (existingImg) existingImg.remove();

                slot.innerHTML = '';
                slot.appendChild(createImgElement(file));
                slot.appendChild(btnEliminar);
                slot.appendChild(indicador);
                slot.appendChild(fileInput);

                slot.dataset.existing = '0';
                fileInput.classList.add('hidden');
                btnEliminar.classList.remove('hidden');
                indicador.classList.remove('hidden');
                slot.draggable = true;
            });
        }

        const img = slot.querySelector('img');
        if (img || slot.dataset.existing === '1') {
            slot.draggable = true;
        }

        btnEliminar?.addEventListener('click', function (e) {
            e.stopPropagation();
            const idProductoImagen = slot.dataset.imgId;
            if (idProductoImagen) {
                const container = document.getElementById('imagenes-eliminar-container');
                if (container) {
                    const input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = 'imagenes_eliminar[]';
                    input.value = idProductoImagen;
                    container.appendChild(input);
                }
            }
            limpiarSlot(slot);
        });

        slot.addEventListener('click', function () {
            if (this.dataset.existing === '1') return;
            const inp = this.querySelector('input[type="file"]');
            inp?.click();
        });

        // Drag events
        slot.addEventListener('dragstart', function (e) {
            if (!this.draggable) { e.preventDefault(); return; }
            dragSource = this;
            this.classList.add('opacity-50', 'border-emerald-500');
            e.dataTransfer.effectAllowed = 'move';
        });

        slot.addEventListener('dragend', function () {
            this.classList.remove('opacity-50', 'border-emerald-500');
            dragSource = null;
        });

        slot.addEventListener('dragover', function (e) {
            e.preventDefault();
            if (this !== dragSource) {
                this.classList.add('border-emerald-500');
            }
        });

        slot.addEventListener('dragleave', function () {
            this.classList.remove('border-emerald-500');
        });

        slot.addEventListener('drop', function (e) {
            e.preventDefault();
            this.classList.remove('border-emerald-500');
            if (dragSource && dragSource !== this) {
                intercambiarSlots(dragSource, this);
            }
        });
    });
}

function createImgElement(file) {
    const img = document.createElement('img');
    img.className = 'w-full h-full object-cover rounded-lg';
    const reader = new FileReader();
    reader.onload = e => img.src = e.target.result;
    reader.readAsDataURL(file);
    return img;
}

function limpiarSlot(slot) {
    const gestor = document.getElementById('gestor-imagenes');
    const totalSlots = gestor.querySelectorAll('.slot-imagen').length;

    slot.innerHTML = '';
    slot.draggable = false;
    slot.classList.remove('bg-stone-50');
    slot.classList.add('border-dashed', 'border-stone-300', 'hover:border-emerald-400', 'text-stone-400', 'hover:text-emerald-500');
    slot.dataset.existing = '0';

    const fileInput = document.createElement('input');
    fileInput.type = 'file';
    fileInput.name = 'imagenes[]';
    fileInput.accept = 'image/jpeg,image/jpg,image/png,image/webp';
    fileInput.className = 'hidden';

    const ordenInput = document.createElement('input');
    ordenInput.type = 'hidden';
    ordenInput.name = 'orden_imagenes[]';
    ordenInput.className = 'input-orden';

    const iconSvg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
    iconSvg.classList.add('w-8', 'h-8', 'mb-1');
    iconSvg.setAttribute('fill', 'none');
    iconSvg.setAttribute('stroke', 'currentColor');
    iconSvg.setAttribute('viewBox', '0 0 24 24');
    iconSvg.innerHTML = '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>';

    const textSpan = document.createElement('span');
    textSpan.className = 'text-xs';
    textSpan.textContent = 'Agregar imagen';

    const btnEliminar = document.createElement('button');
    btnEliminar.type = 'button';
    btnEliminar.className = 'btn-eliminar-imagen absolute top-1 right-1 bg-red-500 text-white rounded-full w-5 h-5 flex items-center justify-center text-xs hidden hover:bg-red-600';
    btnEliminar.textContent = '\u00D7';
    btnEliminar.addEventListener('click', function (e) {
        e.stopPropagation();
        limpiarSlot(slot);
    });

    const indicador = document.createElement('div');
    indicador.className = 'indicador-arrastre absolute top-1 left-1 text-stone-300 hidden';
    indicador.innerHTML = '<svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M8 6a2 2 0 1 1 0-4 2 2 0 0 1 0 4zm8 0a2 2 0 1 1 0-4 2 2 0 0 1 0 4zM8 14a2 2 0 1 1 0-4 2 2 0 0 1 0 4zm8 0a2 2 0 1 1 0-4 2 2 0 0 1 0 4zM8 22a2 2 0 1 1 0-4 2 2 0 0 1 0 4zm8 0a2 2 0 1 1 0-4 2 2 0 0 1 0 4z"/></svg>';

    slot.appendChild(fileInput);
    slot.appendChild(ordenInput);
    slot.appendChild(iconSvg);
    slot.appendChild(textSpan);
    slot.appendChild(btnEliminar);
    slot.appendChild(indicador);

    fileInput.addEventListener('change', function (e) {
        const file = e.target.files[0];
        if (!file) return;
        slot.classList.remove('border-dashed', 'border-stone-300', 'hover:border-emerald-400', 'text-stone-400', 'hover:text-emerald-500');
        slot.classList.add('border-stone-300', 'bg-stone-50');
        slot.innerHTML = '';
        slot.appendChild(createImgElement(file));
        slot.appendChild(btnEliminar);
        slot.appendChild(indicador);
        slot.appendChild(fileInput);
        slot.dataset.existing = '0';
        fileInput.classList.add('hidden');
        btnEliminar.classList.remove('hidden');
        indicador.classList.remove('hidden');
        slot.draggable = true;
    });

    slot.addEventListener('click', function () {
        if (this.dataset.existing === '1') return;
        const inp = this.querySelector('input[type="file"]');
        inp?.click();
    });

    configurarDragSlot(slot);
}

function intercambiarSlots(a, b) {
    const aHtml = a.innerHTML;
    const aDraggable = a.draggable;
    const aExisting = a.dataset.existing;

    a.innerHTML = b.innerHTML;
    a.draggable = b.draggable;
    a.dataset.existing = b.dataset.existing;

    if (a.dataset.existing === '0') {
        a.classList.remove('bg-stone-50');
        a.classList.add('border-dashed', 'border-stone-300', 'hover:border-emerald-400', 'text-stone-400', 'hover:text-emerald-500');
    } else {
        a.classList.remove('border-dashed', 'border-stone-300', 'hover:border-emerald-400', 'text-stone-400', 'hover:text-emerald-500');
        a.classList.add('border-stone-300', 'bg-stone-50');
    }

    b.innerHTML = aHtml;
    b.draggable = aDraggable;
    b.dataset.existing = aExisting;

    if (b.dataset.existing === '0') {
        b.classList.remove('bg-stone-50');
        b.classList.add('border-dashed', 'border-stone-300', 'hover:border-emerald-400', 'text-stone-400', 'hover:text-emerald-500');
    } else {
        b.classList.remove('border-dashed', 'border-stone-300', 'hover:border-emerald-400', 'text-stone-400', 'hover:text-emerald-500');
        b.classList.add('border-stone-300', 'bg-stone-50');
    }

    reinstalarEventos(a);
    reinstalarEventos(b);
}

function reinstalarEventos(slot) {
    const fileInput = slot.querySelector('input[type="file"]');
    const btnEliminar = slot.querySelector('.btn-eliminar-imagen');

    if (fileInput) {
        fileInput.addEventListener('change', function (e) {
            const file = e.target.files[0];
            if (!file) return;
            slot.classList.remove('border-dashed', 'border-stone-300', 'hover:border-emerald-400', 'text-stone-400', 'hover:text-emerald-500');
            slot.classList.add('border-stone-300', 'bg-stone-50');
            slot.innerHTML = '';
            slot.appendChild(createImgElement(file));
            slot.appendChild(btnEliminar);
            slot.appendChild(slot.querySelector('.indicador-arrastre'));
            slot.appendChild(fileInput);
            slot.dataset.existing = '0';
            fileInput.classList.add('hidden');
            btnEliminar.classList.remove('hidden');
            slot.querySelector('.indicador-arrastre')?.classList.remove('hidden');
            slot.draggable = true;
        });
    }

    btnEliminar?.addEventListener('click', function (e) {
        e.stopPropagation();
        const idProductoImagen = slot.dataset.imgId;
        if (idProductoImagen) {
            const container = document.getElementById('imagenes-eliminar-container');
            if (container) {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'imagenes_eliminar[]';
                input.value = idProductoImagen;
                container.appendChild(input);
            }
        }
        limpiarSlot(slot);
    });

    slot.addEventListener('click', function () {
        if (this.dataset.existing === '1') return;
        const inp = this.querySelector('input[type="file"]');
        inp?.click();
    });

    configurarDragSlot(slot);
}

function configurarDragSlot(slot) {
    slot.removeEventListener('dragstart', slot._dragStart);
    slot.removeEventListener('dragend', slot._dragEnd);
    slot.removeEventListener('dragover', slot._dragOver);
    slot.removeEventListener('dragleave', slot._dragLeave);
    slot.removeEventListener('drop', slot._drop);

    slot._dragStart = function (e) {
        if (!this.draggable) { e.preventDefault(); return; }
        dragSource = this;
        this.classList.add('opacity-50', 'border-emerald-500');
        e.dataTransfer.effectAllowed = 'move';
    };
    slot._dragEnd = function () {
        this.classList.remove('opacity-50', 'border-emerald-500');
        dragSource = null;
    };
    slot._dragOver = function (e) {
        e.preventDefault();
        if (this !== dragSource) {
            this.classList.add('border-emerald-500');
        }
    };
    slot._dragLeave = function () {
        this.classList.remove('border-emerald-500');
    };
    slot._drop = function (e) {
        e.preventDefault();
        this.classList.remove('border-emerald-500');
        if (dragSource && dragSource !== this) {
            intercambiarSlots(dragSource, this);
        }
    };

    slot.addEventListener('dragstart', slot._dragStart);
    slot.addEventListener('dragend', slot._dragEnd);
    slot.addEventListener('dragover', slot._dragOver);
    slot.addEventListener('dragleave', slot._dragLeave);
    slot.addEventListener('drop', slot._drop);
}

export function sincronizarOrden(form) {
    const gestor = document.getElementById('gestor-imagenes');
    if (!gestor) return;

    const slots = gestor.querySelectorAll('.slot-imagen');

    slots.forEach((slot, index) => {
        const ordenInputs = slot.querySelectorAll('.input-orden');
        ordenInputs.forEach(inp => inp.value = index);

        const files = slot.querySelectorAll('input[type="file"]');
        files.forEach(inp => inp.name = 'imagenes[]');

        const existingImg = slot.querySelector('img');
        if (existingImg && slot.dataset.existing === '1') {
            const idInput = slot.querySelector('input[name^="orden_imagenes["]');
            if (idInput) idInput.value = index;
        }
    });
}
