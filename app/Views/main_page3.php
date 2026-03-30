<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FRUVER — Panel de Control</title>
    <link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= base_url('estilos/Header.css') ?>">
    <link rel="stylesheet" href="<?= base_url('estilos/dashboard.css') ?>">
</head>
<body>

    <?php include 'Header.php'; ?>

    <div class="dashboard-wrapper">

        <!-- SIDEBAR -->
        <aside class="sidebar">
            <nav class="sidebar-nav">
                <p class="nav-label">Módulos</p>

                <a href="<?= base_url('lista_producto') ?>" class="nav-item">
                    <span class="nav-icon">📦</span> Productos
                </a>
                <a href="<?= base_url('lista_cliente') ?>" class="nav-item">
                    <span class="nav-icon">👤</span> Clientes
                </a>
                <a href="<?= base_url('lista_repartidor') ?>" class="nav-item">
                    <span class="nav-icon">🚚</span> Repartidores
                </a>
                <a href="<?= base_url('lista_direccion') ?>" class="nav-item">
                    <span class="nav-icon">📍</span> Direcciones
                </a>
                <a href="<?= base_url('lista_entrada') ?>" class="nav-item">
                    <span class="nav-icon">📥</span> Entradas
                </a>
                <a href="<?= base_url('lista_pedido') ?>" class="nav-item">
                    <span class="nav-icon">🧾</span> Pedidos
                </a>

                <p class="nav-label" style="margin-top:1.5rem;">Próximamente</p>

                <a href="#" class="nav-item nav-item--placeholder">
                    <span class="nav-icon">🔧</span> Módulo A
                </a>
                <a href="#" class="nav-item nav-item--placeholder">
                    <span class="nav-icon">🔧</span> Módulo B
                </a>
                <a href="#" class="nav-item nav-item--placeholder">
                    <span class="nav-icon">🔧</span> Módulo C
                </a>
                <a href="#" class="nav-item nav-item--placeholder">
                    <span class="nav-icon">🔧</span> Módulo D
                </a>
            </nav>
        </aside>

        <!-- MAIN CONTENT -->
        <main class="dashboard-main">

            <!-- PAGE TITLE -->
            <div class="page-header">
                <h1 class="page-title">Panel de Control</h1>
                <p class="page-subtitle">Resumen general del sistema FRUVER</p>
            </div>

            <!-- KPI CARDS -->
            <section class="kpi-grid">
                <div class="kpi-card kpi--green">
                    <div class="kpi-icon">📦</div>
                    <div class="kpi-info">
                        <span class="kpi-value">—</span>
                        <span class="kpi-label">Productos</span>
                    </div>
                    <a href="<?= base_url('lista_producto') ?>" class="kpi-link">Ver lista →</a>
                </div>
                <div class="kpi-card kpi--teal">
                    <div class="kpi-icon">👤</div>
                    <div class="kpi-info">
                        <span class="kpi-value">—</span>
                        <span class="kpi-label">Clientes</span>
                    </div>
                    <a href="<?= base_url('lista_cliente') ?>" class="kpi-link">Ver lista →</a>
                </div>
                <div class="kpi-card kpi--olive">
                    <div class="kpi-icon">🚚</div>
                    <div class="kpi-info">
                        <span class="kpi-value">—</span>
                        <span class="kpi-label">Repartidores</span>
                    </div>
                    <a href="<?= base_url('lista_repartidor') ?>" class="kpi-link">Ver lista →</a>
                </div>
                <div class="kpi-card kpi--dark">
                    <div class="kpi-icon">🧾</div>
                    <div class="kpi-info">
                        <span class="kpi-value">—</span>
                        <span class="kpi-label">Pedidos</span>
                    </div>
                    <a href="<?= base_url('lista_pedido') ?>" class="kpi-link">Ver lista →</a>
                </div>
            </section>

            <!-- CHARTS ROW -->
            <section class="charts-row">
                <!-- Bar Chart -->
                <div class="chart-card chart-card--wide">
                    <div class="chart-header">
                        <h2 class="chart-title">Pedidos por mes</h2>
                        <span class="chart-badge">Últimos 6 meses</span>
                    </div>
                    <div class="chart-area">
                        <canvas id="pedidosChart"></canvas>
                    </div>
                </div>

                <!-- Line Chart -->
                <div class="chart-card">
                    <div class="chart-header">
                        <h2 class="chart-title">Entradas por mes</h2>
                        <span class="chart-badge">Últimos 6 meses</span>
                    </div>
                    <div class="chart-area">
                        <canvas id="entradasChart"></canvas>
                    </div>
                </div>
            </section>

            <!-- BOTTOM ROW: Low stock table + quick actions -->
            <section class="bottom-row">

                <!-- Low-stock products table -->
                <div class="table-card">
                    <div class="chart-header">
                        <h2 class="chart-title">Productos con menos entradas</h2>
                        <span class="chart-badge">Top 5</span>
                    </div>
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Producto</th>
                                <th>Entradas</th>
                                <th>Estado</th>
                            </tr>
                        </thead>
                        <tbody>
                            <!-- Ejemplo estático; reemplaza con PHP dinámico -->
                            <?php foreach ($productosLowStock as $i => $p): ?>
                                <tr>
                                    <td><?= $i + 1 ?></td>
                                    <td><?= esc($p['nombre']) ?></td>
                                    <td><?= $p['total'] ?? 0 ?></td>
                                    <td>
                                        <?php if (($p['total'] ?? 0) <= 5): ?>
                                            <span class="badge badge--red">Bajo</span>
                                        <?php elseif ($p['total'] <= 10): ?>
                                            <span class="badge badge--yellow">Medio</span>
                                        <?php else: ?>
                                            <span class="badge badge--green">OK</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Quick actions -->
                <div class="actions-card">
                    <h2 class="chart-title" style="margin-bottom:1rem;">Acciones rápidas</h2>
                    <div class="actions-grid">
                        <a href="<?= base_url('crea_producto') ?>" class="action-btn">+ Producto</a>
                        <a href="<?= base_url('crea_cliente') ?>" class="action-btn">+ Cliente</a>
                        <a href="<?= base_url('crea_repartidor') ?>" class="action-btn">+ Repartidor</a>
                        <a href="<?= base_url('crea_direccion') ?>" class="action-btn">+ Dirección</a>
                        <a href="<?= base_url('crea_entrada') ?>" class="action-btn">+ Entrada</a>
                        <a href="<?= base_url('crea_pedido') ?>" class="action-btn">+ Pedido</a>
                    </div>
                </div>

            </section>

        </main>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="<?= base_url('js/dashboard.js') ?>"></script>
</body>
</html>
