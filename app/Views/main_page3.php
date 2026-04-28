<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FRUVER — Panel de Control</title>
    <link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= base_url('estilos/Header.css') ?>">
    <link rel="stylesheet" href="<?= base_url('estilos/dashboard.css') ?>">
    <link rel="stylesheet" href="https://www.w3schools.com/w3css/5/w3.css">
    <!-- Chart.js cargado en el HEAD para garantizar que esté disponible -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>

    <?php include 'Header.php'; ?>

    <div class="dashboard-wrapper">

        <!-- SIDEBAR -->
        <aside class="sidebar">
            <nav class="sidebar-nav">
                <p class="nav-label">Módulos</p>
                <a href="<?= base_url('lista_producto') ?>" class="nav-item">Productos</a>
                <a href="<?= base_url('lista_cliente') ?>" class="nav-item">Clientes</a>
                <a href="<?= base_url('lista_repartidor') ?>" class="nav-item">Repartidores</a>
                <a href="<?= base_url('lista_direccion') ?>" class="nav-item">Direcciones</a>
                <a href="<?= base_url('lista_entrada') ?>" class="nav-item">Entradas</a>
                <a href="<?= base_url('lista_pedido') ?>" class="nav-item">Pedidos</a>
                <a href="<?= base_url('lista_merma') ?>" class="nav-item">Mermas</a>
                <a href="<?= base_url('lista_p_pedido') ?>" class="nav-item">Productos por pedido</a>
                <a href="<?= base_url('lista_estatus') ?>" class="nav-item">Estatus</a>
                <a href="<?= base_url('lista_existencia') ?>" class="nav-item">Existencias</a>
            </nav>
        </aside>

        <!-- MAIN CONTENT -->
        <main class="dashboard-main">

            <!-- PAGE TITLE -->
            <div class="page-header">
                <h1 class="page-title">Panel de Control</h1>
                <p class="page-subtitle">Resumen general del sistema FRUVER</p>
            </div>

            <!-- KPI CARDS — valores dinámicos desde PHP -->
            <section class="kpi-grid">
                <div class="kpi-card kpi--green">
                    <div class="kpi-icon">📦</div>
                    <div class="kpi-info">
                        <span class="kpi-value"><?= count($productos) ?></span>
                        <span class="kpi-label">Productos</span>
                    </div>
                    <a href="<?= base_url('lista_producto') ?>" class="kpi-link">Ver lista →</a>
                </div>
                <div class="kpi-card kpi--teal">
                    <div class="kpi-icon">👤</div>
                    <div class="kpi-info">
                        <span class="kpi-value"><?= count($clientes) ?></span>
                        <span class="kpi-label">Clientes</span>
                    </div>
                    <a href="<?= base_url('lista_cliente') ?>" class="kpi-link">Ver lista →</a>
                </div>
                <div class="kpi-card kpi--olive">
                    <div class="kpi-icon">🚚</div>
                    <div class="kpi-info">
                        <span class="kpi-value"><?= count($repartidores) ?></span>
                        <span class="kpi-label">Repartidores</span>
                    </div>
                    <a href="<?= base_url('lista_repartidor') ?>" class="kpi-link">Ver lista →</a>
                </div>
                <div class="kpi-card kpi--dark">
                    <div class="kpi-icon">🧾</div>
                    <div class="kpi-info">
                        <span class="kpi-value"><?= count($pedidos) ?></span>
                        <span class="kpi-label">Pedidos</span>
                    </div>
                    <a href="<?= base_url('lista_pedido') ?>" class="kpi-link">Ver lista →</a>
                </div>
            </section>

            <!-- CHARTS ROW -->
            <section class="charts-row">

                <div class="chart-card chart-card--wide">
                    <div class="chart-header">
                        <h2 class="chart-title">Pedidos por mes</h2>
                        <span class="chart-badge">Últimos 6 meses</span>
                    </div>
                    <div class="chart-area">
                        <canvas id="pedidosChart"></canvas>
                    </div>
                </div>

                <div class="chart-card">
                    <div class="chart-header">
                        <h2 class="chart-title">Total en ventas</h2>
                        <span class="chart-badge">Últimos 6 meses</span>
                    </div>
                    <div class="chart-area">
                        <canvas id="ventasChart"></canvas>
                    </div>
                </div>

                <div class="chart-card chart-card--wide">
                    <div class="chart-header">
                        <h2 class="chart-title">Top 5 productos más vendidos</h2>
                        <span class="chart-badge">Histórico</span>
                    </div>
                    <div class="chart-area">
                        <canvas id="topProductosChart"></canvas>
                    </div>
                </div>

                <div class="chart-card">
                    <div class="chart-header">
                        <h2 class="chart-title">Pérdidas por merma</h2>
                        <span class="chart-badge">Últimos 6 meses</span>
                    </div>
                    <div class="chart-area">
                        <canvas id="mermaChart"></canvas>
                    </div>
                </div>

            </section>

            <!-- BOTTOM ROW -->
            <section class="bottom-row">

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
                        <button onclick="document.getElementById('modalProducto').style.display='block'" class="action-btn" style="border:none;cursor:pointer;">+ Producto</button>
                        <button onclick="document.getElementById('modalCliente').style.display='block'" class="action-btn" style="border:none;cursor:pointer;">+ Cliente</button>
                        <button onclick="document.getElementById('modalRepartidor').style.display='block'" class="action-btn" style="border:none;cursor:pointer;">+ Repartidor</button>
                        <button onclick="document.getElementById('modalDireccion').style.display='block'" class="action-btn" style="border:none;cursor:pointer;">+ Dirección</button>
                        <button onclick="document.getElementById('modalEntrada').style.display='block'" class="action-btn" style="border:none;cursor:pointer;">+ Entrada</button>
                        <button onclick="document.getElementById('modalPedido').style.display='block'" class="action-btn" style="border:none;cursor:pointer;">+ Pedido</button>
                        <button onclick="document.getElementById('modalMerma').style.display='block'" class="action-btn" style="border:none;cursor:pointer;">+ Merma</button>
                        <button onclick="document.getElementById('modalPpedido').style.display='block'" class="action-btn" style="border:none;cursor:pointer;">+ Carrito</button>
                        <button onclick="document.getElementById('modalEstatus').style.display='block'" class="action-btn" style="border:none;cursor:pointer;">+ Estatus</button>
                        <button onclick="document.getElementById('modalExistencias').style.display='block'" class="action-btn" style="border:none;cursor:pointer;">+ Existencias</button>
                    </div>
                </div>

            </section>

        </main>
    </div>

    <!-- MODALES -->
    <div id="modalCliente" class="w3-modal" style="padding-top:100px;z-index:9999;">
        <div class="w3-modal-content w3-animate-zoom" style="max-width:500px;max-height:90vh;overflow-y:auto;">
            <form action="<?= base_url('guarda_cliente') ?>" method="post" class="w3-container w3-padding-16">
                <label><b>Nombre</b></label>
                <input type="text" name="nom" class="w3-input w3-border w3-margin-bottom" required>
                <label><b>Apellido Paterno</b></label>
                <input type="text" name="ape_pat" class="w3-input w3-border w3-margin-bottom" required>
                <label><b>Apellido Materno</b></label>
                <input type="text" name="ape_mat" class="w3-input w3-border w3-margin-bottom" required>
                <label><b>Teléfono</b></label>
                <input type="text" name="tel" class="w3-input w3-border w3-margin-bottom" required>
                <footer class="w3-container w3-green w3-padding">
                    <button type="submit" class="w3-button w3-white w3-right">Guardar</button>
                    <button type="button" onclick="document.getElementById('modalCliente').style.display='none'" class="w3-button w3-white">Cancelar</button>
                </footer>
            </form>
        </div>
    </div>

    <div id="modalDireccion" class="w3-modal" style="padding-top:100px;z-index:9999;">
        <div class="w3-modal-content w3-animate-zoom" style="max-width:500px;max-height:90vh;overflow-y:auto;">
            <form action="<?= base_url('guarda_direccion') ?>" method="post" class="w3-container w3-padding-16">
                <label><b>Colonia</b></label>
                <input type="text" name="col" class="w3-input w3-border w3-margin-bottom" required>
                <label><b>Calle</b></label>
                <input type="text" name="calle" class="w3-input w3-border w3-margin-bottom" required>
                <label><b>Número</b></label>
                <input type="text" name="num" class="w3-input w3-border w3-margin-bottom" required>
                <label><b>Municipio</b></label>
                <input type="text" name="mun" class="w3-input w3-border w3-margin-bottom" required>
                <label><b>Estado</b></label>
                <select name="edo" class="w3-select w3-border w3-margin-bottom" required>
                    <option value="Aguascalientes">Aguascalientes</option>
                    <option value="Baja California">Baja California</option>
                    <option value="Baja California Sur">Baja California Sur</option>
                    <option value="Campeche">Campeche</option>
                    <option value="Chiapas">Chiapas</option>
                    <option value="Chihuahua">Chihuahua</option>
                    <option value="Ciudad de Mexico">Ciudad de México</option>
                    <option value="Coahuila">Coahuila</option>
                    <option value="Colima">Colima</option>
                    <option value="Durango">Durango</option>
                    <option value="Estado de Mexico">Estado de México</option>
                    <option value="Guanajuato">Guanajuato</option>
                    <option value="Guerrero">Guerrero</option>
                    <option value="Hidalgo">Hidalgo</option>
                    <option value="Jalisco">Jalisco</option>
                    <option value="Michoacán">Michoacán</option>
                    <option value="Morelos">Morelos</option>
                    <option value="Nayarit">Nayarit</option>
                    <option value="Nuevo Leon">Nuevo León</option>
                    <option value="Oaxaca">Oaxaca</option>
                    <option value="Puebla">Puebla</option>
                    <option value="Queretaro">Querétaro</option>
                    <option value="Quintana Roo">Quintana Roo</option>
                    <option value="San Luis Potosi">San Luis Potosí</option>
                    <option value="Sinaloa">Sinaloa</option>
                    <option value="Sonora">Sonora</option>
                    <option value="Tabasco">Tabasco</option>
                    <option value="Tamaulipas">Tamaulipas</option>
                    <option value="Tlaxcala">Tlaxcala</option>
                    <option value="Veracruz" selected>Veracruz</option>
                    <option value="Yucatan">Yucatán</option>
                    <option value="Zacatecas">Zacatecas</option>
                </select>
                <label><b>Cliente</b></label>
                <select name="id_cliente" class="w3-select w3-border w3-margin-bottom" required>
                    <?php foreach ($clientes as $c): ?>
                        <option value="<?= esc($c['id']) ?>"><?= esc($c['nombre'].' '.$c['ape_pat'].' '.$c['ape_mat']) ?></option>
                    <?php endforeach; ?>
                </select>
                <footer class="w3-container w3-green w3-padding">
                    <button type="submit" class="w3-button w3-white w3-right">Guardar</button>
                    <button type="button" onclick="document.getElementById('modalDireccion').style.display='none'" class="w3-button w3-white">Cancelar</button>
                </footer>
            </form>
        </div>
    </div>
    
