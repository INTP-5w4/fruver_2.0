<?php
/**
 * Menú lateral del panel (solo personal). Un único menú para todas las pantallas,
 * agrupado por área y con el módulo activo marcado según la URL.
 * Cada opción: [ruta, ícono de Font Awesome, texto, rutas que la activan].
 *
 * Este archivo se incluye con `include` y comparte el ámbito de la vista que lo llama.
 * Por eso todo va dentro de una función: antes, el foreach dejaba una variable $ruta
 * (texto) que pisaba la $ruta (arreglo) de rutas_detalle.php y provocaba el error
 * "Cannot access offset of type string on string".
 */
(static function (): void {
    $rutaActual = trim(uri_string(), '/');
    $grupos = [
        '' => [
            ['/', 'fa-house', 'Inicio', ['']],
        ],
        'Ventas' => [
            ['lista_p_pedido', 'fa-receipt', 'Productos por pedido', ['lista_p_pedido', 'crea_p_pedido', 'pasa_id_p_pedido', 'ticket_pedido', 'ticket_impreso']],
            // "Pedidos" (lista_pedido) se oculta del menú: todo el flujo de pedidos se maneja
            // desde "Productos por pedido". Sus rutas siguen activas.
            // "Estatus" (lista_estatus) también se oculta: el estatus se cambia desde "Productos por pedido"
            // (selector de la columna Estatus) o desde el modal de edición del pedido.
            ['lista_cliente', 'fa-users', 'Clientes', ['lista_cliente', 'crea_cliente', 'pasa_id_cliente']],
            ['comunicaciones', 'fa-comment-dots', 'Comunicaciones', ['comunicaciones']],
        ],
        'Inventario' => [
            ['lista_producto', 'fa-carrot', 'Productos', ['lista_producto', 'crea_producto', 'pasa_id_producto']],
            ['lista_entrada', 'fa-truck-ramp-box', 'Entradas', ['lista_entrada', 'crea_entrada', 'pasa_id_entrada']],
            ['entrada_rapida', 'fa-bolt', 'Entrada rápida', ['entrada_rapida']],
            ['lista_existencia', 'fa-boxes-stacked', 'Existencias', ['lista_existencia', 'crea_existencia', 'pasa_id_existencia']],
            ['lista_merma', 'fa-arrow-trend-down', 'Mermas', ['lista_merma', 'crea_merma', 'pasa_id_merma']],
        ],
        'Reparto' => [
            ['lista_entrega', 'fa-truck', 'Entregas', ['lista_entrega', 'evidencia_entrega']],
            ['rutas', 'fa-route', 'Rutas de reparto', ['rutas']],
            ['lista_repartidor', 'fa-id-badge', 'Repartidores', ['lista_repartidor', 'crea_repartidor', 'pasa_id_repartidor']],
            ['lista_direccion', 'fa-location-dot', 'Direcciones', ['lista_direccion', 'crea_direccion', 'pasa_id_direccion']],
        ],
        'Sistema' => [
            ['lista_sugerencia', 'fa-lightbulb', 'Sugerencias', ['lista_sugerencia']],
            ['impresora', 'fa-print', 'Impresora', ['impresora']],
            ['privacidad/preferencias', 'fa-user-shield', 'Privacidad', ['privacidad']],
        ],
    ];
    $activa = static function (array $rutas) use ($rutaActual): bool {
        foreach ($rutas as $r) {
            if ($rutaActual === $r || ($r !== '' && str_starts_with($rutaActual, $r . '/'))) {
                return true;
            }
        }

        return false;
    };
    ?>
<aside class="sidebar" id="menu-panel">
    <nav class="sidebar-nav" aria-label="Módulos del panel">
        <?php foreach ($grupos as $grupo => $opciones): ?>
            <div class="nav-grupo">
                <?php if ($grupo !== ''): ?><span class="nav-label"><?= esc($grupo) ?></span><?php endif; ?>
                <?php foreach ($opciones as [$destino, $icono, $texto, $activaCon]): ?>
                    <?php $esActiva = $activa($activaCon); ?>
                    <a href="<?= base_url($destino) ?>" class="nav-item<?= $esActiva ? ' activo' : '' ?>"<?= $esActiva ? ' aria-current="page"' : '' ?>>
                        <i class="fa-solid <?= esc($icono, 'attr') ?>" aria-hidden="true"></i><?= esc($texto) ?>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endforeach; ?>
    </nav>
</aside>
<div class="sidebar-fondo" data-cerrar-menu></div>
<?php
})();
