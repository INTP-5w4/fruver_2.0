<?php
/**
 * Navegación del panel (solo personal). Un único catálogo de opciones, agrupado por área,
 * que se dibuja de dos formas según el tamaño de pantalla (public/estilos/estilosPaginas.css):
 *
 *  - Escritorio y tableta (≥ 768 px): menú lateral con grupos plegables. Solo se abre el
 *    grupo de la página actual; el resto se recuerda como lo dejó la persona (panel.js).
 *  - Celular (< 768 px): barra inferior con 4 secciones + «Más», y una fila con las pantallas
 *    del área actual arriba del contenido. «Más» abre una hoja con TODAS las opciones.
 *
 * Cada opción: [ruta, ícono de Font Awesome, texto, rutas que la activan]. El texto es el
 * mismo que el título de la página a la que lleva («¿en qué página estoy?»).
 *
 * Este archivo se incluye con `include` y comparte el ámbito de la vista que lo llama;
 * por eso todo va dentro de una función.
 */
(static function (): void {
    $rutaActual = trim(uri_string(), '/');
    $grupos = [
        'ventas' => ['Ventas', 'fa-receipt', [
            ['lista_p_pedido', 'fa-receipt', 'Pedidos', ['lista_p_pedido', 'crea_p_pedido', 'pasa_id_p_pedido', 'ticket_pedido', 'ticket_impreso', 'lista_pedido', 'pasa_id_pedido', 'crea_pedido', 'lista_estatus', 'crea_estatus', 'pasa_id_estatus']],
            ['lista_cliente', 'fa-users', 'Clientes', ['lista_cliente', 'crea_cliente', 'pasa_id_cliente']],
            ['comunicaciones', 'fa-comment-dots', 'Comunicaciones', ['comunicaciones']],
        ]],
        'inventario' => ['Inventario', 'fa-boxes-stacked', [
            ['lista_producto', 'fa-carrot', 'Productos', ['lista_producto', 'crea_producto', 'pasa_id_producto']],
            ['ofertas', 'fa-tags', 'Ofertas', ['ofertas']],
            ['lista_existencia', 'fa-boxes-stacked', 'Existencias', ['lista_existencia', 'crea_existencia', 'pasa_id_existencia']],
            ['lista_entrada', 'fa-truck-ramp-box', 'Entradas', ['lista_entrada', 'crea_entrada', 'pasa_id_entrada']],
            ['entrada_rapida', 'fa-bolt', 'Entrada rápida', ['entrada_rapida']],
            ['lista_merma', 'fa-arrow-trend-down', 'Mermas', ['lista_merma', 'crea_merma', 'pasa_id_merma']],
        ]],
        'reparto' => ['Reparto', 'fa-truck', [
            ['lista_entrega', 'fa-truck', 'Entregas', ['lista_entrega', 'evidencia_entrega']],
            ['rutas', 'fa-route', 'Rutas de reparto', ['rutas']],
            ['lista_repartidor', 'fa-id-badge', 'Repartidores', ['lista_repartidor', 'crea_repartidor', 'pasa_id_repartidor']],
            ['lista_direccion', 'fa-location-dot', 'Direcciones', ['lista_direccion', 'crea_direccion', 'pasa_id_direccion']],
        ]],
        'sistema' => ['Sistema', 'fa-gear', [
            ['lista_sugerencia', 'fa-lightbulb', 'Sugerencias', ['lista_sugerencia']],
            ['auditoria', 'fa-clipboard-list', 'Auditoría', ['auditoria']],
            ['impresora', 'fa-print', 'Impresora', ['impresora']],
            ['privacidad/preferencias', 'fa-user-shield', 'Privacidad', ['privacidad']],
        ]],
    ];
    // Roles: solo superadmin (la ruta también lo exige con el filtro group:superadmin).
    $usuarioMenu = auth()->user();
    if ($usuarioMenu !== null && $usuarioMenu->inGroup('superadmin')) {
        $grupos['sistema'][2][] = ['roles', 'fa-user-gear', 'Roles', ['roles']];
    }

    $activa = static function (array $rutas) use ($rutaActual): bool {
        foreach ($rutas as $r) {
            if ($rutaActual === $r || ($r !== '' && str_starts_with($rutaActual, $r . '/'))) {
                return true;
            }
        }

        return false;
    };
    $esInicio = $rutaActual === '';

    // Grupo de la página actual: abre su grupo en el menú y marca su sección en celular.
    $grupoActual = null;
    foreach ($grupos as $clave => [, , $opciones]) {
        foreach ($opciones as $op) {
            if ($activa($op[3])) {
                $grupoActual = $clave;
            }
        }
    }

    // Barra inferior (celular): 5 destinos como máximo. Sistema y la cuenta van en «Más».
    $secciones = [['/', 'fa-house', 'Inicio', $esInicio]];
    foreach (['ventas', 'inventario', 'reparto'] as $clave) {
        $secciones[] = [$grupos[$clave][2][0][0], $grupos[$clave][1], $grupos[$clave][0], $grupoActual === $clave];
    }
    $masActivo = $grupoActual === 'sistema';

    $enlace = static fn (array $op, bool $esActiva, string $clase): string =>
        '<a href="' . esc(base_url($op[0]), 'attr') . '" class="' . $clase . ($esActiva ? ' activo' : '') . '"'
        . ($esActiva ? ' aria-current="page"' : '') . '><i class="fa-solid ' . esc($op[1], 'attr') . '" aria-hidden="true"></i><span>'
        . esc($op[2]) . '</span></a>';
    ?>
<aside class="sidebar" id="menu-panel">
    <nav class="sidebar-nav" aria-label="Menú principal">
        <?= $enlace(['/', 'fa-house', 'Inicio'], $esInicio, 'nav-item') ?>
        <?php foreach ($grupos as $clave => [$titulo, $icono, $opciones]): ?>
            <details class="nav-grupo" data-grupo="<?= esc($clave, 'attr') ?>"<?= $grupoActual === $clave ? ' open data-actual' : '' ?>>
                <summary class="nav-label">
                    <span><?= esc($titulo) ?></span>
                    <i class="fa-solid fa-chevron-down nav-flecha" aria-hidden="true"></i>
                </summary>
                <div class="nav-opciones">
                    <?php foreach ($opciones as $op): ?>
                        <?= $enlace($op, $activa($op[3]), 'nav-item') ?>
                    <?php endforeach; ?>
                </div>
            </details>
        <?php endforeach; ?>
    </nav>
</aside>

<?php if ($grupoActual !== null): ?>
    <?php [$tituloGrupo, , $opcionesGrupo] = $grupos[$grupoActual]; ?>
    <!-- Celular: las demás pantallas del área actual, a un toque -->
    <nav class="subnav" aria-label="<?= esc('Pantallas de ' . $tituloGrupo, 'attr') ?>">
        <?php foreach ($opcionesGrupo as $op): ?>
            <?= $enlace($op, $activa($op[3]), 'subnav-item') ?>
        <?php endforeach; ?>
    </nav>
<?php endif; ?>

<nav class="nav-inferior" aria-label="Secciones">
    <?php foreach ($secciones as [$destino, $icono, $texto, $esActiva]): ?>
        <?= $enlace([$destino, $icono, $texto], $esActiva, 'nav-inferior-item') ?>
    <?php endforeach; ?>
    <button type="button" class="nav-inferior-item<?= $masActivo ? ' activo' : '' ?>" data-abrir-hoja="hoja-mas"
            aria-haspopup="dialog" aria-expanded="false" aria-controls="hoja-mas"<?= $masActivo ? ' aria-current="true"' : '' ?>>
        <i class="fa-solid fa-bars" aria-hidden="true"></i><span>Más</span>
    </button>
</nav>

<div class="hoja-fondo" data-cerrar-hoja hidden></div>
<div class="hoja" id="hoja-mas" role="dialog" aria-modal="true" aria-labelledby="hoja-mas-titulo" hidden>
    <div class="hoja-cabeza">
        <h2 id="hoja-mas-titulo">Todas las opciones</h2>
        <button type="button" class="hoja-cerrar" data-cerrar-hoja aria-label="Cerrar">
            <i class="fa-solid fa-xmark" aria-hidden="true"></i>
        </button>
    </div>
    <div class="hoja-cuerpo">
        <?php foreach ($grupos as [$titulo, , $opciones]): ?>
            <section class="hoja-grupo" aria-label="<?= esc($titulo, 'attr') ?>">
                <h3><?= esc($titulo) ?></h3>
                <?php foreach ($opciones as $op): ?>
                    <?= $enlace($op, $activa($op[3]), 'hoja-item') ?>
                <?php endforeach; ?>
            </section>
        <?php endforeach; ?>
        <section class="hoja-grupo" aria-label="Tu cuenta">
            <h3>Tu cuenta</h3>
            <?= $enlace(['main_page_4', 'fa-store', 'Ver la tienda'], false, 'hoja-item') ?>
            <?= $enlace(['crea_sugerencia', 'fa-lightbulb', 'Enviar una sugerencia'], false, 'hoja-item') ?>
            <?= $enlace(['logout', 'fa-right-from-bracket', 'Cerrar sesión'], false, 'hoja-item hoja-salir') ?>
        </section>
    </div>
</div>
<?php
})();
