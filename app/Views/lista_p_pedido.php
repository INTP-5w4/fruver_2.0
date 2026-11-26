<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <?= view('partials/estilos_panel') ?>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="<?= base_url('estilos/ticket.css') ?>">
    <script src="<?= base_url('js/cambio_estado.js') . '?v=' . @filemtime(FCPATH . 'js/cambio_estado.js') ?>" defer></script>
    <link rel="stylesheet" href="<?= base_url('estilos/pedido_formulario.css') . '?v=' . @filemtime(FCPATH . 'estilos/pedido_formulario.css') ?>">
    <meta name="csrf-token" content="<?= esc(csrf_hash(), 'attr') ?>">
    <title>Pedidos · Fruver</title>
</head>
<body>
<?php include 'Header.php'; ?>
<?php include 'sidebar.php'; ?>

<div class="lista-wrapper">

<div class="flash-container">
    <?php if (session()->getFlashdata('error')): ?>
        <div class="w3-panel w3-red w3-animate-opacity">
            <p><?= esc(session()->getFlashdata('error')) ?></p>
        </div>
    <?php endif; ?>
    <?php if (session()->getFlashdata('mensaje')): ?>
        <div class="w3-panel w3-green w3-animate-opacity">
            <p><?= esc(session()->getFlashdata('mensaje')) ?></p>
        </div>
    <?php endif; ?>
</div>

<div class="contenedor-boton">
    <div>
        <span class="pagina-sub"><?= (int) $total_pedidos ?> <?= (int) $total_pedidos === 1 ? 'pedido' : 'pedidos' ?><?= ($estado ?? '') !== '' ? ' · ' . esc(estado_pedido_texto($estado)) : '' ?></span>
        <h1>Pedidos</h1>
    </div>
    <div class="contenedor-boton__acciones">
        <a href="<?= base_url('impresora') ?>" class="btn-cancelar" title="Configurar la impresora de tickets"
           aria-label="Configurar la impresora de tickets">
            <i class="fa-solid fa-gear" aria-hidden="true"></i> Configurar impresora
        </a>
        <button type="button" onclick="abrirNuevoPedido()" class="btn-agregar">
            <i class="fa-solid fa-plus" aria-hidden="true"></i> Nuevo pedido
        </button>
    </div>
</div>

<form method="get" action="<?= base_url('lista_p_pedido') ?>">
    <div>
        <?php if (($estado ?? '') !== ''): ?><input type="hidden" name="estado" value="<?= esc($estado, 'attr') ?>"><?php endif; ?>
        <input type="text" name="buscar" value="<?= esc($buscar ?? '', 'attr') ?>" placeholder="Buscar por número de pedido…" class="w3-input w3-border" aria-label="Buscar pedido">
        <button type="submit" class="w3-button w3-green">
            <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i> Buscar
        </button>
        <?php if (!empty($buscar)): ?>
            <a href="<?= base_url('lista_p_pedido') . (($estado ?? '') !== '' ? '?estado=' . esc($estado, 'url') : '') ?>" class="w3-button w3-border"><i class="fa-solid fa-eraser" aria-hidden="true"></i> Limpiar búsqueda</a>
        <?php endif; ?>
    </div>
</form>

<?php
// Pestañas por estado, en el orden en que avanza un pedido. Conservan la búsqueda.
$pestanas = ['' => 'Todos'];
foreach (array_keys(\App\Models\Modelo_pedido::TRANSICIONES) as $clave) {
    $pestanas[$clave] = estado_pedido_texto($clave);
}
$enlacePestana = static function (string $clave) use ($buscar): string {
    $q = array_filter(['estado' => $clave, 'buscar' => (string) ($buscar ?? '')], static fn ($v) => $v !== '');

    return base_url('lista_p_pedido') . ($q !== [] ? '?' . http_build_query($q) : '');
};
?>
<nav class="pestanas-estado" aria-label="Filtrar por estado">
    <?php foreach ($pestanas as $clave => $texto): ?>
        <?php $n = $clave === '' ? array_sum($conteoEstados ?? []) : (int) (($conteoEstados ?? [])[$clave] ?? 0); ?>
        <?php if ($clave !== '' && $n === 0 && ($estado ?? '') !== $clave) { continue; } ?>
        <a href="<?= esc($enlacePestana($clave), 'attr') ?>" class="pestana<?= ($estado ?? '') === $clave ? ' activa' : '' ?>"<?= ($estado ?? '') === $clave ? ' aria-current="page"' : '' ?>>
            <?= esc($texto) ?> <b class="num"><?= $n ?></b>
        </a>
    <?php endforeach; ?>
