<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FRUVER — Panel de Control</title>
    <link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css"
          crossorigin="anonymous" referrerpolicy="no-referrer" />
    <link rel="stylesheet" href="<?= base_url('estilos/Header.css') ?>">
    <link rel="stylesheet" href="<?= base_url('estilos/dashboard.css') ?>">
    <link rel="stylesheet" href="https://www.w3schools.com/w3css/5/w3.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>

    <?php include 'Header.php'; ?>
    <div class="flash-container">
        <?php if (session()->getFlashdata('error')): ?>
            <div class="w3-panel w3-red w3-animate-opacity">
                <p><?= session()->getFlashdata('error') ?></p>
            </div>
        <?php endif; ?>
        <?php if (session()->getFlashdata('mensaje')): ?>
            <div class="w3-panel w3-green w3-animate-opacity">
                <p><?= session()->getFlashdata('mensaje') ?></p>
            </div>
        <?php endif; ?>
    </div>

    <div class="dashboard-wrapper">

        <aside class="sidebar">
            <nav class="sidebar-nav">
                <p class="nav-label">Módulos</p>
                <a href="<?= base_url('lista_producto') ?>"   class="nav-item">Productos</a>
                <a href="<?= base_url('lista_cliente') ?>"    class="nav-item">Clientes</a>
                <a href="<?= base_url('lista_repartidor') ?>" class="nav-item">Repartidores</a>
                <a href="<?= base_url('lista_direccion') ?>"  class="nav-item">Direcciones</a>
                <a href="<?= base_url('lista_entrada') ?>"    class="nav-item">Entradas</a>
                <a href="<?= base_url('lista_merma') ?>"      class="nav-item">Mermas</a>
                <a href="<?= base_url('lista_p_pedido') ?>"   class="nav-item">Productos por pedido</a>
                <a href="<?= base_url('lista_existencia') ?>" class="nav-item">Existencias</a>
            </nav>
        </aside>

        <main class="dashboard-main">

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

            <section class="charts-row">
                <div class="chart-card">
                    <div class="chart-header">
                        <h2 class="chart-title">Pedidos por semana</h2>
                        <span class="chart-badge">Últimas 8 semanas</span>
                    </div>
                    <div class="chart-area"><canvas id="pedidosChart"></canvas></div>
                </div>
                <div class="chart-card">
                    <div class="chart-header">
                        <h2 class="chart-title">Total en ventas</h2>
                        <span class="chart-badge">Últimas 8 semanas</span>
                    </div>
                    <div class="chart-area"><canvas id="ventasChart"></canvas></div>
                </div>
            </section>

            <section class="charts-row">
                <div class="chart-card">
                    <div class="chart-header">
                        <h2 class="chart-title">Top 5 productos más vendidos</h2>
                        <span class="chart-badge">Histórico</span>
                    </div>
                    <div class="chart-area"><canvas id="topProductosChart"></canvas></div>
                </div>
                <div class="chart-card">
                    <div class="chart-header">
                        <h2 class="chart-title">Pérdidas por merma</h2>
                        <span class="chart-badge">Últimas 8 semanas</span>
                    </div>
                    <div class="chart-area"><canvas id="mermaChart"></canvas></div>
                </div>
            </section>

            <section class="bottom-row">
                <div class="table-card">
                    <div class="chart-header">
                        <h2 class="chart-title">Productos con bajas existencias</h2>
                        <span class="chart-badge">Top 5</span>
                    </div>
                    <?php
                    // Ordenar productos por existencia (de menor a mayor)
                    $bajos_stock = [];
                    foreach ($productos as $p) {
                        $stock = $stockPorProducto[$p['id']] ?? 0;
                        $bajos_stock[] = ['nombre' => $p['nombre'], 'stock' => $stock];
                    }
                    usort($bajos_stock, fn($a, $b) => $a['stock'] <=> $b['stock']);
                    $top5_bajos = array_slice($bajos_stock, 0, 5);
                    ?>
                    <table class="data-table">
                        <thead>
                            <tr><th>#</th><th>Producto</th><th>Stock</th><th>Estado</th></tr>
                        </thead>
                        <tbody>
                            <?php foreach ($top5_bajos as $i => $p): ?>
                                <tr>
                                    <td><?= $i + 1 ?></td>
                                    <td><?= esc($p['nombre']) ?></td>
                                    <td><?= $p['stock'] ?></td>
                                    <td>
                                        <?php if ($p['stock'] <= 5): ?>
                                            <span class="badge badge--red">Bajo</span>
                                        <?php elseif ($p['stock'] <= 15): ?>
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

                <div class="actions-card">
                    <h2 class="chart-title" style="margin-bottom:1rem;">Acciones rápidas</h2>
                    <div class="actions-grid">
                        <button onclick="document.getElementById('modalProducto').style.display='block'"    class="action-btn" style="border:none;cursor:pointer;">+ Producto</button>
                        <button onclick="document.getElementById('modalCliente').style.display='block'"     class="action-btn" style="border:none;cursor:pointer;">+ Cliente</button>
                        <button onclick="document.getElementById('modalRepartidor').style.display='block'"  class="action-btn" style="border:none;cursor:pointer;">+ Repartidor</button>
                        <button onclick="document.getElementById('modalDireccion').style.display='block'"   class="action-btn" style="border:none;cursor:pointer;">+ Dirección</button>
                        <button onclick="document.getElementById('modalEntrada').style.display='block'"     class="action-btn" style="border:none;cursor:pointer;">+ Entrada</button>
                        <button onclick="document.getElementById('modalMerma').style.display='block'"       class="action-btn" style="border:none;cursor:pointer;">+ Merma</button>
                        <button onclick="document.getElementById('modalPpedido').style.display='block'"     class="action-btn" style="border:none;cursor:pointer;">+ Carrito</button>
                        <button onclick="document.getElementById('modalExistencias').style.display='block'" class="action-btn" style="border:none;cursor:pointer;">+ Existencias</button>
                    </div>
                </div>
            </section>

        </main>
    </div>

    <div id="modalCliente" class="w3-modal" style="padding-top:100px;z-index:9999;">
        <div class="w3-modal-content w3-animate-zoom" style="max-width:500px;max-height:90vh;overflow-y:auto;">
            <form action="<?= base_url('guarda_cliente') ?>" method="post" class="w3-container w3-padding-16">
                <input type="hidden" name="origen" value="main_page">
                <label><b>Nombre*</b></label>
                <input type="text" placeholder="Ej: Mario" name="nom" class="w3-input w3-border w3-margin-bottom" required>
                <label><b>Apellido Paterno*</b></label>
                <input type="text" placeholder="Ej: Pérez" name="ape_pat" class="w3-input w3-border w3-margin-bottom" required>
                <label><b>Apellido Materno*</b></label>
                <input type="text" placeholder="Ej: González" name="ape_mat" class="w3-input w3-border w3-margin-bottom" required>
                <label><b>Teléfono*</b></label>
                <input type="text" placeholder="Formato:1234567890" name="tel" class="w3-input w3-border w3-margin-bottom" required>
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
                <input type="hidden" name="origen" value="main_page">
                <label><b>Colonia*</b></label>
                <input type="text" placeholder="Ej: Centro" name="col" class="w3-input w3-border w3-margin-bottom" required>
                <label><b>Calle*</b></label>
                <input type="text" placeholder="Ej: Benito Juárez" name="calle" class="w3-input w3-border w3-margin-bottom" required>
                <label><b>Número*</b></label>
                <input type="text" placeholder="Ej: 59" name="num" class="w3-input w3-border w3-margin-bottom" required>
                <label><b>Municipio*</b></label>
                <input type="text" placeholder="Ej: Rafael Lara Grajales" name="mun" class="w3-input w3-border w3-margin-bottom" required>
                <label><b>Estado*</b></label>
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
                <label><b>Cliente*</b></label>
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
                <input type="hidden" name="origen" value="main_page">
                <label><b>Fecha de entrada*</b></label>
                <input type="date" name="f_ent" class="w3-input w3-border w3-margin-bottom" required>
                <label><b>Fecha de caducidad</b></label>
                <input type="date" name="f_cad" class="w3-input w3-border w3-margin-bottom">
                <label><b>Categoría</b></label>
                <select id="filtroCategoriaEntrada" class="w3-select w3-border w3-margin-bottom">
                    <option value="">— Todas —</option>
                    <option value="frutas">Frutas</option>
                    <option value="verduras">Verduras</option>
                    <option value="hierbas">Hierbas</option>
                </select>
                <label><b>Producto*</b></label>
                <select id="selectProductoEntrada" name="id_producto" class="w3-select w3-border w3-margin-bottom" required>
                    <?php foreach ($productos as $p): ?>
                        <option value="<?= esc($p['id']) ?>"
                                data-categoria="<?= esc($p['categoria']) ?>">
                            <?= esc($p['nombre']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <label><b>Cantidad*</b></label>
                <input type="number" placeholder="Ej: 50" name="cant" class="w3-input w3-border w3-margin-bottom" min="1" required>
                <label><b>Unidad de compra*</b></label>
                <select name="u_com" class="w3-select w3-border w3-margin-bottom" required>
                    <option value="Caja">Caja</option>
                    <option value="Arpilla">Arpilla</option>
                    <option value="Bulto">Bulto</option>
                    <option value="Tonelada">Tonelada</option>
                    <option value="Mazo">Mazo</option>
                </select>
                <label><b>Unidad de venta*</b></label>
                <select name="u_ven" class="w3-select w3-border w3-margin-bottom" required>
                    <option value="Kilogramo">Kilogramo</option>
                    <option value="Litro">Litro</option>
                    <option value="Caja">Caja</option>
                    <option value="Pieza">Pieza</option>
                    <option value="Domo">Domo</option>
                    <option value="Ramo">Ramo</option>
                </select>
                <label><b>Equivalente</b></label>
                <input type="number" placeholder="Ej: 20" name="equi" class="w3-input w3-border w3-margin-bottom">
                <label><b>Precio de compra (Unitario)*</b></label>
                <input type="number" placeholder="Ej: 45" name="p_compra" step="0.01" class="w3-input w3-border w3-margin-bottom" required>
                <label><b>Precio de venta (Unitario)*</b></label>
                <input type="number" placeholder="Ej: 50" name="p_venta" step="0.01" class="w3-input w3-border w3-margin-bottom" required>
                <footer class="w3-container w3-green w3-padding">
                    <button type="submit" class="w3-button w3-white w3-right">Guardar</button>
                    <button type="button" onclick="document.getElementById('modalEntrada').style.display='none'" class="w3-button w3-white">Cancelar</button>
                </footer>
            </form>
        </div>
    </div>

    <div id="modalProducto" class="w3-modal" style="padding-top:100px;z-index:9999;">
        <div class="w3-modal-content w3-animate-zoom" style="max-width:500px;max-height:90vh;overflow-y:auto;">
            <form action="<?= base_url('guarda_producto') ?>" method="post" enctype="multipart/form-data" class="w3-container w3-padding-16">
                <input type="hidden" name="origen" value="main_page">
                <label><b>Nombre*</b></label>
                <input type="text" name="nom" placeholder="Ej: Tomate Saladet" class="w3-input w3-border w3-margin-bottom" required>
                <label><b>Descripción*</b></label>
                <textarea name="desc" placeholder="Ej: Fruta roja de sabor dulce" rows="4" class="w3-input w3-border w3-margin-bottom" required></textarea>
                <label><b>Categoría*</b></label>
                <select name="cat" class="w3-select w3-border w3-margin-bottom" required>
                    <option value="frutas">Frutas</option>
                    <option value="verduras">Verduras</option>
                    <option value="hierbas">Hierbas</option>
                </select>
                <label><b>Imagen*</b></label>
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
                <input type="hidden" name="origen" value="main_page">
                <label><b>Nombre*</b></label>
                <input type="text" placeholder="Ej: Juan" name="nom" class="w3-input w3-border w3-margin-bottom" required>
                <label><b>Apellido Paterno*</b></label>
                <input type="text" placeholder="Ej: Pérez" name="ape_pat" class="w3-input w3-border w3-margin-bottom" required>
                <label><b>Apellido Materno*</b></label>
                <input type="text" placeholder="Ej: Calamáro" name="ape_mat" class="w3-input w3-border w3-margin-bottom" required>
                <label><b>Teléfono*</b></label>
                <input type="text" placeholder="Formato:1234567890" name="tel" class="w3-input w3-border w3-margin-bottom" required>
                <label><b>Dirección*</b></label>
                <input type="text" placeholder="Formato: Estado, Ciudad, Colonia, Calle, Número" name="dir" class="w3-input w3-border w3-margin-bottom" required>
                <label><b>Notas</b></label>
                <textarea name="not" placeholder="Ej: Es un repartidor constante y cumplido" rows="4" class="w3-input w3-border w3-margin-bottom"></textarea>
                <footer class="w3-container w3-green w3-padding">
                    <button type="submit" class="w3-button w3-white w3-right">Guardar</button>
                    <button type="button" onclick="document.getElementById('modalRepartidor').style.display='none'" class="w3-button w3-white">Cancelar</button>
                </footer>
            </form>
        </div>
    </div>

    <div id="modalPpedido" class="w3-modal" style="padding-top:100px;z-index:9999;">
        <div class="w3-modal-content w3-animate-zoom" style="max-width:580px;max-height:90vh;overflow-y:auto;">
            <div class="w3-container w3-padding-16">

                <div class="w3-bar w3-margin-bottom" style="border-bottom:1px solid #ddd;">
                    <div id="tab1" class="w3-bar-item w3-center w3-padding-small"
                         style="width:33%;border-bottom:3px solid green;font-weight:bold;cursor:default">1. Pedido</div>
                    <div id="tab2" class="w3-bar-item w3-center w3-padding-small"
                         style="width:33%;border-bottom:3px solid #ccc;cursor:default">2. Productos</div>
                    <div id="tab3" class="w3-bar-item w3-center w3-padding-small"
                         style="width:33%;border-bottom:3px solid #ccc;cursor:default">3. Estatus</div>
                </div>

                <div id="paso1">
                    <label><b>Fecha*</b></label>
                    <input type="date" id="ped_fecha" class="w3-input w3-border w3-margin-bottom">
                    <label><b>Cliente*</b></label>
                    <select id="ped_id_cliente" class="w3-select w3-border w3-margin-bottom">
                        <?php foreach ($clientes as $c): ?>
                            <option value="<?= esc($c['id']) ?>">
                                <?= esc($c['nombre'].' '.$c['ape_pat'].' '.$c['ape_mat']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <label><b>Repartidor*</b></label>
                    <select id="ped_id_repartidor" class="w3-select w3-border w3-margin-bottom">
                        <?php foreach ($repartidores as $r): ?>
                            <option value="<?= esc($r['id']) ?>">
                                <?= esc($r['nombre'].' '.$r['ape_pat'].' '.$r['ape_mat']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div id="paso2" style="display:none;">
 
                    <label><b>Categoría</b></label>
                    <select id="filtroCategoriaCrear" class="w3-select w3-border w3-margin-bottom">
                        <option value="">— Todas —</option>
                        <option value="frutas">Frutas</option>
                        <option value="verduras">Verduras</option>
                        <option value="hierbas">Hierbas</option>
                    </select>
 
                    <label><b>Producto*</b></label>
                    <p class="search-hint">
                        <i class="fa-solid fa-circle-info"></i>
                        Escribe al menos 3 caracteres para buscar
                    </p>
 
                    <div class="prod-search-wrap">
                        <i class="fa-solid fa-magnifying-glass search-icon"></i>
                        <input type="text"
                               id="cp_buscar"
                               class="prod-search-input"
                               placeholder="Buscar producto..."
                               autocomplete="off">
                        <div id="cp_resultados" class="prod-resultados"></div>
                    </div>
 
                    <div id="cp_badge" class="prod-badge" style="display:none;">
                        <span class="badge-nombre" id="cp_badge_nombre"></span>
                        <span class="badge-stock"  id="cp_badge_stock"></span>
                        <button type="button" class="badge-limpiar"
                                onclick="cpLimpiarSeleccion()" title="Cambiar producto">✕</button>
                    </div>
 
                    <input type="hidden" id="cp_id_producto">
 
                    <label><b>Unidad de venta*</b></label>
                    <select id="cp_u_venta" class="w3-select w3-border w3-margin-bottom">
                        <option value="Kilogramo">Kilogramo</option>
                        <option value="Domo">Domo</option>
                        <option value="Ramos">Ramo</option>
                        <option value="Caja">Caja</option>
                    </select>
                    <label><b>Cantidad*</b></label>
                    <input type="number" id="cp_cant" placeholder="Ej: 5"
                           class="w3-input w3-border w3-margin-bottom">
                    <label><b>Precio de venta (unitario)*</b></label>
                    <input type="number" id="cp_p_venta" placeholder="Ej: 45.00" step="0.01"
                           class="w3-input w3-border w3-margin-bottom">
                    <button type="button" onclick="agregarAlCarrito()"
                            class="w3-button w3-blue w3-margin-bottom">
                        + Agregar producto
                    </button>
 
                    <div id="carritoContainer" style="display:none;">
                        <hr><b>Carrito:</b>
                        <table class="w3-table w3-bordered w3-small w3-margin-top">
                            <thead class="w3-green">
                                <tr>
                                    <th>Producto</th><th>Unidad</th><th>Cant</th>
                                    <th>Precio</th><th>Total</th><th></th>
                                </tr>
                            </thead>
                            <tbody id="carritoBody"></tbody>
                        </table>
                    </div>
                </div>

                <div id="paso3" style="display:none;">
                    <label><b>Estado inicial*</b></label>
                    <select id="est_estado" class="w3-select w3-border w3-margin-bottom">
                        <option value="pedido_realizado">Pedido realizado</option>
                        <option value="pedido_confirmado">Pedido confirmado</option>
                        <option value="pedido_en_transito">Pedido en tránsito</option>
                        <option value="pedido_entregado">Pedido entregado</option>
                        <option value="pedido_a_credito">Pedido a crédito</option>
                        <option value="pedido_pagado">Pedido pagado</option>
                        <option value="pedido_cancelado">Pedido cancelado</option>
                    </select>
                    <label><b>Fecha y hora*</b></label>
                    <input type="datetime-local" id="est_fecha" class="w3-input w3-border w3-margin-bottom">
                </div>

                <form id="formCarrito" action="<?= base_url('guarda_pedido_completo') ?>" method="post">
                    <?= csrf_field() ?>
                    <input type="hidden" name="origen"        value="main_page">
                    <input type="hidden" name="fecha"         id="fn_fecha">
                    <input type="hidden" name="id_cliente"    id="fn_id_cliente">
                    <input type="hidden" name="id_repartidor" id="fn_id_repartidor">
                    <input type="hidden" name="items"         id="inputItems">
                    <input type="hidden" name="estado"        id="fn_estado">
                    <input type="hidden" name="fecha_estatus" id="fn_fecha_estatus">
                </form>

                <footer class="w3-container w3-green w3-padding w3-margin-top">
                    <button type="button" id="btnGuardar" onclick="enviarCarrito()"
                            class="w3-button w3-white w3-right" style="display:none;">Guardar todo</button>
                    <button type="button" id="btnSiguiente" onclick="siguientePaso()"
                            class="w3-button w3-white w3-right">Siguiente →</button>
                    <button type="button" id="btnAtras" onclick="anteriorPaso()"
                            class="w3-button w3-white" style="display:none;">← Atrás</button>
                    <button type="button" onclick="cerrarModal()"
                            class="w3-button w3-white">Cancelar</button>
                </footer>

            </div>
        </div>
    </div>

    <div id="modalExistencias" class="w3-modal" style="padding-top:100px;z-index:9999;">
        <div class="w3-modal-content w3-animate-zoom" style="max-width:500px;max-height:90vh;overflow-y:auto;">
            <form action="<?= base_url('guarda_existencia') ?>" method="post" class="w3-container w3-padding-16">
                <input type="hidden" name="origen" value="main_page">
                <label><b>Producto*</b></label>
                <select name="id_producto" class="w3-select w3-border w3-margin-bottom" required>
                    <?php foreach ($productos as $p): ?>
                        <option value="<?= esc($p['id']) ?>"><?= esc($p['nombre']) ?></option>
                    <?php endforeach; ?>
                </select>
                <label><b>Existencias totales*</b></label>
                <input type="number" placeholder="Ej: 30" name="e_total" class="w3-input w3-border w3-margin-bottom" min="0">
                <label><b>Existencias bloqueadas*</b></label>
                <input type="number" placeholder="Ej: 20" name="e_bloqueado" class="w3-input w3-border w3-margin-bottom" min="0">
                <label><b>Existencias para venta*</b></label>
                <input type="number" placeholder="Ej: 10" name="e_venta" class="w3-input w3-border w3-margin-bottom" min="0">
                <label><b>Fecha*</b></label>
                <input type="datetime-local" name="fecha" class="w3-input w3-border w3-margin-bottom"
                       value="<?= date('Y-m-d\TH:i') ?>">
                <footer class="w3-container w3-green w3-padding">
                    <button type="submit" class="w3-button w3-white w3-right">Guardar</button>
                    <button type="button" onclick="document.getElementById('modalExistencias').style.display='none'" class="w3-button w3-white">Cancelar</button>
                </footer>
            </form>
        </div>
    </div>

    <div id="modalMerma" class="w3-modal" style="padding-top:100px;z-index:9999;">
        <div class="w3-modal-content w3-animate-zoom" style="max-width:500px;max-height:90vh;overflow-y:auto;">
            <form action="<?= base_url('guarda_merma') ?>" method="post" class="w3-container w3-padding-16">
                <input type="hidden" name="origen" value="main_page">
                <label><b>Entrada*</b></label>
                <select name="id_entrada" id="id_entrada_modal" class="w3-select w3-border w3-margin-bottom" required>
                    <option value="">-- Selecciona una entrada --</option>
                    <?php foreach ($entradas as $entrada): ?>
                        <option value="<?= $entrada['id'] ?>">
                            #<?= $entrada['id'] ?> — <?= esc($entrada['nombre_producto']) ?> (<?= $entrada['fecha'] ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
                <label><b>Unidad de venta*</b></label>
                <input type="text" id="u_venta_modal" name="u_venta" class="w3-input w3-border w3-margin-bottom"
                       readonly placeholder="Se llena automáticamente">
                <label><b>Cantidad*</b></label>
                <input type="number" placeholder="Ej: 45" name="cant" class="w3-input w3-border w3-margin-bottom" min="1" required>
                <label><b>Fecha*</b></label>
                <input type="date" name="fecha" class="w3-input w3-border w3-margin-bottom" value="<?= date('Y-m-d') ?>" required>
                <label><b>Notas</b></label>
                <textarea name="notas" placeholder="Ej: Caducó durante el viaje" rows="3" class="w3-input w3-border w3-margin-bottom"></textarea>
                <footer class="w3-container w3-green w3-padding">
                    <button type="submit" class="w3-button w3-white w3-right">Guardar</button>
                    <button type="button" onclick="document.getElementById('modalMerma').style.display='none'" class="w3-button w3-white">Cancelar</button>
                </footer>
            </form>
        </div>
    </div>

    <?php include 'Footer.php'; ?>

    <script>
    const stockPorProducto = <?= json_encode($stockPorProducto) ?>;
 
    const nombreProducto = {
        <?php foreach ($productos as $pr): ?>
            <?= $pr['id'] ?>: "<?= esc($pr['nombre']) ?>",
        <?php endforeach; ?>
    };
 
    // Catálogo con unidad y precio sugeridos por producto
    const catalogoProductos = <?= json_encode(array_map(fn($p) => [
        'id'        => (int)$p['id'],
        'nombre'    => $p['nombre'],
        'categoria' => $p['categoria'] ?? '',
        'u_venta'   => $uVentaSugeridaPorProducto[$p['id']] ?? '',
        'precio'    => $precioSugeridoPorProducto[$p['id']] ?? '',
    ], $productos)) ?>;
    </script>
 
    <script>
 
    // ── Filtro para modal entrada (se conserva tal cual) ──────────
    const opcionesProductoEntrada = [];
    document.querySelectorAll('#selectProductoEntrada option').forEach(op => {
        opcionesProductoEntrada.push(op.cloneNode(true));
    });
 
    function filtrarProductos(selectId, categoria, pool) {
        const select = document.getElementById(selectId);
        select.innerHTML = '';
        const filtradas = categoria === ''
            ? pool
            : pool.filter(op => op.dataset.categoria === categoria);
        filtradas.forEach(op => select.appendChild(op.cloneNode(true)));
        if (select.options.length === 0) {
            const vacia = document.createElement('option');
            vacia.text = '— Sin resultados —';
            vacia.disabled = true;
            select.appendChild(vacia);
        }
    }
 
    const filtroCategoriaEntrada = document.getElementById('filtroCategoriaEntrada');
    if (filtroCategoriaEntrada) {
        filtroCategoriaEntrada.addEventListener('change', function () {
            filtrarProductos('selectProductoEntrada', this.value, opcionesProductoEntrada);
        });
    }
 
    // ── Unidad de venta automática en modal merma ─────────────────
    document.getElementById('id_entrada_modal').addEventListener('change', function () {
        const entradas = <?= json_encode(array_column($entradas, 'u_venta', 'id')) ?>;
        document.getElementById('u_venta_modal').value = entradas[this.value] ?? '';
    });
 
 
    // ═══════════════════════════════════════════════════════════
    //  UTILIDADES
    // ═══════════════════════════════════════════════════════════
 
    /** Debounce genérico */
    function debounce(fn, ms) {
        let timer;
        return function (...args) {
            clearTimeout(timer);
            timer = setTimeout(() => fn.apply(this, args), ms);
        };
    }
 
    /**
     * Intenta seleccionar en un <select> la opción más parecida
     * a `sugerida`. Si no hay coincidencia, deja la selección actual.
     */
    function matchearUnidad(selectEl, sugerida) {
        if (!sugerida) return;
        const s = sugerida.toLowerCase();
        for (const opt of selectEl.options) {
            const v = opt.value.toLowerCase();
            if (v === s || v.startsWith(s) || s.startsWith(v)) {
                opt.selected = true;
                return;
            }
        }
    }
 
 
    // ═══════════════════════════════════════════════════════════
    //  BUSCADOR GENÉRICO DE PRODUCTOS
    //
    //   prefijo     → 'cp_'
    //   onSeleccion → callback(id, nombre, stock, producto)
    //                 Auto-rellena unidad y precio al seleccionar.
    // ═══════════════════════════════════════════════════════════
    function crearBuscadorProducto(prefijo, onSeleccion) {
 
        const inputBuscar   = document.getElementById(prefijo + 'buscar');
        const divResultados = document.getElementById(prefijo + 'resultados');
        const divBadge      = document.getElementById(prefijo + 'badge');
        const spanNombre    = document.getElementById(prefijo + 'badge_nombre');
        const spanStock     = document.getElementById(prefijo + 'badge_stock');
        const inputHidden   = document.getElementById(prefijo + 'id_producto');
 
        // El filtro de categoría está en el mismo modal
        const selectCat = document.getElementById('filtroCategoriaCrear');
 
        function buscar(texto) {
            const query     = texto.trim().toLowerCase();
            const categoria = selectCat ? selectCat.value : '';
 
            if (query.length < 3) {
                divResultados.style.display = 'none';
                divResultados.innerHTML = '';
                return;
            }
 
            let candidatos = catalogoProductos.filter(p => {
                const coincideNombre    = p.nombre.toLowerCase().includes(query);
                const coincideCategoria = !categoria || p.categoria === categoria;
                return coincideNombre && coincideCategoria;
            });
 
            if (candidatos.length === 0) {
                divResultados.innerHTML =
                    '<div class="sin-resultados">Sin resultados para "' + texto + '"</div>';
                divResultados.style.display = 'block';
                return;
            }
 
            // Ordenar: primero con stock
            candidatos.sort((a, b) =>
                (stockPorProducto[b.id] ?? 0) - (stockPorProducto[a.id] ?? 0)
            );
 
            divResultados.innerHTML = candidatos.map(p => {
                const stock     = stockPorProducto[p.id] ?? 0;
                const pillClass = stock > 0 ? 'con-stock' : 'sin-stock';
                const pillText  = stock > 0 ? 'Stock: ' + stock : 'Sin stock';
                return `<div class="prod-item"
                             data-id="${p.id}"
                             data-nombre="${p.nombre.replace(/"/g,'&quot;')}"
                             data-stock="${stock}">
                          <span>${p.nombre}</span>
                          <span class="stock-pill ${pillClass}">${pillText}</span>
                        </div>`;
            }).join('');
 
            divResultados.querySelectorAll('.prod-item').forEach(el => {
                el.addEventListener('click', () => {
                    const id       = el.dataset.id;
                    const nombre   = el.dataset.nombre;
                    const stock    = parseInt(el.dataset.stock, 10);
                    // Objeto completo del catálogo con u_venta y precio
                    const producto = catalogoProductos.find(p => p.id == id) ?? {};
 
                    seleccionar(id, nombre, stock);
                    if (onSeleccion) onSeleccion(id, nombre, stock, producto);
                });
            });
 
            divResultados.style.display = 'block';
        }
 
        function seleccionar(id, nombre, stock) {
            inputHidden.value      = id;
            spanNombre.textContent = nombre;
 
            const sinStock = stock <= 0;
            spanStock.textContent  = sinStock
                ? '⚠ Sin stock disponible'
                : '✓ Stock disponible: ' + stock;
            divBadge.className     = 'prod-badge' + (sinStock ? ' sin-stock' : '');
            divBadge.style.display = 'flex';
 
            inputBuscar.value           = '';
            divResultados.style.display = 'none';
            divResultados.innerHTML     = '';
            inputBuscar.style.display   = 'none';
        }
 
        // Función global de limpieza: cpLimpiarSeleccion()
        window[prefijo.replace('_','') + 'LimpiarSeleccion'] = function () {
            inputHidden.value           = '';
            divBadge.style.display      = 'none';
            inputBuscar.style.display   = '';
            inputBuscar.value           = '';
            inputBuscar.focus();
        };
 
        inputBuscar.addEventListener('input', debounce(function () {
            buscar(this.value);
        }, 600));
 
        document.addEventListener('click', function (e) {
            if (!inputBuscar.contains(e.target) && !divResultados.contains(e.target)) {
                divResultados.style.display = 'none';
            }
        });
 
        if (selectCat) {
            selectCat.addEventListener('change', () => {
                if (inputBuscar.style.display !== 'none' && inputBuscar.value.length >= 3) {
                    buscar(inputBuscar.value);
                }
            });
        }
    }
 
    // ── Inicializar buscador con callback de auto-relleno ─────────
    crearBuscadorProducto('cp_', function(id, nombre, stock, producto) {
        // Auto-rellenar unidad de venta (editable)
        matchearUnidad(document.getElementById('cp_u_venta'), producto.u_venta);
 
        // Auto-rellenar precio sugerido (editable)
        if (producto.precio) {
            document.getElementById('cp_p_venta').value =
                parseFloat(producto.precio).toFixed(2);
        }
    });
 
 
    // ═══════════════════════════════════════════════════════════
    //  WIZARD CREAR  (igual que antes, con correcciones)
    // ═══════════════════════════════════════════════════════════
    let carrito    = [];
    let pasoActual = 1;
 
    function mostrarPaso(n) {
        [1, 2, 3].forEach(i => {
            document.getElementById('paso' + i).style.display = i === n ? 'block' : 'none';
            const tab = document.getElementById('tab' + i);
            tab.style.borderBottom = i === n ? '3px solid green' : '3px solid #ccc';
            tab.style.fontWeight   = i === n ? 'bold' : 'normal';
        });
        document.getElementById('btnAtras').style.display     = n > 1 ? 'inline-block' : 'none';
        document.getElementById('btnSiguiente').style.display = n < 3 ? 'inline-block' : 'none';
        document.getElementById('btnGuardar').style.display   = n === 3 ? 'inline-block' : 'none';
        pasoActual = n;
    }
 
    function siguientePaso() {
        if (pasoActual === 1 && !validarPaso1()) return;
        if (pasoActual === 2 && !validarPaso2()) return;
        if (pasoActual < 3) mostrarPaso(pasoActual + 1);
        if (pasoActual === 3) {
            const now   = new Date();
            const local = new Date(now - now.getTimezoneOffset() * 60000).toISOString().slice(0, 16);
            document.getElementById('est_fecha').value = local;
        }
    }
 
    function anteriorPaso() {
        if (pasoActual > 1) mostrarPaso(pasoActual - 1);
    }
 
    function validarPaso1() {
        if (!document.getElementById('ped_fecha').value ||
            !document.getElementById('ped_id_cliente').value ||
            !document.getElementById('ped_id_repartidor').value) {
            alert('Completa todos los campos del pedido.');
            return false;
        }
        return true;
    }
 
    function validarPaso2() {
        if (carrito.length === 0) {
            alert('Agrega al menos un producto al carrito.');
            return false;
        }
        return true;
    }
 
    function agregarAlCarrito() {
        const id_producto = document.getElementById('cp_id_producto').value;
        const u_venta     = document.getElementById('cp_u_venta').value;
        const cant        = parseFloat(document.getElementById('cp_cant').value);
        const p_venta     = parseFloat(document.getElementById('cp_p_venta').value);
 
        // ← Validación de producto corregida (antes faltaba)
        if (!id_producto) { alert('Selecciona un producto primero.'); return; }
        if (!cant || !p_venta) { alert('Completa cantidad y precio.'); return; }
 
        const disponible  = stockPorProducto[id_producto] ?? 0;
        const yaEnCarrito = carrito
            .filter(i => i.id_producto === id_producto)
            .reduce((sum, i) => sum + i.cant, 0);
 
        if (yaEnCarrito + cant > disponible) {
            const maxPosible = disponible - yaEnCarrito;
            alert(maxPosible <= 0
                ? `"${nombreProducto[id_producto]}" ya no tiene stock disponible.`
                : `Stock insuficiente para "${nombreProducto[id_producto]}". Puedes agregar máximo ${maxPosible} más.`
            );
            return;
        }
 
        carrito.push({ id_producto, u_venta, cant, p_venta, total: (cant * p_venta).toFixed(2) });
        renderCarrito();
 
        // ← Limpiar buscador y campos al agregar
        cpLimpiarSeleccion();
        document.getElementById('cp_cant').value    = '';
        document.getElementById('cp_p_venta').value = '';
    }
 
    function renderCarrito() {
        const tbody = document.getElementById('carritoBody');
        tbody.innerHTML = '';
        carrito.forEach((item, i) => {
            tbody.innerHTML += `
              <tr>
                <td>${nombreProducto[item.id_producto]}</td>
                <td>${item.u_venta}</td>
                <td>${item.cant}</td>
                <td>$${item.p_venta}</td>
                <td>$${item.total}</td>
                <td><button type="button" onclick="quitarItem(${i})"
                    class="w3-button w3-red w3-small">✕</button></td>
              </tr>`;
        });
        document.getElementById('carritoContainer').style.display = carrito.length ? 'block' : 'none';
    }
 
    function quitarItem(i) { carrito.splice(i, 1); renderCarrito(); }
 
    function enviarCarrito() {
        const estado   = document.getElementById('est_estado').value;
        const fechaEst = document.getElementById('est_fecha').value;
        if (!estado || !fechaEst) { alert('Completa estado y fecha.'); return; }
 
        document.getElementById('fn_fecha').value         = document.getElementById('ped_fecha').value;
        document.getElementById('fn_id_cliente').value    = document.getElementById('ped_id_cliente').value;
        document.getElementById('fn_id_repartidor').value = document.getElementById('ped_id_repartidor').value;
        document.getElementById('inputItems').value       = JSON.stringify(carrito);
        document.getElementById('fn_estado').value        = estado;
        document.getElementById('fn_fecha_estatus').value = fechaEst;
        document.getElementById('formCarrito').submit();
    }
 
    // ← cerrarModal ahora limpia el buscador y los campos
    function cerrarModal() {
        carrito = [];
        renderCarrito();
        mostrarPaso(1);
        cpLimpiarSeleccion();
        document.getElementById('ped_fecha').value   = '';
        document.getElementById('cp_cant').value     = '';
        document.getElementById('cp_p_venta').value  = '';
        document.getElementById('modalPpedido').style.display = 'none';
    }
 
    // ── Cierre por click fuera de cualquier modal ─────────────────
    window.onclick = function(event) {
        const modales = [
            'modalCliente', 'modalDireccion', 'modalEntrada',
            'modalProducto', 'modalRepartidor', 'modalExistencias',
            'modalMerma'
        ];
        modales.forEach(id => {
            const m = document.getElementById(id);
            if (m && event.target === m) m.style.display = 'none';
        });
        if (event.target === document.getElementById('modalPpedido')) {
            cerrarModal();
        }
    };
    </script>

    <?php
    // Extraemos limpiamente los datos que el controlador ya agrupó desde SQL
    
    // 1. Pedidos por semana
    $pedidos_labels = array_column($pedidosPorMes, 'semana');
    $semanas_count  = array_column($pedidosPorMes, 'total');

    // 2. Ventas por semana
    $ventas_labels  = array_column($ventasPorMes, 'semana');
    $semanas_ventas = array_column($ventasPorMes, 'ventas');

    // 3. Top 5 productos
    $top5_labels    = array_column($topProductos, 'nombre');
    $top5_data      = array_column($topProductos, 'total_vendido');

    // 4. Mermas por mes
    $mermas_labels  = array_column($perdidasMerma, 'mes');
    $mermas_data    = array_column($perdidasMerma, 'perdida');
    ?>

    <script>
    (function () {
        const colVerde  = 'rgba(0, 145, 10, 0.75)';
        const colVerdeB = 'rgba(0, 145, 10, 1)';
        const colLima   = 'rgba(155, 233, 49, 0.6)';
        const colRojo   = 'rgba(246, 78, 96, 0.75)';
        const colRojoB  = 'rgba(246, 78, 96, 1)';

        const defOpts = {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: {
                x: { grid: { display: false } },
                y: { beginAtZero: true, grid: { color: '#f0f0f0' } }
            }
        };

        // 1. Pedidos por semana
        new Chart(document.getElementById('pedidosChart'), {
            type: 'bar',
            data: {
                labels: <?= json_encode($pedidos_labels) ?>,
                datasets: [{
                    label: 'Pedidos',
                    data: <?= json_encode($semanas_count) ?>,
                    backgroundColor: colVerde,
                    borderColor: colVerdeB,
                    borderWidth: 1,
                    borderRadius: 6,
                }]
            },
            options: defOpts
        });

        // 2. Total en ventas
        new Chart(document.getElementById('ventasChart'), {
            type: 'line',
            data: {
                labels: <?= json_encode($ventas_labels) ?>,
                datasets: [{
                    label: 'Ventas ($)',
                    data: <?= json_encode($semanas_ventas) ?>,
                    backgroundColor: colLima,
                    borderColor: colVerdeB,
                    borderWidth: 2,
                    fill: true,
                    tension: 0.4,
                    pointBackgroundColor: colVerdeB,
                    pointRadius: 4,
                }]
            },
            options: defOpts
        });

        // 3. Top 5 productos más vendidos (horizontal)
        new Chart(document.getElementById('topProductosChart'), {
            type: 'bar',
            data: {
                labels: <?= json_encode($top5_labels) ?>,
                datasets: [{
                    label: 'Unidades',
                    data: <?= json_encode($top5_data) ?>,
                    backgroundColor: colVerde,
                    borderColor: colVerdeB,
                    borderWidth: 1,
                    borderRadius: 6,
                }]
            },
            options: {
                ...defOpts,
                indexAxis: 'y',
                scales: {
                    x: { beginAtZero: true, grid: { color: '#f0f0f0' } },
                    y: { grid: { display: false } }
                }
            }
        });

        // 4. Pérdidas por merma
        new Chart(document.getElementById('mermaChart'), {
            type: 'bar',
            data: {
                labels: <?= json_encode($mermas_labels) ?>,
                datasets: [{
                    label: 'Merma',
                    data: <?= json_encode($mermas_data) ?>,
                    backgroundColor: colRojo,
                    borderColor: colRojoB,
                    borderWidth: 1,
                    borderRadius: 6,
                }]
            },
            options: defOpts
        });
    })();
    </script>

</body>
</html>