<div id="modalEntrada" class="w3-modal" style="padding-top:100px;z-index:9999;">
    <div class="w3-modal-content w3-animate-zoom" style="max-width:500px;max-height:90vh;overflow-y:auto;">
        <form action="<?= base_url('guarda_entrada') ?>" method="post" class="w3-container w3-padding-16">

            <label><b>Fecha de entrada</b></label>
            <input type="date" name="f_ent" class="w3-input w3-border w3-margin-bottom" required>

            <label><b>Fecha de caducidad</b></label>
            <input type="date" name="f_cad" class="w3-input w3-border w3-margin-bottom">

            <label><b>Producto</b></label>
            <select name="id_producto" class="w3-select w3-border w3-margin-bottom" required>
                <?php foreach ($productos as $p): ?>
                    <option value="<?= esc($p['id']) ?>"><?= esc($p['nombre']) ?></option>
                <?php endforeach; ?>
            </select>

            <label><b>Cantidad</b></label>
            <input type="number" name="cant" class="w3-input w3-border w3-margin-bottom" required>

            <label><b>Unidad de compra</b></label>
            <select name="u_com" class="w3-select w3-border w3-margin-bottom" required>
                <option value="Caja">Caja</option>
                <option value="Arpilla">Arpilla</option>
                <option value="Bulto">Bulto</option>
                <option value="Tonelada">Tonelada</option>
            </select>

            <label><b>Unidad de venta</b></label>
            <select name="u_ven" class="w3-select w3-border w3-margin-bottom" required>
                <option value="Kilogramo">Kilogramo</option>
                <option value="Litro">Litro</option>
                <option value="Caja">Caja</option>
                <option value="Domo">Domo</option>
                <option value="Pieza">Pieza</option>
                <option value="Ramo">Ramo</option>
            </select>

            <label><b>Equivalente</b></label>
            <input type="number" name="equi" class="w3-input w3-border w3-margin-bottom">

            <label><b>Precio de compra</b></label>
            <input type="number" name="p_compra" step="0.01" class="w3-input w3-border w3-margin-bottom" required>

            <footer class="w3-container w3-green w3-padding">
                <button type="submit" class="w3-button w3-white w3-right">Guardar</button>
                <button type="button"
                        onclick="document.getElementById('modalEntrada').style.display='none'"
                        class="w3-button w3-white">Cancelar</button>
            </footer>

        </form>
    </div>
