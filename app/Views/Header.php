<!-- Header.php — header común; muestra logout solo a usuarios del backend con sesión activa -->
<?php
$esBackend = auth()->loggedIn()
    && auth()->user()->inGroup('superadmin', 'admin', 'developer');
$conSesionHeader = auth()->loggedIn();
?>
<header>
    <div class="header_container">
        <div class="logo"><a href="<?= base_url('') ?>">FRUVER</a></div>
        <?php if ($conSesionHeader): ?>
            <div class="header-acciones">
                <?php if ($esBackend): ?>
                    <a href="<?= base_url('main_page_4') ?>" class="header-enlace" title="Ver la tienda como la ven los clientes">
                        <i class="fa-solid fa-store"></i> Ir a la tienda
                    </a>
                <?php endif; ?>
                <a href="<?= base_url('crea_sugerencia') ?>" class="header-enlace" title="Enviar una sugerencia">
                    <i class="fa-regular fa-lightbulb"></i> Buzón de sugerencias
                </a>
                <?php if ($esBackend): ?>
                    <a href="<?= base_url('logout') ?>" class="header-logout" title="Cerrar sesión">
                        <i class="fa-solid fa-right-from-bracket"></i> Cerrar sesión
                    </a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</header>