</nav>

<!-- ══ TABLA PRINCIPAL ══ -->
<div class="tabla-wrapper">
<table class="tabla-pedidos">
    <thead>
        <tr>
            <th></th>
            <th>ID Pedido</th>
            <th>Fecha</th>
            <th>Cliente</th>
            <th>Productos</th>
            <th>Total pedido</th>
            <th>Repartidor</th>
            <th>Estatus</th>
            <th class="col-acciones">Acciones</th>
        </tr>
    </thead>
    <tbody>
    <?php foreach ($pedidos_agrupados as $pedido): ?>

        <tr class="fila-pedido" onclick="toggleDetalle(<?= esc($pedido['id_pedido'], 'js') ?>)">
            <td>
                <button type="button" class="btn-expandir" id="btn-<?= esc($pedido['id_pedido']) ?>"
                        aria-expanded="false" aria-controls="detalle-<?= (int) $pedido['id_pedido'] ?>"
                        aria-label="Ver los productos del pedido <?= (int) $pedido['id_pedido'] ?>">
                    <i class="fa-solid fa-chevron-right" aria-hidden="true"></i>
                </button>
            </td>
            <td><b>#<?= esc($pedido['id_pedido']) ?></b></td>
            <td><?= esc($pedido['fecha_pedido'] ?? '—') ?></td>
            <td><?= esc($pedido['nombre_cliente'].' '.$pedido['ape_pat_cliente'].' '.$pedido['ape_mat_cliente']) ?></td>
            <td>
                <span class="resumen-productos" title="<?= esc(implode(', ', array_column($pedido['items'], 'nombre_producto'))) ?>">
                    <?= esc(implode(', ', array_column($pedido['items'], 'nombre_producto'))) ?>
                </span>
            </td>
            <td class="total-pedido">$<?= number_format($pedido['total_pedido'], 2) ?></td>
            <td><?= esc($pedido['nombre_repartidor'].' '.$pedido['ape_pat_repartidor'].' '.$pedido['ape_mat_repartidor']) ?></td>
            <?php
                $estadoFila  = $pedido['estado_actual'] ?? 'pedido_pendiente';
                $siguientes  = \App\Models\Modelo_pedido::TRANSICIONES[$estadoFila] ?? [];
            ?>
            <td class="celda-estado" onclick="event.stopPropagation()">
                <div class="estado-rapido" data-estado-rapido>
                    <?= estado_pedido($estadoFila) ?>
                    <?php if ($siguientes !== []): ?>
                        <form method="post" action="<?= base_url('pedido/' . (int) $pedido['id_pedido'] . '/estado') ?>" class="cambio-estado" data-cambio-estado>
                            <?= csrf_field() ?>
                            <select name="estado" aria-label="Cambiar el estatus del pedido <?= (int) $pedido['id_pedido'] ?>" title="Cambiar estatus">
                                <option value="" selected disabled>Cambiar a…</option>
                                <?php foreach ($siguientes as $sig): ?>
                                    <option value="<?= esc($sig, 'attr') ?>"><?= esc(estado_pedido_texto($sig)) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <noscript><button type="submit" class="btn btn-chico btn-secundario"><i class="fa-solid fa-pen" aria-hidden="true"></i> Cambiar</button></noscript>
                        </form>
                    <?php endif; ?>
                </div>
            </td>
            <td class="col-acciones" onclick="event.stopPropagation()">
                <div class="acciones-fila">
                    <a href="<?= base_url('ticket_pedido/' . (int) $pedido['id_pedido']) ?>" class="btn-icono"
                       title="Enviar el ticket por WhatsApp" aria-label="Enviar el ticket del pedido <?= (int) $pedido['id_pedido'] ?> por WhatsApp">
                        <i class="fa-brands fa-whatsapp" aria-hidden="true"></i>
                    </a>
                    <button type="button" class="btn-icono" title="Imprimir ticket" aria-label="Imprimir el ticket del pedido <?= (int) $pedido['id_pedido'] ?>"
                            data-url="<?= base_url('imprime_ticket/' . (int) $pedido['id_pedido']) ?>"
                            onclick="imprimirTicket(this, event)">
                        <i class="fa-solid fa-print" aria-hidden="true"></i>
                    </button>
                    <button type="button" class="btn-icono" title="Volver a pedir lo mismo" aria-label="Volver a pedir lo mismo que el pedido <?= (int) $pedido['id_pedido'] ?>"
                            onclick="recargarPedido('<?= esc($pedido['id_pedido'], 'js') ?>')">
                        <i class="fa-solid fa-rotate-right" aria-hidden="true"></i>
                    </button>
                    <button type="button" class="btn-icono" title="Editar" aria-label="Editar el pedido <?= (int) $pedido['id_pedido'] ?>"
                            onclick="abrirEditarPedido('<?= esc($pedido['id_pedido'], 'js') ?>')">
                        <i class="fa-solid fa-pen-to-square" aria-hidden="true"></i>
                    </button>
                    <form method="post" action="<?= base_url('borra_pedido_completo/' . (int) $pedido['id_pedido']) ?>" class="form-borrar" onsubmit="return confirm('¿Eliminar este pedido y todos sus productos?');">
                        <?= csrf_field() ?>
                        <button type="submit" class="btn-icono peligro" title="Eliminar" aria-label="Eliminar el pedido <?= (int) $pedido['id_pedido'] ?>">
                            <i class="fa-solid fa-trash-can" aria-hidden="true"></i>
                        </button>
                    </form>
                </div>
            </td>
        </tr>

        <tr class="fila-detalle" id="detalle-<?= esc($pedido['id_pedido']) ?>">
            <td colspan="9">
                <div class="detalle-inner">
                    <b>Productos del pedido #<?= esc($pedido['id_pedido']) ?>:</b>
                    <table class="w3-table w3-bordered w3-small">
                        <thead>
                            <tr>
                                <th>Producto</th>
                                <th>Unidad</th>
                                <th>Cantidad</th>
                                <th>Precio unit.</th>
                                <th>Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($pedido['items'] as $item): ?>
                            <tr>
                                <td><?= esc($item['nombre_producto']) ?></td>
                                <td><?= esc($item['unidad_venta']) ?></td>
                                <td><?= esc($item['cant']) ?></td>
                                <td>$<?= number_format($item['precio_venta'], 2) ?></td>
                                <td>$<?= number_format($item['total'], 2) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>

                    <?php $movimientos = $bitacora[(int) $pedido['id_pedido']] ?? []; ?>
                    <?php if ($movimientos !== []): ?>
                        <!-- Trazabilidad (Aviso de Privacidad 3.2): quién capturó y quién modificó el pedido -->
                        <b class="bitacora-titulo">Historial del pedido #<?= esc($pedido['id_pedido']) ?>:</b>
                        <table class="w3-table w3-bordered w3-small bitacora-tabla">
                            <thead>
                                <tr><th>Fecha</th><th>Usuario</th><th>Acción</th><th>Detalle</th></tr>
                            </thead>
                            <tbody>
                                <?php foreach ($movimientos as $m): ?>
                                <tr>
                                    <td><?= esc(date('d/m/Y H:i', strtotime((string) $m['fecha']))) ?></td>
                                    <td><?= esc($m['usuario']) ?></td>
                                    <td><?= esc(\App\Models\Modelo_bitacora_pedido::ACCIONES[$m['accion']] ?? $m['accion']) ?></td>
                                    <td><?= esc($m['detalle'] ?? '') ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </td>
        </tr>

    <?php endforeach; ?>
    </tbody>