</div>

    <div id="modalPedido" class="w3-modal" style="padding-top:100px;z-index:9999;">
        <div class="w3-modal-content w3-animate-zoom" style="max-width:500px;max-height:90vh;overflow-y:auto;">
            <form action="<?= base_url('guarda_pedido') ?>" method="post" class="w3-container w3-padding-16">

                <label><b>Fecha</b></label>
                <input type="date" name="fecha" class="w3-input w3-border w3-margin-bottom" required>
                <label><b>Cliente</b></label>
                <select name="id_cliente" class="w3-select w3-border w3-margin-bottom" required>
                    <?php foreach ($clientes as $c): ?>
                        <option value="<?= esc($c['id']) ?>"><?= esc($c['nombre'].' '.$c['ape_pat'].' '.$c['ape_mat']) ?></option>
                    <?php endforeach; ?>
                </select>
                <label><b>Repartidor</b></label>
                <select name="id_repartidor" class="w3-select w3-border w3-margin-bottom" required>
                    <?php foreach ($repartidores as $r): ?>
                        <option value="<?= esc($r['id']) ?>"><?= esc($r['nombre'].' '.$r['ape_pat'].' '.$r['ape_mat']) ?></option>
                    <?php endforeach; ?>
                </select>
                <footer class="w3-container w3-green w3-padding">
                    <button type="submit" class="w3-button w3-white w3-right">Guardar</button>
                    <button type="button" onclick="document.getElementById('modalPedido').style.display='none'" class="w3-button w3-white">Cancelar</button>
                </footer>
            </form>
        </div>
    </div>

    <div id="modalProducto" class="w3-modal" style="padding-top:100px;z-index:9999;">
        <div class="w3-modal-content w3-animate-zoom" style="max-width:500px;max-height:90vh;overflow-y:auto;">
            <form action="<?= base_url('guarda_producto') ?>" method="post" enctype="multipart/form-data" class="w3-container w3-padding-16">
                <label><b>Nombre</b></label>
                <input type="text" name="nom" placeholder="Ej: Tomate Saladet" class="w3-input w3-border w3-margin-bottom" required>
                <label><b>Descripción</b></label>
                <textarea name="desc" rows="4" class="w3-input w3-border w3-margin-bottom" required></textarea>
                <label><b>Categoría</b></label>
                <select name="cat" class="w3-select w3-border w3-margin-bottom" required>
                    <option value="productos">productos</option>
                    <option value="verduras">Verdura</option>
                    <option value="yerbas">Yerba</option>
                    <option value="frutas">Frutas</option>
                </select>
                <label><b>Imagen</b></label>
                <input type="file" name="img" class="w3-input w3-border w3-margin-bottom" required>
                <footer class="w3-container w3-green w3-padding">
                    <button type="submit" class="w3-button w3-white w3-right">Guardar</button>
                    <button type="button" onclick="document.getElementById('modalProducto').style.display='none'" class="w3-button w3-white">Cancelar</button>
                </footer>
            </form>
        </div>
    </div>

    <div id="modalRepartidor" class="w3-modal" style="padding-top:100px;z-index:9999;">
        <div class="w3-modal-content w3-animate-zoom" style="max-width:500px;max-height:90vh;overflow-y:auto;">
            <form action="<?= base_url('guarda_repartidor') ?>" method="post" class="w3-container w3-padding-16">
                <label><b>Nombre</b></label>
                <input type="text" name="nom" class="w3-input w3-border w3-margin-bottom" required>
                <label><b>Apellido Paterno</b></label>
                <input type="text" name="ape_pat" class="w3-input w3-border w3-margin-bottom" required>
                <label><b>Apellido Materno</b></label>
                <input type="text" name="ape_mat" class="w3-input w3-border w3-margin-bottom" required>
                <label><b>Teléfono</b></label>
                <input type="text" name="tel" class="w3-input w3-border w3-margin-bottom" required>
                <label><b>Dirección</b></label>
                <input type="text" name="dir" class="w3-input w3-border w3-margin-bottom" required>
                <label><b>Notas</b></label>
                <textarea name="not" rows="4" class="w3-input w3-border w3-margin-bottom" required></textarea>
                <footer class="w3-container w3-green w3-padding">
                    <button type="submit" class="w3-button w3-white w3-right">Guardar</button>
                    <button type="button" onclick="document.getElementById('modalRepartidor').style.display='none'" class="w3-button w3-white">Cancelar</button>
                </footer>
            </form>
        </div>
    </div>

    <div id="modalPpedido" class="w3-modal" style="padding-top:100px;z-index:9999;">
    <div class="w3-modal-content w3-animate-zoom" style="max-width:500px;max-height:90vh;overflow-y:auto;">
        <form action="<?= base_url('guarda_p_pedido') ?>" method="post" class="w3-container w3-padding-16">

            <label><b>Pedido</b></label>
            <select name="id_pedido" class="w3-select w3-border w3-margin-bottom" required>
                <?php foreach ($pedidos as $p): ?>
                    <option value="<?= esc($p['id']) ?>"><?= esc($p['id']) ?></option>
                <?php endforeach; ?>
            </select>

            <label><b>Producto</b></label>
            <select name="id_producto" class="w3-select w3-border w3-margin-bottom" required>
                <?php foreach ($productos as $pr): ?>
                    <option value="<?= esc($pr['id']) ?>"><?= esc($pr['nombre']) ?></option>
                <?php endforeach; ?>
            </select>

            <label><b>Unidad de venta</b></label>
            <select name="u_venta" class="w3-select w3-border w3-margin-bottom" required>
                <option value="Kilogramo">Kilogramo</option>
                <option value="Domo">Domo</option>
                <option value="Ramos">Ramo</option>
                <option value="Caja">Caja</option>
            </select>

            <label><b>Cantidad</b></label>
            <input type="number" name="cant" class="w3-input w3-border w3-margin-bottom" required>

            <label><b>Precio de venta</b></label>
            <input type="number" name="p_venta" step="0.01" class="w3-input w3-border w3-margin-bottom" required>

            <label><b>Total</b></label>
            <input type="number" name="tot" step="0.01" class="w3-input w3-border w3-margin-bottom">

            <footer class="w3-container w3-green w3-padding">
                <button type="submit" class="w3-button w3-white w3-right">Guardar</button>
                <button type="button"
                        onclick="document.getElementById('modalPpedido').style.display='none'"
                        class="w3-button w3-white">Cancelar</button>
            </footer>

        </form>
    </div>
