import L from 'leaflet';

document.addEventListener('DOMContentLoaded', () => {
    const mapContainer = document.getElementById('footer-map');
    if (!mapContainer) return;

    const map = L.map(mapContainer, {
        zoomControl: false,
        dragging: false,
        scrollWheelZoom: false,
        touchZoom: false,
        doubleClickZoom: false,
        boxZoom: false,
        keyboard: false,
    }).setView([-12.046374, -77.042793], 15);

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; <a href="https://openstreetmap.org/copyright">OSM</a>',
    }).addTo(map);

    L.marker([-12.046374, -77.042793]).addTo(map);
});