</table>

<?php if ($total_paginas > 1): ?>
<?php
// Conserva la búsqueda y el estado al cambiar de página
$enlacePagina = static fn (int $n): string => '?' . http_build_query(array_filter(
    ['estado' => $estado ?? '', 'buscar' => (string) ($buscar ?? ''), 'page' => $n],
    static fn ($v) => $v !== ''
));
?>
<?php
$vent  = 2;
$desde = max(1, (int) $pagina_actual - $vent);
$hasta = min((int) $total_paginas, (int) $pagina_actual + $vent);
?>
<nav class="paginacion" aria-label="Páginas">
    <?php if ($pagina_actual > 1): ?>
        <a href="<?= esc($enlacePagina($pagina_actual - 1), 'attr') ?>" class="w3-button w3-border" aria-label="Página anterior">&laquo;</a>
    <?php endif; ?>

    <?php if ($desde > 1): ?>
        <a href="<?= esc($enlacePagina(1), 'attr') ?>" class="w3-button w3-border">1</a>
        <?php if ($desde > 2): ?><span class="paginacion-estado">…</span><?php endif; ?>
    <?php endif; ?>

    <?php for ($i = $desde; $i <= $hasta; $i++): ?>
        <a href="<?= esc($enlacePagina($i), 'attr') ?>" class="w3-button <?= $i === (int) $pagina_actual ? 'w3-green' : 'w3-border' ?>"<?= $i === (int) $pagina_actual ? ' aria-current="page"' : '' ?>><?= $i ?></a>
    <?php endfor; ?>

    <?php if ($hasta < $total_paginas): ?>
        <?php if ($hasta < $total_paginas - 1): ?><span class="paginacion-estado">…</span><?php endif; ?>
        <a href="<?= esc($enlacePagina((int) $total_paginas), 'attr') ?>" class="w3-button w3-border"><?= (int) $total_paginas ?></a>
    <?php endif; ?>

    <?php if ($pagina_actual < $total_paginas): ?>
        <a href="<?= esc($enlacePagina($pagina_actual + 1), 'attr') ?>" class="w3-button w3-border" aria-label="Página siguiente">&raquo;</a>
    <?php endif; ?>
