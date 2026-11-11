<?php
/**
 * Layout de las páginas de privacidad (avisos, aceptación y preferencias).
 * Reutiliza el estilo de las vistas de acceso (auth.css) y añade privacidad.css.
 * Sin estilos ni scripts en línea.
 *
 * @var string $volver    ruta de inicio del rol
 * @var bool   $sinVolver oculta el enlace de regreso (pantalla de aceptación obligatoria)
 */
$volver    ??= 'main_page_4';
$sinVolver ??= false;
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>FRUVER — <?= $this->renderSection('title') ?></title>
    <link rel="stylesheet" href="<?= base_url('estilos/auth.css') ?>">
    <link rel="stylesheet" href="<?= base_url('estilos/privacidad.css') ?>">
</head>
<body class="fv-auth">

    <header class="fv-auth-header">
        <a href="<?= site_url('main_page_4') ?>" class="fv-auth-logo">FRUVER</a>
        <?php if (! $sinVolver): ?>
            <a href="<?= site_url($volver) ?>" class="fv-auth-volver">&larr; Volver</a>
        <?php endif; ?>
    </header>

    <main class="fv-auth-main pv-main">
        <?php foreach (['mensaje' => 'alert-success', 'error' => 'alert-danger', 'aviso_privacidad' => 'alert-info'] as $clave => $clase): ?>
            <?php if (session()->getFlashdata($clave)): ?>
                <div class="alert <?= $clase ?>" role="status"><?= esc(session()->getFlashdata($clave)) ?></div>
            <?php endif; ?>
        <?php endforeach; ?>

        <?= $this->renderSection('main') ?>
    </main>

    <footer class="fv-auth-footer">
        FRUVER 2.0 ·
        <a href="<?= site_url('aviso-de-privacidad') ?>">Aviso de privacidad</a> ·
        <a href="<?= site_url('aviso-de-privacidad/consentimiento') ?>">Aviso de consentimiento</a>
        <?php if (auth()->loggedIn()): ?>
            · <a href="<?= site_url('privacidad/preferencias') ?>">Mis preferencias de privacidad</a>
        <?php endif; ?>
    </footer>
</body>
</html>
