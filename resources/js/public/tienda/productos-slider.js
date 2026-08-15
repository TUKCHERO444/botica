class ProductSlider {
    constructor(containerId, trackId, prevId, nextId) {
        this.container = document.getElementById(containerId);
        this.track = document.getElementById(trackId);
        this.prevBtn = document.getElementById(prevId);
        this.nextBtn = document.getElementById(nextId);
        this.cards = [...this.track.children];
        this.totalCards = this.cards.length;

        this.pos = 0;
        this.direction = 1;
        this.interval = null;
        this.isDragging = false;
        this.startX = 0;
        this.startPos = 0;
        this.dragOffset = 0;

        this.visible = this.getVisibleCount();
        this.maxPos = this.totalCards - this.visible;
        this.stepPercent = 100 / this.visible;

        this.bindEvents();
        this.startAutoplay();
    }

    getVisibleCount() {
        const gap = 16;
        const cardWidth = this.cards[0]?.offsetWidth || 0;
        const containerWidth = this.container.offsetWidth;
        if (!cardWidth || !containerWidth) return 5;
        return Math.max(1, Math.round((containerWidth + gap) / (cardWidth + gap)));
    }

    recalc() {
        const prevVisible = this.visible;
        this.visible = this.getVisibleCount();
        if (this.visible === prevVisible) return;
        this.maxPos = Math.max(0, this.totalCards - this.visible);
        this.stepPercent = 100 / this.visible;
        if (this.pos > this.maxPos) this.pos = this.maxPos;
        this.goToPos(this.pos);
    }

    goToPos(index) {
        if (index < 0) index = 0;
        if (index > this.maxPos) index = this.maxPos;
        this.pos = index;
        this.track.style.transform = `translateX(-${this.pos * this.stepPercent}%)`;
    }

    next() {
        if (this.direction === 1) {
            if (this.pos < this.maxPos) this.goToPos(this.pos + 1);
            if (this.pos === this.maxPos) this.direction = -1;
        } else {
            if (this.pos > 0) this.goToPos(this.pos - 1);
            if (this.pos === 0) this.direction = 1;
        }
    }

    prev() {
        if (this.direction === -1) {
            if (this.pos > 0) this.goToPos(this.pos - 1);
            if (this.pos === 0) this.direction = 1;
        } else {
            if (this.pos < this.maxPos) this.goToPos(this.pos + 1);
            if (this.pos === this.maxPos) this.direction = -1;
        }
        this.stopAutoplay();
        this.startAutoplay();
    }

    startAutoplay() {
        this.stopAutoplay();
        this.interval = setInterval(() => this.next(), 5000);
    }

    stopAutoplay() {
        clearInterval(this.interval);
    }

    bindEvents() {
        this.nextBtn.addEventListener('click', () => this.next());
        this.prevBtn.addEventListener('click', () => this.prev());

        const getX = (e) => e.touches ? e.touches[0].clientX : e.clientX;

        const onStart = (e) => {
            this.isDragging = true;
            this.startX = getX(e);
            this.startPos = this.pos;
            this.dragOffset = 0;
            this.stopAutoplay();
        };

        const onMove = (e) => {
            if (!this.isDragging) return;
            if (e.cancelable) e.preventDefault();
            this.dragOffset = getX(e) - this.startX;
            const movePercent = (this.dragOffset / this.container.offsetWidth) * 100;
            this.track.style.transform = `translateX(${-this.startPos * this.stepPercent + movePercent}%)`;
            this.track.style.transition = 'none';
        };

        const onEnd = () => {
            if (!this.isDragging) return;
            this.isDragging = false;
            this.track.style.transition = 'transform 500ms ease-in-out';

            const movedSlides = -(this.dragOffset / this.container.offsetWidth) * this.visible;
            const newPos = Math.round(this.startPos + movedSlides);
            this.goToPos(Math.max(0, Math.min(newPos, this.maxPos)));

            this.startAutoplay();
        };

        this.container.addEventListener('mousedown', onStart);
        document.addEventListener('mousemove', onMove);
        document.addEventListener('mouseup', onEnd);

        this.container.addEventListener('touchstart', onStart, { passive: true });
        document.addEventListener('touchmove', onMove, { passive: false });
        document.addEventListener('touchend', onEnd);
        document.addEventListener('touchcancel', onEnd);

        this.container.addEventListener('dragstart', (e) => e.preventDefault());

        window.addEventListener('resize', () => this.recalc());
    }
}

new ProductSlider('slider-buscados', 'slider-buscados-track', 'slider-buscados-prev', 'slider-buscados-next');
new ProductSlider('slider-vendidos', 'slider-vendidos-track', 'slider-vendidos-prev', 'slider-vendidos-next');
new ProductSlider('slider-descuentos', 'slider-descuentos-track', 'slider-descuentos-prev', 'slider-descuentos-next');
