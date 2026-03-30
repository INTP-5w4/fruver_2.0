// ============================================
//  FRUVER — dashboard.js
//  Gráficas con Chart.js (datos de ejemplo)
//  Reemplaza los arrays de `data` con valores
//  reales traídos desde PHP/CI4.
// ============================================

const meses = ['Oct', 'Nov', 'Dic', 'Ene', 'Feb', 'Mar'];

// ---- Colores reutilizables ----
const GREEN_DARK  = '#094e00';
const GREEN_MID   = '#1a7a0a';
const GREEN_LIGHT = 'rgba(214, 240, 200, 0.5)';
const TEAL        = '#0d7a6b';
const TEAL_LIGHT  = 'rgba(194, 237, 231, 0.5)';

// ============================================
// 1. Gráfica de barras — Pedidos por mes
// ============================================
const pedidosCtx = document.getElementById('pedidosChart');
if (pedidosCtx) {
    new Chart(pedidosCtx, {
        type: 'bar',
        data: {
            labels: meses,
            datasets: [{
                label: 'Pedidos',
                // TODO: reemplaza con datos reales desde PHP
                data: [18, 24, 31, 20, 27, 35],
                backgroundColor: GREEN_MID,
                borderRadius: 6,
                borderSkipped: false,
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                tooltip: {
                    callbacks: {
                        label: ctx => ` ${ctx.parsed.y} pedidos`
                    }
                }
            },
            scales: {
                x: {
                    grid: { display: false },
                    ticks: { font: { family: 'DM Sans', size: 11 } }
                },
                y: {
                    beginAtZero: true,
                    grid: { color: '#eef2e8' },
                    ticks: { font: { family: 'DM Sans', size: 11 } }
                }
            }
        }
    });
}

// ============================================
// 2. Gráfica de línea — Entradas por mes
// ============================================
const entradasCtx = document.getElementById('entradasChart');
if (entradasCtx) {
    new Chart(entradasCtx, {
        type: 'line',
        data: {
            labels: meses,
            datasets: [{
                label: 'Entradas',
                // TODO: reemplaza con datos reales desde PHP
                data: [12, 19, 14, 22, 18, 25],
                borderColor: TEAL,
                backgroundColor: TEAL_LIGHT,
                tension: 0.4,
                fill: true,
                pointBackgroundColor: TEAL,
                pointRadius: 4,
                pointHoverRadius: 6,
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                tooltip: {
                    callbacks: {
                        label: ctx => ` ${ctx.parsed.y} entradas`
                    }
                }
            },
            scales: {
                x: {
                    grid: { display: false },
                    ticks: { font: { family: 'DM Sans', size: 11 } }
                },
                y: {
                    beginAtZero: true,
                    grid: { color: '#eef2e8' },
                    ticks: { font: { family: 'DM Sans', size: 11 } }
                }
            }
        }
    });
}
