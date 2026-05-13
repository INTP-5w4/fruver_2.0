<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FRUVER — Panel de Control</title>
    <link href="https://fonts.googleapis.com/css2?family=Instrument+Serif:ital@0;1&family=DM+Sans:ital,wght@0,400;0,500;1,400&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://www.w3schools.com/w3css/5/w3.css">
    <link rel="stylesheet" href="<?= base_url('estilos/Header.css') ?>">
    <link rel="stylesheet" href="<?= base_url('estilos/dashboard.css') ?>">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>

<?php include 'Header.php'; ?>

<div class="fv-layout">

    <!-- SIDEBAR -->
    <aside class="fv-sidebar">
        <div class="fv-sidebar-group">Principal</div>
        <a href="<?= base_url('dashboard') ?>" class="fv-slink fv-slink--active">
            <i class="fa-solid fa-gauge-high"></i>
            Dashboard
        </a>
        <a href="<?= base_url('lista_pedido') ?>" class="fv-slink">
            <i class="fa-solid fa-cart-shopping"></i>
            Pedidos
            <?php if (!empty($pedidosPendientes)): ?>
                <span class="fv-slink-dot"></span>
            <?php endif; ?>
        </a>
        <a href="<?= base_url('lista_producto') ?>" class="fv-slink">
            <i class="fa-solid fa-box"></i>
            Productos
        </a>
        <a href="<?= base_url('lista_cliente') ?>" class="fv-slink">
            <i class="fa-solid fa-users"></i>
            Clientes
        </a>
        <a href="<?= base_url('lista_repartidor') ?>" class="fv-slink">
            <i class="fa-solid fa-bicycle"></i>
            Repartidores
        </a>

        <div class="fv-sidebar-group">Inventario</div>
        <a href="<?= base_url('lista_entrada') ?>" class="fv-slink">
            <i class="fa-solid fa-arrow-down-to-bracket"></i>
            Entradas
        </a>
        <a href="<?= base_url('lista_existencia') ?>" class="fv-slink">
            <i class="fa-solid fa-chart-simple"></i>
            Existencias
        </a>
        <a href="<?= base_url('lista_merma') ?>" class="fv-slink">
            <i class="fa-solid fa-trash-can"></i>
            Mermas
        </a>

        <div class="fv-sidebar-group">Operación</div>
        <a href="<?= base_url('lista_estatus') ?>" class="fv-slink">
            <i class="fa-solid fa-timeline"></i>
            Estatus
        </a>
        <a href="<?= base_url('lista_direccion') ?>" class="fv-slink">
            <i class="fa-solid fa-map-pin"></i>
            Direcciones
        </a>
        <a href="<?= base_url('lista_p_pedido') ?>" class="fv-slink">
            <i class="fa-solid fa-basket-shopping"></i>
            Prod. por pedido
        </a>
    </aside>

    <!-- CONTENIDO PRINCIPAL -->
    <main class="fv-main">

        <!-- Mensajes flash -->
        <?php if (session()->getFlashdata('error')): ?>
            <div class="fv-flash fv-flash--error">
                <?= session()->getFlashdata('error') ?>
            </div>
        <?php endif; ?>
        <?php if (session()->getFlashdata('mensaje')): ?>
            <div class="fv-flash fv-flash--ok">
                <?= session()->getFlashdata('mensaje') ?>
            </div>
        <?php endif; ?>

        <!-- ENCABEZADO -->
        <div class="fv-page-header">
            <div>
                <h1 class="fv-page-title">Panel de control</h1>
                <p class="fv-page-sub">Todo lo que necesitas saber hoy de un vistazo.</p>
            </div>
            <a href="<?= base_url('lista_pedido') ?>" class="fv-btn-primary">
                <i class="fa-solid fa-plus"></i> Nuevo pedido
            </a>
        </div>

        <!-- KPI CARDS -->
        <section class="fv-kpi-grid">

            <div class="fv-kpi fv-kpi--green">
                <div class="fv-kpi-top">
                    <div class="fv-kpi-icon">
                        <i class="fa-solid fa-cart-shopping"></i>
                    </div>
                </div>
                <div class="fv-kpi-val"><?= count($pedidos) ?></div>
                <div class="fv-kpi-lbl">Pedidos este mes</div>
            </div>

            <div class="fv-kpi fv-kpi--amber">
                <div class="fv-kpi-top">
                    <div class="fv-kpi-icon">
                        <i class="fa-solid fa-peso-sign"></i>
                    </div>
                </div>
                <div class="fv-kpi-val">
                    $<?= number_format($ventasTotales ?? 0, 0, '.', ',') ?>
                </div>
                <div class="fv-kpi-lbl">Ventas del mes</div>
            </div>

            <div class="fv-kpi fv-kpi--red">
                <div class="fv-kpi-top">
                    <div class="fv-kpi-icon">
                        <i class="fa-solid fa-leaf"></i>
                    </div>
                </div>
                <div class="fv-kpi-val">
                    $<?= number_format($perdidasMerma ?? 0, 0, '.', ',') ?>
                </div>
                <div class="fv-kpi-lbl">Pérdidas por merma</div>
            </div>

            <div class="fv-kpi fv-kpi--teal">
                <div class="fv-kpi-top">
                    <div class="fv-kpi-icon">
                        <i class="fa-solid fa-users"></i>
                    </div>
                </div>
                <div class="fv-kpi-val"><?= count($clientes) ?></div>
                <div class="fv-kpi-lbl">Clientes activos</div>
            </div>

        </section>

        <!-- FILA MEDIA: gráfica + insights -->
        <section class="fv-mid-row">

            <div class="fv-card">
                <div class="fv-card-head">
                    <span class="fv-card-title">Ventas mensuales</span>
                    <span class="fv-badge">Últimos 6 meses</span>
                </div>
                <div class="fv-legend">
                    <span class="fv-legend-item">
                        <span class="fv-legend-dot fv-legend-dot--green"></span>Ventas $
                    </span>
                    <span class="fv-legend-item">
                        <span class="fv-legend-dot fv-legend-dot--coral fv-legend-dot--dashed"></span>Pedidos
                    </span>
                </div>
                <div class="fv-chart-wrap">
                    <canvas id="ventasChart"
                        role="img"
                        aria-label="Gráfica de barras: ventas en pesos y número de pedidos por mes, últimos 6 meses">
                        Datos de ventas mensuales de FRUVER.
                    </canvas>
                </div>
            </div>

            <div class="fv-insights">
                <div class="fv-insights-title">
                    <i class="fa-solid fa-lightbulb"></i> Datos del negocio
                </div>
                <?php if (!empty($topProducto)): ?>
                <div class="fv-insight-item">
                    <i class="fa-solid fa-arrow-trend-up fv-insight-icon"></i>
                    <p>El producto más vendido es <strong><?= esc($topProducto['nombre']) ?></strong> con <?= $topProducto['total_cant'] ?> unidades despachadas.</p>
                </div>
                <?php endif; ?>
                <?php if (!empty($productosLowStock)): ?>
                <div class="fv-insight-item">
                    <i class="fa-solid fa-triangle-exclamation fv-insight-icon"></i>
                    <p><strong><?= esc($productosLowStock[0]['nombre'] ?? '') ?></strong> y otros productos están por agotarse — menos de 5 entradas disponibles.</p>
                </div>
                <?php endif; ?>
                <?php if (!empty($topCliente)): ?>
                <div class="fv-insight-item">
                    <i class="fa-solid fa-star fv-insight-icon"></i>
                    <p>El cliente con más compras este mes es <strong><?= esc($topCliente['nombre'] . ' ' . $topCliente['ape_pat']) ?></strong>.</p>
                </div>
                <?php endif; ?>
                <div class="fv-insight-item">
                    <i class="fa-solid fa-box-open fv-insight-icon"></i>
                    <p>Hay <strong><?= count($productos) ?></strong> productos registrados en el catálogo actualmente.</p>
                </div>
            </div>

        </section>

        <!-- FILA INFERIOR: stock bajo + pedidos recientes -->
        <section class="fv-bot-row">

            <div class="fv-card">
                <div class="fv-card-head">
                    <span class="fv-card-title">Stock bajo</span>
                    <span class="fv-badge">Top 5 productos</span>
                </div>
                <table class="fv-table">
                    <thead>
                        <tr>
                            <th>Producto</th>
                            <th>Entradas</th>
                            <th>Categoría</th>
                            <th>Estado</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($productosLowStock as $p): ?>
                        <tr>
                            <td><?= esc($p['nombre']) ?></td>
                            <td><?= $p['total'] ?? 0 ?></td>
                            <td><?= ucfirst(esc($p['categoria'])) ?></td>
                            <td>
                                <?php $total = $p['total'] ?? 0; ?>
                                <?php if ($total <= 4): ?>
                                    <span class="fv-status fv-status--low">Bajo</span>
                                <?php elseif ($total <= 9): ?>
                                    <span class="fv-status fv-status--med">Medio</span>
                                <?php else: ?>
                                    <span class="fv-status fv-status--ok">OK</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <a href="<?= base_url('lista_existencia') ?>" class="fv-table-link">Ver existencias completas →</a>
            </div>

            <div class="fv-card">
                <div class="fv-card-head">
                    <span class="fv-card-title">Pedidos recientes</span>
                    <span class="fv-badge">Esta semana</span>
                </div>
                <div class="fv-pedidos">
                    <?php foreach (array_slice($pedidosRecientes ?? [], 0, 5) as $ped): ?>
                    <div class="fv-pedido-item">
                        <div class="fv-pedido-num">#<?= $ped['id'] ?></div>
                        <div class="fv-pedido-info">
                            <div class="fv-pedido-name">
                                <?= esc($ped['nombre'] . ' ' . $ped['ape_pat']) ?>
                            </div>
                            <div class="fv-pedido-date"><?= $ped['fecha'] ?></div>
                        </div>
                        <?php
                            $edo = $ped['ultimo_estado'] ?? 'pedido_pendiente';
                            $estadoMap = [
                                'pedido_realizado'   => ['lbl' => 'Realizado',  'cls' => 'fv-status--info'],
                                'pedido_confirmado'  => ['lbl' => 'Confirmado', 'cls' => 'fv-status--info'],
                                'pedido_en_transito' => ['lbl' => 'Tránsito',   'cls' => 'fv-status--med'],
                                'pedido_entregado'   => ['lbl' => 'Entregado',  'cls' => 'fv-status--ok'],
                                'pedido_pagado'      => ['lbl' => 'Pagado',     'cls' => 'fv-status--ok'],
                                'pedido_a_credito'   => ['lbl' => 'Crédito',    'cls' => 'fv-status--med'],
                                'pedido_cancelado'   => ['lbl' => 'Cancelado',  'cls' => 'fv-status--low'],
                                'pedido_pendiente'   => ['lbl' => 'Pendiente',  'cls' => 'fv-status--info'],
                            ];
                            $info = $estadoMap[$edo] ?? ['lbl' => ucfirst($edo), 'cls' => 'fv-status--info'];
                        ?>
                        <span class="fv-status <?= $info['cls'] ?>"><?= $info['lbl'] ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>
                <a href="<?= base_url('lista_pedido') ?>" class="fv-table-link">Ver todos los pedidos →</a>
            </div>

        </section>

    </main>
</div>

<!-- Datos para Chart.js inyectados desde PHP -->
<script id="chart-data" type="application/json">
<?= json_encode([
    'pedidosPorMes' => $pedidosPorMes ?? [],
    'ventasPorMes'  => $ventasPorMes  ?? [],
]) ?>
</script>

<script src="<?= base_url('js/dashboard.js') ?>"></script>

</body>
</html>