</nav>
<?php endif; ?>

</div><!-- /tabla-wrapper -->


<!-- Ventanas «Nuevo pedido» y «Editar pedido»: una sola pantalla cada una -->
<?= view('partials/modal_pedido', ['modo' => 'crear', 'clientes' => $clientes, 'repartidores' => $repartidores, 'idsPreferenciales' => $idsPreferenciales]) ?>
<?= view('partials/modal_pedido', ['modo' => 'editar', 'clientes' => $clientes, 'repartidores' => $repartidores, 'idsPreferenciales' => $idsPreferenciales]) ?>

<?php include 'Footer.php'; ?>

<?php
// Datos para public/js/pedido_formulario.js (precio y unidad sugeridos: los de la última entrada).
$estadosTexto = [];
foreach (array_keys(\App\Models\Modelo_pedido::TRANSICIONES) as $claveEstado) {
    $estadosTexto[$claveEstado] = estado_pedido_texto($claveEstado);
}
?>
<script type="application/json" id="datos-pedido"><?= json_seguro([
    'catalogo' => array_map(static fn (array $p): array => [
        'id'        => (int) $p['id'],
        'nombre'    => (string) $p['nombre'],
        'categoria' => (string) ($p['categoria'] ?? ''),
        'u_venta'   => (string) ($uVentaSugeridaPorProducto[$p['id']] ?? ''),
        'precio'    => $precioSugeridoPorProducto[$p['id']] ?? '',
    ], $productos),
    'stock'          => $stockPorProducto,
    'preferenciales' => array_map('intval', $idsPreferenciales),
    'descuento'      => (float) $descuentoPreferencial,
    'estados'        => $estadosTexto,
    'urls'           => [
        'pedido'       => base_url('api_pedido/'),
        'recargar'     => base_url('api_recargar_pedido/'),
        'crearCliente' => base_url('clientes/crear-rapido'),
    ],
]) ?></script>

</div><!-- /.lista-wrapper -->
<script src="<?= base_url('js/impresion_ticket.js') ?>"></script>
<script src="<?= base_url('js/direccion_pedido.js') ?>"></script>
<script src="<?= base_url('js/pedido_formulario.js') . '?v=' . @filemtime(FCPATH . 'js/pedido_formulario.js') ?>"></script>
</body>
</html>