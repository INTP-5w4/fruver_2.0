<?php
/**
 * Barra superior común (panel, repartidor y buzón de sugerencias).
 * Estilos: public/estilos/Header.css. En celular, el personal navega con la barra inferior
 * (sidebar.php) y el repartidor con la suya (abajo, en este archivo); las acciones de la cuenta
 * pasan a la hoja «Más» para no saturar la barra superior.
 * Las variables llevan el sufijo "Header" porque este archivo se incluye con include y comparte el ámbito de la vista.
 */
$usuarioHeader = auth()->user();
$esBackendHeader = $usuarioHeader !== null && $usuarioHeader->inGroup('superadmin', 'admin', 'developer');
$esRepartidorHeader = $usuarioHeader !== null && $usuarioHeader->inGroup('repartidor');
$inicioHeader = $esBackendHeader ? '/' : ($esRepartidorHeader ? 'repartidor/entregas' : 'main_page_4');
$nombreHeader = $usuarioHeader !== null ? (string) ($usuarioHeader->username ?: $usuarioHeader->email) : '';
$rolHeader = '';
foreach (['superadmin' => 'Superadministración', 'admin' => 'Administración', 'developer' => 'Desarrollo', 'repartidor' => 'Repartidor', 'user' => 'Cliente'] as $grupoHeader => $textoHeader) {
    if ($usuarioHeader !== null && $usuarioHeader->inGroup($grupoHeader)) {
        $rolHeader = $textoHeader;
        break;
    }
}
?>
<header class="encabezado">
    <a class="encabezado-marca" href="<?= base_url($inicioHeader) ?>" aria-label="Fruver, ir al inicio"><i class="fa-solid fa-leaf" aria-hidden="true"></i>Fruver</a>

    <?php if ($usuarioHeader !== null): ?>
        <nav class="encabezado-acciones" aria-label="Tu cuenta">
            <?php if ($esBackendHeader): ?>
                <a href="<?= base_url('main_page_4') ?>" class="encabezado-enlace" title="Ver la tienda como la ven los clientes">
                    <i class="fa-solid fa-store" aria-hidden="true"></i><span>Tienda</span>
                </a>
            <?php endif; ?>
            <?php if ($esRepartidorHeader): ?>
                <a href="<?= base_url('repartidor/mis-datos') ?>" class="encabezado-enlace" title="Tus datos de repartidor y tu vehículo">
                    <i class="fa-solid fa-id-badge" aria-hidden="true"></i><span>Mis datos</span>
                </a>
            <?php endif; ?>
            <a href="<?= base_url('crea_sugerencia') ?>" class="encabezado-enlace" title="Enviar una sugerencia">
                <i class="fa-regular fa-lightbulb" aria-hidden="true"></i><span>Sugerencias</span>
            </a>
            <a href="<?= base_url('privacidad/preferencias') ?>" class="encabezado-enlace" title="Aviso y preferencias de privacidad">
                <i class="fa-solid fa-user-shield" aria-hidden="true"></i><span>Privacidad</span>
            </a>
            <span class="encabezado-persona">
                <span class="encabezado-avatar" aria-hidden="true"><?= esc(mb_strtoupper(mb_substr($nombreHeader, 0, 1))) ?></span>
                <span class="encabezado-persona-texto"><b><?= esc($nombreHeader) ?></b><?php if ($rolHeader !== ''): ?><small><?= esc($rolHeader) ?></small><?php endif; ?></span>
            </span>
            <a href="<?= base_url('logout') ?>" class="encabezado-salir" title="Cerrar sesión">
                <i class="fa-solid fa-right-from-bracket" aria-hidden="true"></i><span>Salir</span>
            </a>
        </nav>
    <?php endif; ?>
</header>
<?php if ($esRepartidorHeader && ! $esBackendHeader): ?>
    <?php
    $rutaHeader = trim(uri_string(), '/');
    $seccionesHeader = [
        ['repartidor/entregas', 'fa-truck', 'Entregas'],
        ['repartidor/ruta', 'fa-route', 'Mi ruta'],
        ['repartidor/mis-datos', 'fa-id-badge', 'Mis datos'],
    ];
    ?>
    <!-- Celular: navegación del repartidor (3 secciones + «Más») -->
    <nav class="nav-inferior" aria-label="Secciones">
        <?php foreach ($seccionesHeader as [$destinoHeader, $iconoHeader, $textoHeader]): ?>
            <?php $actualHeader = $rutaHeader === $destinoHeader || str_starts_with($rutaHeader, $destinoHeader . '/'); ?>
            <a href="<?= base_url($destinoHeader) ?>" class="nav-inferior-item<?= $actualHeader ? ' activo' : '' ?>"<?= $actualHeader ? ' aria-current="page"' : '' ?>>
                <i class="fa-solid <?= esc($iconoHeader, 'attr') ?>" aria-hidden="true"></i><span><?= esc($textoHeader) ?></span>
            </a>
        <?php endforeach; ?>
        <button type="button" class="nav-inferior-item" data-abrir-hoja="hoja-mas" aria-haspopup="dialog" aria-expanded="false" aria-controls="hoja-mas">
            <i class="fa-solid fa-bars" aria-hidden="true"></i><span>Más</span>
        </button>
    </nav>
    <div class="hoja-fondo" data-cerrar-hoja hidden></div>
    <div class="hoja" id="hoja-mas" role="dialog" aria-modal="true" aria-labelledby="hoja-mas-titulo" hidden>
        <div class="hoja-cabeza">
            <h2 id="hoja-mas-titulo">Tu cuenta</h2>
            <button type="button" class="hoja-cerrar" data-cerrar-hoja aria-label="Cerrar"><i class="fa-solid fa-xmark" aria-hidden="true"></i></button>
        </div>
        <div class="hoja-cuerpo">
            <section class="hoja-grupo" aria-label="Tu cuenta">
                <a href="<?= base_url('crea_sugerencia') ?>" class="hoja-item"><i class="fa-regular fa-lightbulb" aria-hidden="true"></i><span>Enviar una sugerencia</span></a>
                <a href="<?= base_url('privacidad/preferencias') ?>" class="hoja-item"><i class="fa-solid fa-user-shield" aria-hidden="true"></i><span>Privacidad</span></a>
                <a href="<?= base_url('logout') ?>" class="hoja-item hoja-salir"><i class="fa-solid fa-right-from-bracket" aria-hidden="true"></i><span>Cerrar sesión</span></a>
            </section>
        </div>
    </div>
<?php endif; ?>
