import Chart from 'chart.js/auto';

function initCharts() {
    const canvasSemanal = document.getElementById('chartSemanal');
    const canvasDistribucion = document.getElementById('chartDistribucion');

    if (!canvasSemanal || !canvasDistribucion) return;

    const existingSemanal = Chart.getChart(canvasSemanal);
    if (existingSemanal) existingSemanal.destroy();

    const existingDist = Chart.getChart(canvasDistribucion);
    if (existingDist) existingDist.destroy();

    try {
        const labels = JSON.parse(canvasSemanal.dataset.labels);
        const ventas = JSON.parse(canvasSemanal.dataset.ventas);
        const atenciones = JSON.parse(canvasSemanal.dataset.atenciones);

        new Chart(canvasSemanal, {
            type: 'bar',
            data: {
                labels,
                datasets: [
                    {
                        label: 'Ventas',
                        data: ventas,
                        backgroundColor: '#10b981',
                        borderRadius: 4,
                    },
                    {
                        label: 'Atenciones',
                        data: atenciones,
                        backgroundColor: '#f59e0b',
                        borderRadius: 4,
                    },
                ],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'top',
                        labels: { boxWidth: 12, padding: 12, font: { size: 12 } },
                    },
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: (v) => 'S/ ' + v.toFixed(2),
                            font: { size: 11 },
                        },
                    },
                    x: {
                        ticks: { font: { size: 10 } },
                    },
                },
            },
        });
    } catch (e) {
        console.warn('Error al iniciar chart semanal:', e);
    }

    try {
        const totalVentas = parseFloat(canvasDistribucion.dataset.ventas) || 0;
        const totalAtenciones = parseFloat(canvasDistribucion.dataset.atenciones) || 0;

        if (totalVentas === 0 && totalAtenciones === 0) {
            const ctx = canvasDistribucion.getContext('2d');
            if (ctx) {
                ctx.font = '14px system-ui';
                ctx.fillStyle = '#a8a29e';
                ctx.textAlign = 'center';
                ctx.fillText('Sin datos este mes', canvasDistribucion.width / 2, canvasDistribucion.height / 2);
            }
            return;
        }

        new Chart(canvasDistribucion, {
            type: 'doughnut',
            data: {
                labels: ['Ventas', 'Atenciones'],
                datasets: [
                    {
                        data: [totalVentas, totalAtenciones],
                        backgroundColor: ['#10b981', '#f59e0b'],
                        borderWidth: 0,
                    },
                ],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: { boxWidth: 12, padding: 12, font: { size: 12 } },
                    },
                    tooltip: {
                        callbacks: {
                            label: (ctx) => {
                                const total = ctx.dataset.data.reduce((a, b) => a + b, 0);
                                const pct = ((ctx.parsed / total) * 100).toFixed(1);
                                return ctx.label + ': S/ ' + ctx.parsed.toFixed(2) + ' (' + pct + '%)';
                            },
                        },
                    },
                },
            },
        });
    } catch (e) {
        console.warn('Error al iniciar chart distribucion:', e);
    }
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initCharts);
} else {
    initCharts();
}