</div>

<div id="modalEstatus" class="w3-modal" style="padding-top:100px;z-index:9999;">
    <div class="w3-modal-content w3-animate-zoom" style="max-width:500px;max-height:90vh;overflow-y:auto;">
        <form action="<?= base_url('guarda_estatus') ?>" method="post" class="w3-container w3-padding-16">

            <label><b>Pedido</b></label>
            <select name="id_pedido" class="w3-select w3-border w3-margin-bottom" required>
                <?php foreach ($pedidos as $p): ?>
                    <option value="<?= esc($p['id']) ?>"><?= esc($p['id']) ?></option>
                <?php endforeach; ?>
            </select>

            <label><b>Estado</b></label>
            <select name="edo" class="w3-select w3-border w3-margin-bottom" required>
                <option value="pedido_realizado">Pedido realizado</option>
                <option value="pedido_confirmado">Pedido confirmado</option>
                <option value="pedido_en_transito">Pedido en tránsito</option>
                <option value="pedido_entregado">Pedido entregado</option>
                <option value="pedido_a_credito">Pedido a crédito</option>
                <option value="pedido_pagado">Pedido pagado</option>
                <option value="pedido_cancelado">Pedido cancelado</option>
            </select>

            <label><b>Fecha</b></label>
            <input type="timestamp" name="fecha" class="w3-input w3-border w3-margin-bottom" value="<?= date('Y-m-d H:i:s') ?>">

            <footer class="w3-container w3-green w3-padding">
                <button type="submit" class="w3-button w3-white w3-right">Guardar</button>
                <button type="button"
                        onclick="document.getElementById('modalEstatus').style.display='none'"
                        class="w3-button w3-white">Cancelar</button>
            </footer>

        </form>
    </div>
