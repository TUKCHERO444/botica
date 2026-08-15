document.addEventListener('DOMContentLoaded', function () {
    const galeria = document.getElementById('galeria-principal');
    if (!galeria) return;

    const total = parseInt(galeria.dataset.total, 10);
    const imagenPrincipal = document.getElementById('imagen-principal');
    const thumbs = document.querySelectorAll('.thumb-btn');
    const btnAnterior = document.getElementById('btn-anterior');
    const btnSiguiente = document.getElementById('btn-siguiente');

    let indiceActual = 0;
    let intervalo = null;

    function mostrarImagen(index) {
        if (total <= 1) return;
        if (index < 0) index = total - 1;
        if (index >= total) index = 0;
        indiceActual = index;

        const imgUrl = thumbs[index]?.querySelector('img')?.src;
        if (imgUrl) imagenPrincipal.src = imgUrl;

        thumbs.forEach((btn, i) => {
            btn.classList.toggle('border-emerald-500', i === index);
            btn.classList.toggle('border-transparent', i !== index);
            btn.classList.toggle('hover:border-stone-300', i !== index);
        });
    }

    function iniciarRotacion() {
        if (total <= 1) return;
        detenerRotacion();
        intervalo = setInterval(function () {
            mostrarImagen(indiceActual + 1);
        }, 5000);
    }

    function detenerRotacion() {
        if (intervalo) {
            clearInterval(intervalo);
            intervalo = null;
        }
    }

    if (total > 1) {
        thumbs.forEach(function (btn) {
            btn.addEventListener('click', function () {
                mostrarImagen(parseInt(this.dataset.index, 10));
                iniciarRotacion();
            });
        });

        if (btnAnterior) {
            btnAnterior.addEventListener('click', function () {
                mostrarImagen(indiceActual - 1);
                iniciarRotacion();
            });
        }

        if (btnSiguiente) {
            btnSiguiente.addEventListener('click', function () {
                mostrarImagen(indiceActual + 1);
                iniciarRotacion();
            });
        }

        galeria.addEventListener('mouseenter', detenerRotacion);
        galeria.addEventListener('mouseleave', iniciarRotacion);

        iniciarRotacion();
    }

    // ── Zoom inline ──

    function esTouch() {
        return 'ontouchstart' in window || navigator.maxTouchPoints > 0;
    }

    if (!esTouch()) {
        galeria.addEventListener('mouseenter', function () {
            detenerRotacion();
            imagenPrincipal.style.transition = 'none';
        });

        galeria.addEventListener('mousemove', function (e) {
            const rect = galeria.getBoundingClientRect();
            const x = ((e.clientX - rect.left) / rect.width) * 100;
            const y = ((e.clientY - rect.top) / rect.height) * 100;
            imagenPrincipal.style.transformOrigin = x + '% ' + y + '%';
            imagenPrincipal.style.transform = 'scale(2)';
        });

        galeria.addEventListener('mouseleave', function () {
            imagenPrincipal.style.transition = 'transform 0.3s ease-out';
            imagenPrincipal.style.transform = 'scale(1)';
            setTimeout(function () {
                imagenPrincipal.style.transition = '';
            }, 300);
            iniciarRotacion();
        });
    }
});
