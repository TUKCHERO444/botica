document.addEventListener('DOMContentLoaded', () => {
    const track = document.getElementById('header-marquee-track');
    if (!track) return;

    const content = track.querySelector('.marquee-content');
    if (!content) return;

    const text = content.textContent.trim();
    if (!text) return;

    content.textContent = text;

    track.classList.add('animating');

    const container = track.parentElement;
    const viewportWidth = container.offsetWidth;
    const copyWidth = content.offsetWidth;

    setTimeout(() => {
        track.style.transition = 'opacity 0.35s ease';
        track.style.opacity = '0';

        setTimeout(() => {
            const clone = content.cloneNode(true);
            track.appendChild(clone);

            const startX = Math.max(0, viewportWidth - copyWidth);
            const endX = startX - copyWidth;

            const charsPerSecond = 4;
            const duration = Math.max(text.length / charsPerSecond, 8);

            const styleId = 'header-marquee-dynamic';
            let styleEl = document.getElementById(styleId);
            if (!styleEl) {
                styleEl = document.createElement('style');
                styleEl.id = styleId;
                document.head.appendChild(styleEl);
            }

            styleEl.textContent = `
                @keyframes header-marquee-loop {
                    0% { transform: translateX(${startX}px); }
                    100% { transform: translateX(${endX}px); }
                }
            `;

            container.style.justifyContent = 'flex-start';
            track.style.transition = 'none';
            track.style.opacity = '1';
            track.style.animation = `header-marquee-loop ${duration}s linear infinite`;
        }, 350);
    }, 2000);
});
