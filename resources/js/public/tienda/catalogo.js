const track = document.getElementById('carousel-track');
const prevBtn = document.getElementById('carousel-prev');
const nextBtn = document.getElementById('carousel-next');
const dots = document.querySelectorAll('.carousel-dot');
let currentIndex = 0;
const totalSlides = dots.length;
let interval;

function goToSlide(index) {
    if (index < 0) index = totalSlides - 1;
    if (index >= totalSlides) index = 0;
    currentIndex = index;
    track.style.transform = `translateX(-${currentIndex * 100}%)`;

    dots.forEach((dot, i) => {
        dot.classList.toggle('bg-white', i === currentIndex);
        dot.classList.toggle('bg-white/50', i !== currentIndex);
        dot.setAttribute('aria-selected', i === currentIndex ? 'true' : 'false');
    });
}

function nextSlide() {
    goToSlide(currentIndex + 1);
}

function prevSlide() {
    goToSlide(currentIndex - 1);
}

function startAutoplay() {
    stopAutoplay();
    interval = setInterval(nextSlide, 5000);
}

function stopAutoplay() {
    clearInterval(interval);
}

prevBtn.addEventListener('click', () => {
    prevSlide();
    startAutoplay();
});

nextBtn.addEventListener('click', () => {
    nextSlide();
    startAutoplay();
});

dots.forEach(dot => {
    dot.addEventListener('click', () => {
        goToSlide(parseInt(dot.dataset.index));
        startAutoplay();
    });
});

document.addEventListener('keydown', (e) => {
    if (e.key === 'ArrowLeft') { prevSlide(); startAutoplay(); }
    if (e.key === 'ArrowRight') { nextSlide(); startAutoplay(); }
});

startAutoplay();