</div>

<div id="modalExistencias" class="w3-modal" style="padding-top:100px;z-index:9999;">
    <div class="w3-modal-content w3-animate-zoom" style="max-width:500px;max-height:90vh;overflow-y:auto;">
        <form action="<?= base_url('guarda_existencia') ?>" method="post" class="w3-container w3-padding-16">

            <label><b>Producto</b></label>
            <select name="id_producto" class="w3-select w3-border w3-margin-bottom" required>
                <?php foreach ($productos as $p): ?>
                    <option value="<?= esc($p['id']) ?>"><?= esc($p['nombre']) ?></option>
                <?php endforeach; ?>
            </select>

            <label><b>Existencias totales</b></label>
            <input type="number" name="e_total" class="w3-input w3-border w3-margin-bottom">

            <label><b>Existencias bloqueadas</b></label>
            <input type="number" name="e_bloqueado" class="w3-input w3-border w3-margin-bottom">

            <label><b>Existencias para venta</b></label>
            <input type="number" name="e_venta" class="w3-input w3-border w3-margin-bottom">

            <label><b>Fecha</b></label>
            <input type="timestamp" name="fecha" class="w3-input w3-border w3-margin-bottom" value="<?= date('Y-m-d H:i:s') ?>">

            <footer class="w3-container w3-green w3-padding">
                <button type="submit" class="w3-button w3-white w3-right">Guardar</button>
                <button type="button"
                        onclick="document.getElementById('modalExistencias').style.display='none'"
                        class="w3-button w3-white">Cancelar</button>
            </footer>

        </form>
    </div>
