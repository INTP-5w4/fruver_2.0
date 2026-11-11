<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <?= view('partials/estilos_panel') ?>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inicio · Fruver</title>
    <link rel="stylesheet" href="<?= base_url('estilos/dashboard.css') ?>">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
    <script src="<?= base_url('js/direccion_pedido.js') ?>" defer></script>
</head>
<body>
<?php include 'Header.php'; ?>
<?php include 'sidebar.php'; ?>
<?php
/*
 * Inicio del personal: primero lo que cambió contra la semana pasada y lo que hay que
 * atender hoy (Modelo_resumen::inicio), después las gráficas y el registro rápido.
 */
$r          = $resumen ?? [];
$diasSemana = ['domingo', 'lunes', 'martes', 'miércoles', 'jueves', 'viernes', 'sábado'];
$meses      = [1 => 'enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];
$mesesCorto = [1 => 'ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic'];
$diaHoy     = $diasSemana[(int) date('w')];
$fechaLarga = ucfirst($diaHoy) . ' ' . date('j') . ' de ' . $meses[(int) date('n')];
$horaActual = (int) date('G');
$saludo     = $horaActual < 12 ? 'Buenos días' : ($horaActual < 19 ? 'Buenas tardes' : 'Buenas noches');
$usuarioInicio = auth()->user();
$nombreInicio  = $usuarioInicio !== null ? (string) $usuarioInicio->username : '';
$dinero        = static fn ($v): string => '$' . number_format((float) $v, 0);

/** [texto, clase] de la comparación contra el periodo anterior. */
$comparar = static function (int|float|null $actual, int|float|null $antes, bool $menosEsMejor, callable $formato, string $contra): array {
    if ($actual === null || $antes === null) {
        return ['Sin datos para comparar', 'neutro'];
    }
    $dif = $actual - $antes;
    if (abs($dif) < 0.005) {
        return ['Igual que ' . $contra, 'neutro'];
    }
    $sube = $dif > 0;

    return [
        ($sube ? '▲ ' : '▼ ') . $formato(abs($dif)) . ($sube ? ' más' : ' menos') . ' que ' . $contra,
        ($menosEsMejor ? ! $sube : $sube) ? 'bien' : 'mal',
    ];
};
[$txtPedidos, $clsPedidos] = $comparar($r['pedidos_hoy'] ?? null, $r['pedidos_hace_7'] ?? null, false, static fn ($v) => (string) (int) $v, 'el ' . $diaHoy . ' pasado');
[$txtVentas, $clsVentas]   = $comparar($r['ventas_semana'] ?? null, $r['ventas_semana_ant'] ?? null, false, $dinero, 'la semana pasada');
[$txtMerma, $clsMerma]     = $comparar($r['merma_semana'] ?? null, $r['merma_semana_ant'] ?? null, true, $dinero, 'la semana pasada');
$porCaducar = $r['por_caducar'] ?? [];

// "Requiere atención": [gravedad, título, detalle, ruta, acción]
$atencion = [];
if (($r['fallidas_hoy'] ?? 0) > 0) {
    $n = (int) $r['fallidas_hoy'];
    $atencion[] = ['crit', $n . ($n === 1 ? ' entrega no se pudo hacer hoy' : ' entregas no se pudieron hacer hoy'), 'Revisa el motivo y vuelve a asignarla.', 'lista_entrega', 'Ver entregas'];
}
if (($r['sin_repartidor'] ?? 0) > 0) {
    $n = (int) $r['sin_repartidor'];
    $atencion[] = ['aviso', $n . ($n === 1 ? ' pedido confirmado sin repartidor' : ' pedidos confirmados sin repartidor'), 'Asígnales repartidor o arma una ruta.', 'lista_entrega', 'Asignar'];
}
if (($r['por_confirmar'] ?? 0) > 0) {
    $n = (int) $r['por_confirmar'];
    $atencion[] = ['aviso', $n . ($n === 1 ? ' pedido nuevo por confirmar' : ' pedidos nuevos por confirmar'), 'Llegaron de la tienda y esperan revisión.', 'lista_p_pedido?estado=pedido_realizado', 'Revisar'];
}
foreach (array_slice($porCaducar, 0, 3) as $cad) {
    $dias  = (int) round((strtotime($cad['fecha_cad']) - strtotime(date('Y-m-d'))) / 86400);
    $cuando = match (true) {
        $dias <= 0 => 'caduca hoy',
        $dias === 1 => 'caduca mañana',
        default    => 'caduca el ' . $diasSemana[(int) date('w', strtotime($cad['fecha_cad']))] . ' ' . date('j', strtotime($cad['fecha_cad'])),
    };
    $atencion[] = ['aviso', ucfirst($cad['nombre']) . ' ' . $cuando, 'Entrada #' . $cad['id'], 'lista_entrada', 'Ver'];
}
if (($r['sugerencias_7d'] ?? 0) > 0) {
    $n = (int) $r['sugerencias_7d'];
    $atencion[] = ['info', $n . ($n === 1 ? ' sugerencia esta semana' : ' sugerencias esta semana'), 'Ideas del equipo y de clientes para mejorar Fruver.', 'lista_sugerencia', 'Leer'];
}

// Productos con menos existencias (ya se calculaba aquí)
$bajos_stock = [];
foreach ($productos as $p) {
    $bajos_stock[] = ['nombre' => $p['nombre'], 'stock' => $stockPorProducto[$p['id']] ?? 0];
}
usort($bajos_stock, static fn ($a, $b) => $a['stock'] <=> $b['stock']);
$top5_bajos = array_slice($bajos_stock, 0, 5);
?>
    <div class="flash-container">
        <?php if (session()->getFlashdata('error')): ?>
            <div class="w3-panel w3-red"><p><?= esc(session()->getFlashdata('error')) ?></p></div>
        <?php endif; ?>
        <?php if (session()->getFlashdata('mensaje')): ?>
            <div class="w3-panel w3-green"><p><?= esc(session()->getFlashdata('mensaje')) ?></p></div>
        <?php endif; ?>
    </div>

    <main class="lista-wrapper inicio">
        <div class="pagina-cabeza">
            <div>
                <span class="pagina-sub"><?= esc($fechaLarga) ?></span>
                <h1 class="pagina-titulo"><?= esc($saludo) ?><?= $nombreInicio !== '' ? ', ' . esc($nombreInicio) : '' ?></h1>
            </div>
            <div class="pagina-acciones">
                <a href="<?= base_url('entrada_rapida') ?>" class="btn btn-secundario"><i class="fa-solid fa-bolt" aria-hidden="true"></i>Entrada rápida</a>
                <a href="<?= base_url('lista_p_pedido') ?>" class="btn btn-primario"><i class="fa-solid fa-receipt" aria-hidden="true"></i>Ver pedidos</a>
            </div>
        </div>

        <section class="inicio-kpis" aria-label="Indicadores">
            <div class="kpi">
                <span class="kpi-nombre">Pedidos hoy</span>
                <strong class="kpi-valor num"><?= ($r['pedidos_hoy'] ?? null) === null ? '—' : (int) $r['pedidos_hoy'] ?></strong>
                <span class="kpi-comp <?= $clsPedidos ?>"><?= esc($txtPedidos) ?></span>
            </div>
            <div class="kpi">
                <span class="kpi-nombre">Ventas de la semana</span>
                <strong class="kpi-valor num"><?= ($r['ventas_semana'] ?? null) === null ? '—' : esc($dinero($r['ventas_semana'])) ?></strong>
                <span class="kpi-comp <?= $clsVentas ?>"><?= esc($txtVentas) ?></span>
            </div>
            <div class="kpi">
                <span class="kpi-nombre">Pérdida por merma (semana)</span>
                <strong class="kpi-valor num"><?= ($r['merma_semana'] ?? null) === null ? '—' : esc($dinero($r['merma_semana'])) ?></strong>
                <span class="kpi-comp <?= $clsMerma ?>"><?= esc($txtMerma) ?></span>
            </div>
            <div class="kpi<?= $porCaducar !== [] ? ' atencion' : '' ?>">
                <span class="kpi-nombre">Por caducar</span>
                <strong class="kpi-valor num"><?= count($porCaducar) ?> <?= count($porCaducar) === 1 ? 'entrada' : 'entradas' ?></strong>
                <span class="kpi-comp <?= $porCaducar !== [] ? 'cuidado' : 'neutro' ?>">En los próximos 2 días</span>
            </div>
        </section>

        <div class="inicio-fila inicio-fila--principal">
            <section class="tarjeta">
                <div class="tarjeta-cab"><h2>Pedidos por semana</h2><small>Últimas 8 semanas</small></div>
                <div class="grafica-caja"><canvas id="pedidosChart" aria-label="Pedidos por semana" role="img"></canvas></div>
            </section>

            <section class="tarjeta">
                <div class="tarjeta-cab"><h2>Requiere atención</h2><small><?= count($atencion) ?></small></div>
                <?php if ($atencion === []): ?>
                    <div class="vacio"><b>Todo en orden</b><span>No hay entregas fallidas, pedidos sin repartidor ni productos por caducar.</span></div>
                <?php else: ?>
                    <ul class="atencion-lista">
                        <?php foreach ($atencion as [$gravedad, $titulo, $detalle, $ruta, $accion]): ?>
                            <li class="atencion-item atencion-<?= esc($gravedad, 'attr') ?>">
                                <div><b><?= esc($titulo) ?></b><span><?= esc($detalle) ?></span></div>
                                <a href="<?= base_url($ruta) ?>" class="btn btn-chico <?= $gravedad === 'aviso' || $gravedad === 'crit' ? 'btn-primario' : 'btn-secundario' ?>"><?= esc($accion) ?></a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </section>
        </div>

        <div class="inicio-fila">
            <section class="tarjeta">
                <div class="tarjeta-cab"><h2>Ventas por semana</h2><small>Últimas 8 semanas</small></div>
                <div class="grafica-caja"><canvas id="ventasChart" aria-label="Ventas por semana" role="img"></canvas></div>
            </section>
            <section class="tarjeta">
                <div class="tarjeta-cab"><h2>Pérdidas por merma</h2><small>Últimos 6 meses</small></div>
                <div class="grafica-caja"><canvas id="mermaChart" aria-label="Pérdidas por merma por mes" role="img"></canvas></div>
            </section>
        </div>

        <div class="inicio-fila">
            <section class="tarjeta">
                <div class="tarjeta-cab"><h2>Productos más vendidos</h2><small>Histórico, en pesos</small></div>
                <div class="grafica-caja"><canvas id="topProductosChart" aria-label="Cinco productos más vendidos" role="img"></canvas></div>
            </section>
            <section class="tarjeta">
                <div class="tarjeta-cab"><h2>Menos existencias</h2><small>5 productos</small></div>
                <table class="tabla-mini">
                    <thead><tr><th>Producto</th><th class="der">Disponible</th><th>Nivel</th></tr></thead>
                    <tbody>
                        <?php foreach ($top5_bajos as $p): ?>
                            <tr>
                                <td><?= esc($p['nombre']) ?></td>
                                <td class="der num"><?= esc($p['stock']) ?></td>
                                <td>
                                    <?php if ($p['stock'] <= 5): ?>
                                        <span class="est est-fallida">Bajo</span>
                                    <?php elseif ($p['stock'] <= 15): ?>
                                        <span class="est est-en_ruta">Medio</span>
                                    <?php else: ?>
                                        <span class="est est-entregada">Suficiente</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </section>
        </div>

        <section class="tarjeta registro-rapido">
            <div class="tarjeta-cab"><h2>Registro rápido</h2><small>Sin salir del inicio</small></div>
            <div class="registro-botones">
                <button type="button" onclick="document.getElementById('modalPpedido').style.display='block'" class="btn btn-secundario"><i class="fa-solid fa-receipt" aria-hidden="true"></i>Pedido</button>
                <button type="button" onclick="document.getElementById('modalEntrada').style.display='block'" class="btn btn-secundario"><i class="fa-solid fa-truck-ramp-box" aria-hidden="true"></i>Entrada</button>
                <button type="button" onclick="document.getElementById('modalExistencias').style.display='block'" class="btn btn-secundario"><i class="fa-solid fa-boxes-stacked" aria-hidden="true"></i>Existencias</button>
                <button type="button" onclick="document.getElementById('modalMerma').style.display='block'" class="btn btn-secundario"><i class="fa-solid fa-arrow-trend-down" aria-hidden="true"></i>Merma</button>
                <button type="button" onclick="document.getElementById('modalProducto').style.display='block'" class="btn btn-secundario"><i class="fa-solid fa-carrot" aria-hidden="true"></i>Producto</button>
                <button type="button" onclick="document.getElementById('modalCliente').style.display='block'" class="btn btn-secundario"><i class="fa-solid fa-user-plus" aria-hidden="true"></i>Cliente</button>
                <button type="button" onclick="document.getElementById('modalDireccion').style.display='block'" class="btn btn-secundario"><i class="fa-solid fa-location-dot" aria-hidden="true"></i>Dirección</button>
                <button type="button" onclick="document.getElementById('modalRepartidor').style.display='block'" class="btn btn-secundario"><i class="fa-solid fa-id-badge" aria-hidden="true"></i>Repartidor</button>
            </div>
        </section>

        <?php include 'Footer.php'; ?>
    </main>

    <div id="modalCliente" class="w3-modal" style="padding-top:100px;z-index:9999;">
        <div class="w3-modal-content w3-animate-zoom" style="max-width:500px;max-height:90vh;overflow-y:auto;">
            <form action="<?= base_url('guarda_cliente') ?>" method="post" class="w3-container w3-padding-16">
<?= csrf_field() ?>
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
<?= csrf_field() ?>
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
<?= csrf_field() ?>
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
<?= csrf_field() ?>
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
<?= csrf_field() ?>
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
                    <?php if (rutas_listas()): ?>
                    <label><b>Dirección de entrega</b></label>
                    <select id="ped_id_direccion" name="id_direccion" form="formCarrito" class="w3-select w3-border w3-margin-bottom"
                            data-direcciones-de="ped_id_cliente" data-api="<?= base_url('api_direcciones_cliente/') ?>">
                      <option value="">Dirección principal del cliente</option>
                    </select>
                    <label><b>Entregar antes de</b> <span class="w3-small">(opcional, para planear la ruta)</span></label>
                    <input type="time" id="ped_ventana_fin" name="ventana_fin" form="formCarrito" class="w3-input w3-border w3-margin-bottom">
                    <?php endif; ?>
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
<?= csrf_field() ?>
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
<?= csrf_field() ?>
                <input type="hidden" name="origen" value="main_page">
                <label><b>Entrada*</b></label>
                <select name="id_entrada" id="id_entrada_modal" class="w3-select w3-border w3-margin-bottom" required>
                    <option value="">-- Selecciona una entrada --</option>
                    <?php foreach ($entradas as $entrada): ?>
                        <?php if (($stockPorProducto[$entrada['id_producto']] ?? 0) > 0): ?>
                            <option value="<?= esc($entrada['id']) ?>">
                                #<?= esc($entrada['id']) ?> — <?= esc($entrada['nombre_producto']) ?> (<?= esc($entrada['fecha']) ?>)
                            </option>
                        <?php endif; ?>
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
    // Escapa texto antes de insertarlo con innerHTML (anti XSS)
    function escHtml(v) { return String(v ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c])); }
    const stockPorProducto = <?= json_seguro($stockPorProducto) ?>;
 
    const nombreProducto = <?= json_seguro(array_column($productos, 'nombre', 'id')) ?>;
 
    // Catálogo con unidad y precio sugeridos por producto
    const catalogoProductos = <?= json_seguro(array_map(fn($p) => [
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
    const selectEntrada = document.getElementById('id_entrada_modal');
    if (selectEntrada) {
        selectEntrada.addEventListener('change', function () {
            const entradas = <?= json_seguro(array_column($entradas, 'u_venta', 'id'), JSON_FORCE_OBJECT) ?>;
            document.getElementById('u_venta_modal').value = entradas[this.value] || '';
        });
    }
 
 
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

            // Con 1-2 caracteres no hacer nada
            if (query.length > 0 && query.length < 3) return;

            let candidatos;
            if (query.length === 0) {
                // Sin texto — mostrar todos (filtrados por categoría si aplica)
                candidatos = catalogoProductos.filter(p =>
                    !categoria || p.categoria === categoria
                );
            } else {
                // 3+ caracteres — filtrar por nombre y categoría
                candidatos = catalogoProductos.filter(p => {
                    const coincideNombre    = p.nombre.toLowerCase().includes(query);
                    const coincideCategoria = !categoria || p.categoria === categoria;
                    return coincideNombre && coincideCategoria;
                });
            }
 
            if (candidatos.length === 0) {
                divResultados.innerHTML =
                    '<div class="sin-resultados">Sin resultados para "' + escHtml(texto) + '"</div>';
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
                             data-nombre="${escHtml(p.nombre)}"
                             data-stock="${stock}">
                          <span>${escHtml(p.nombre)}</span>
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

        // Mostrar todos los productos al hacer focus en el campo
        inputBuscar.addEventListener('focus', function () {
            if (this.value.trim().length < 3) {
                buscar('');
            }
        });
 
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
        let total = 0;
        carrito.forEach((item, i) => {
            total += parseFloat(item.total);
            tbody.innerHTML += `
              <tr>
                <td>${escHtml(nombreProducto[item.id_producto])}</td>
                <td>${escHtml(item.u_venta)}</td>
                <td>${escHtml(item.cant)}</td>
                <td>${parseFloat(item.p_venta).toLocaleString('es-MX', { style: 'currency', currency: 'MXN' })}</td>
                <td>${parseFloat(item.total).toLocaleString('es-MX', { style: 'currency', currency: 'MXN' })}</td>
                <td><button type="button" onclick="quitarItem(${i})"
                    class="w3-button w3-red w3-small">✕</button></td>
              </tr>`;
        });
        tbody.innerHTML += `
          <tr>
            <td colspan="4" style="text-align:right;"><strong>Total:</strong></td>
            <td><strong>${total.toLocaleString('es-MX', { style: 'currency', currency: 'MXN' })}</strong></td>
            <td></td>
          </tr>`;
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
    // Datos de las gráficas (ya agrupados en SQL por el controlador), con etiquetas legibles.
    $semanaTexto = static function (string $s) use ($mesesCorto): string {
        if (preg_match('/^(\d{4})-W(\d{2})$/', $s, $m)) {
            $d = (new DateTime())->setISODate((int) $m[1], (int) $m[2]);

            return $d->format('j') . ' ' . $mesesCorto[(int) $d->format('n')];
        }

        return $s;
    };
    $mesTexto = static fn (string $s): string => preg_match('/^(\d{4})-(\d{2})$/', $s, $m)
        ? $mesesCorto[(int) $m[2]] . ' ' . $m[1]
        : $s;

    $pedidos_labels = array_map($semanaTexto, array_column($pedidosPorMes, 'semana'));
    $semanas_count  = array_map('intval', array_column($pedidosPorMes, 'total'));
    $ventas_labels  = array_map($semanaTexto, array_column($ventasPorMes, 'semana'));
    $semanas_ventas = array_map('floatval', array_column($ventasPorMes, 'ventas'));
    $top5_labels    = array_column($topProductos, 'nombre');
    $top5_data      = array_map('floatval', array_column($topProductos, 'total_vendido'));
    $mermas_labels  = array_map($mesTexto, array_column($perdidasMerma, 'mes'));
    $mermas_data    = array_map('floatval', array_column($perdidasMerma, 'perdida'));
    ?>

    <script>
    (function () {
        if (!window.Chart) { return; }

        // Paleta de Fruver (public/estilos/base.css)
        const HOJA = '#0b7d2e', HOJA_SUAVE = '#b9dcc2', HOJA_FONDO = 'rgba(11, 125, 46, 0.10)';
        const ALERTA = '#c2453c', ALERTA_SUAVE = '#ecb7b1', TINTA = '#4f5d53', LINEA = '#e8ecdf';

        Chart.defaults.font.family = "Figtree, 'Segoe UI', system-ui, sans-serif";
        Chart.defaults.font.size = 12;
        Chart.defaults.color = TINTA;

        const pesos = (v) => '$' + Number(v).toLocaleString('es-MX', { maximumFractionDigits: 0 });
        // La última barra (el periodo más reciente) se resalta
        const resaltarUltima = (datos, base, fuerte) => datos.map((_, i) => i === datos.length - 1 ? fuerte : base);

        // Sin datos (o todo en cero): mensaje en lugar de una gráfica vacía
        const hayDatos = (id, datos, mensaje) => {
            if (datos.some((v) => Number(v) > 0)) { return true; }
            const caja = document.getElementById(id).parentElement;
            const p = document.createElement('p');
            p.className = 'grafica-vacia';
            p.textContent = mensaje;
            caja.replaceChildren(p);
            return false;
        };

        const opciones = (formato) => ({
            responsive: true,
            maintainAspectRatio: false,
            layout: { padding: { left: 6, right: 6 } },
            plugins: {
                legend: { display: false },
                tooltip: { callbacks: formato ? { label: (c) => ' ' + formato(c.parsed.y ?? c.parsed.x) } : {} }
            },
            scales: {
                x: { grid: { display: false }, border: { color: LINEA } },
                y: { beginAtZero: true, grid: { color: LINEA }, border: { display: false },
                     ticks: formato ? { precision: 0, maxTicksLimit: 6, callback: (v) => formato(v) } : { precision: 0, maxTicksLimit: 6 } }
            }
        });

        const pedidos = <?= json_seguro($semanas_count) ?>;
        if (hayDatos('pedidosChart', pedidos, 'No hubo pedidos en las últimas 8 semanas.')) {
            new Chart(document.getElementById('pedidosChart'), {
                type: 'bar',
                data: {
                    labels: <?= json_seguro($pedidos_labels) ?>,
                    datasets: [{ label: 'Pedidos', data: pedidos, backgroundColor: resaltarUltima(pedidos, HOJA_SUAVE, HOJA), borderRadius: 4, maxBarThickness: 40 }]
                },
                options: opciones(null)
            });
        }

        const ventas = <?= json_seguro($semanas_ventas) ?>;
        if (hayDatos('ventasChart', ventas, 'No hubo ventas en las últimas 8 semanas.')) {
            new Chart(document.getElementById('ventasChart'), {
                type: 'line',
                data: {
                    labels: <?= json_seguro($ventas_labels) ?>,
                    datasets: [{ label: 'Ventas', data: ventas, borderColor: HOJA, backgroundColor: HOJA_FONDO,
                                 borderWidth: 2, fill: true, tension: 0.35, pointBackgroundColor: HOJA, pointRadius: 3 }]
                },
                options: opciones(pesos)
            });
        }

        const mermas = <?= json_seguro($mermas_data) ?>;
        if (hayDatos('mermaChart', mermas, 'Sin pérdidas por merma en los últimos 6 meses.')) {
            new Chart(document.getElementById('mermaChart'), {
                type: 'bar',
                data: {
                    labels: <?= json_seguro($mermas_labels) ?>,
                    datasets: [{ label: 'Pérdida', data: mermas, backgroundColor: resaltarUltima(mermas, ALERTA_SUAVE, ALERTA), borderRadius: 4, maxBarThickness: 40 }]
                },
                options: opciones(pesos)
            });
        }

        const vendidos = <?= json_seguro($top5_data) ?>;
        if (hayDatos('topProductosChart', vendidos, 'Todavía no hay ventas registradas.')) {
            const top = opciones(pesos);
            top.indexAxis = 'y';
            top.scales = {
                x: { beginAtZero: true, grid: { color: LINEA }, border: { display: false }, ticks: { maxTicksLimit: 5, callback: (v) => pesos(v) } },
                y: { grid: { display: false }, border: { color: LINEA } }
            };
            top.plugins.tooltip.callbacks = { label: (c) => ' ' + pesos(c.parsed.x) };
            new Chart(document.getElementById('topProductosChart'), {
                type: 'bar',
                data: {
                    labels: <?= json_seguro($top5_labels) ?>,
                    datasets: [{ label: 'Vendido', data: vendidos, backgroundColor: HOJA, borderRadius: 4, maxBarThickness: 26 }]
                },
                options: top
            });
        }
    })();
    </script>

</body>
</html>