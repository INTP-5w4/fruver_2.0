document.addEventListener('DOMContentLoaded', function () {

    const raw   = JSON.parse(document.getElementById('chart-data').textContent);
    const green = '#4caf50';
    const teal  = '#26a69a';
    const red   = '#ef5350';
    const amber = '#ffa726';

    // ── Helpers ──────────────────────────────────────────
    function labels(arr, key) { return arr.map(r => r[key]); }
    function values(arr, key) { return arr.map(r => parseFloat(r[key]) || 0); }

    // ── 1. Pedidos por mes (barras) ───────────────────────
    new Chart(document.getElementById('pedidosChart'), {
        type: 'bar',
        data: {
            labels: labels(raw.pedidosPorMes, 'mes'),
            datasets: [{
                label: 'Pedidos',
                data: values(raw.pedidosPorMes, 'total'),
                backgroundColor: green,
                borderRadius: 6,
            }]
        },
        options: {
            responsive: true,
            plugins: { legend: { display: false } },
            scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } }
        }
    });

    // ── 2. Total en ventas por mes (línea) ────────────────
    new Chart(document.getElementById('ventasChart'), {
        type: 'line',
        data: {
            labels: labels(raw.ventasPorMes, 'mes'),
            datasets: [{
                label: 'Ventas ($)',
                // Línea del gráfico de ventas
                data: values(raw.ventasPorMes, 'ventas'),
                borderColor: teal,
                backgroundColor: teal + '22',
                fill: true,
                tension: 0.4,
                pointRadius: 4,
            }]
        },
        options: {
            responsive: true,
            plugins: { legend: { display: false } },
            scales: { y: { beginAtZero: true } }
        }
    });

    // ── 3. Top productos más vendidos (barras horizontales) 
    new Chart(document.getElementById('topProductosChart'), {
        type: 'bar',
        data: {
            labels: labels(raw.topProductos, 'nombre'),
            datasets: [{
                label: 'Unidades vendidas',
                data: values(raw.topProductos, 'total_vendido'),
                backgroundColor: amber,
                borderRadius: 6,
            }]
        },
        options: {
            indexAxis: 'y',
            responsive: true,
            plugins: { legend: { display: false } },
            scales: { x: { beginAtZero: true } }
        }
    });

    // ── 4. Pérdidas por merma por mes (línea roja) ────────
    new Chart(document.getElementById('mermaChart'), {
        type: 'line',
        data: {
            labels: labels(raw.perdidasMerma, 'mes'),
            datasets: [{
                label: 'Pérdida estimada ($)',
                data: values(raw.perdidasMerma, 'perdida'),
                borderColor: red,
                backgroundColor: red + '22',
                fill: true,
                tension: 0.4,
                pointRadius: 4,
            }]
        },
        options: {
            responsive: true,
            plugins: { legend: { display: false } },
            scales: { y: { beginAtZero: true } }
        }
    });
});