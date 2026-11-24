<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <?= view('partials/estilos_panel') ?>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rutas de reparto</title>
</head>
<body>
<?php include 'Header.php'; ?>
<?php include 'sidebar.php'; ?>
<div class="lista-wrapper">
    <div class="w3-panel w3-pale-yellow w3-border">
        <?php if (es_tecnico()): ?>
            <h3>Falta preparar la base de datos para las rutas</h3>
            <p>Ejecuta en la carpeta del proyecto:</p>
            <pre>php spark migrate</pre>
            <p>o, si prefieres phpMyAdmin, el script <code>database_cambios/rutas_reparto_cambios.sql</code>.
               Después configura la llave de openrouteservice (ver <code>docs/RUTAS_REPARTO.md</code>).</p>
        <?php else: ?>
            <h3>Las rutas de reparto aún no están activadas</h3>
            <p>Pide al administrador del sistema que las active. Mientras tanto puedes seguir asignando entregas desde <b>Entregas</b>.</p>
        <?php endif; ?>
    </div>
</div>
</body>
</html>
