// Espera a que Chart.js esté disponible antes de inicializar
function initCharts() {
    if (typeof Chart === 'undefined') {
        setTimeout(initCharts, 50);
        return;
    }

    const raw   = JSON.parse(document.getElementById('chart-data').textContent);
    const green = '#4caf50';
    const teal  = '#26a69a';
    const red   = '#ef5350';
    const amber = '#ffa726';

    function labels(arr, key) { return arr.map(r => r[key]); }
    function values(arr, key) { return arr.map(r => parseFloat(r[key]) || 0); }

    // ── 1. Pedidos por mes (barras) ───────────────────────
    const ctxPedidos = document.getElementById('pedidosChart');
    if (ctxPedidos && raw.pedidosPorMes.length) {
        new Chart(ctxPedidos, {
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
    }

    // ── 2. Total en ventas por mes (línea) ────────────────
    const ctxVentas = document.getElementById('ventasChart');
    if (ctxVentas && raw.ventasPorMes.length) {
        new Chart(ctxVentas, {
            type: 'line',
            data: {
                labels: labels(raw.ventasPorMes, 'mes'),
                datasets: [{
                    label: 'Ventas ($)',
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
    } else if (ctxVentas) {
        ctxVentas.parentElement.innerHTML = '<p style="color:#aaa;text-align:center;padding:2rem;">Sin ventas registradas</p>';
    }

    // ── 3. Top productos más vendidos (barras horizontales)
    const ctxTop = document.getElementById('topProductosChart');
    if (ctxTop && raw.topProductos.length) {
        new Chart(ctxTop, {
            type: 'bar',
            data: {
                labels: labels(raw.topProductos, 'nombre'),
                datasets: [{
                    label: 'Ingresos ($)',
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
    } else if (ctxTop) {
        ctxTop.parentElement.innerHTML = '<p style="color:#aaa;text-align:center;padding:2rem;">Sin productos vendidos</p>';
    }

    // ── 4. Pérdidas por merma por mes (línea roja) ────────
    const ctxMerma = document.getElementById('mermaChart');
    if (ctxMerma && raw.perdidasMerma.length) {
        new Chart(ctxMerma, {
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
    } else if (ctxMerma) {
        ctxMerma.parentElement.innerHTML = '<p style="color:#aaa;text-align:center;padding:2rem;">Sin mermas registradas</p>';
    }
}

document.addEventListener('DOMContentLoaded', initCharts);