<?php
/**
 * Barra superior común (panel, repartidor y buzón de sugerencias).
 * Estilos: public/estilos/Header.css. El botón ☰ abre el menú lateral en celular (public/js/panel.js).
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
    <?php if ($esBackendHeader): ?>
        <button type="button" class="encabezado-menu" data-abrir-menu aria-label="Abrir el menú" aria-expanded="false" aria-controls="menu-panel">
            <i class="fa-solid fa-bars" aria-hidden="true"></i>
        </button>
    <?php endif; ?>

    <a class="encabezado-marca" href="<?= base_url($inicioHeader) ?>"><i class="fa-solid fa-leaf" aria-hidden="true"></i>Fruver</a>

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