</div>

<div id="modalMerma" class="w3-modal" style="padding-top:100px;z-index:9999;">
    <div class="w3-modal-content w3-animate-zoom" style="max-width:500px;max-height:90vh;overflow-y:auto;">
        <form action="<?= base_url('guarda_merma') ?>" method="post" class="w3-container w3-padding-16">

            <label><b>Entrada</b></label>
            <select name="id_entrada" id="id_entrada_modal" class="w3-select w3-border w3-margin-bottom" required>
                <option value="">-- Selecciona una entrada --</option>
                <?php foreach ($entradas as $entrada): ?>
                    <option value="<?= $entrada['id'] ?>">
                        #<?= $entrada['id'] ?> — <?= esc($entrada['nombre_producto']) ?> (<?= $entrada['fecha'] ?>)
                    </option>
                <?php endforeach; ?>
            </select>

            <label><b>Unidad de venta</b></label>
            <input type="text" id="u_venta_modal" name="u_venta" class="w3-input w3-border w3-margin-bottom" readonly placeholder="Se llena automáticamente">

            <label><b>Cantidad</b></label>
            <input type="number" name="cant" class="w3-input w3-border w3-margin-bottom" required>

            <label><b>Fecha</b></label>
            <input type="date" name="fecha" class="w3-input w3-border w3-margin-bottom" value="<?= date('Y-m-d') ?>" required>

            <label><b>Notas</b></label>
            <textarea name="notas" rows="3" class="w3-input w3-border w3-margin-bottom"></textarea>

            <!-- Datos para JS -->
            <script id="entradas-data" type="application/json">
                <?= json_encode($entradas) ?>
            </script>

            <footer class="w3-container w3-green w3-padding">
                <button type="submit" class="w3-button w3-white w3-right">Guardar</button>
                <button type="button"
                        onclick="document.getElementById('modalMerma').style.display='none'"
                        class="w3-button w3-white">Cancelar</button>
            </footer>

        </form>
    </div>
</div>
    <!-- Datos para Chart.js -->
    <script id="chart-data" type="application/json">
    <?= json_encode([
        'pedidosPorMes' => $pedidosPorMes,
        'ventasPorMes'  => $ventasPorMes,
        'topProductos'  => $topProductos,
        'perdidasMerma' => $perdidasMerma,
    ]) ?>
    </script>

    <!-- dashboard.js DESPUÉS del chart-data -->
    <script src="<?= base_url('js/dashboard.js') ?>"></script>

    <script>
    window.onclick = function(event) {
        const ids = ['modalCliente','modalDireccion','modalEntrada','modalPedido','modalProducto','modalRepartidor','modalPpedido','modalExistencias','modalEstatus','modalMerma'];
        ids.forEach(id => {
            const el = document.getElementById(id);
            if (el && event.target === el) el.style.display = 'none';
        });
    };
    </script>
</body>
</html>