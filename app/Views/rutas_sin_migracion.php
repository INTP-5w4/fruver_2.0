<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://www.w3schools.com/w3css/5/w3.css">
    <link rel="stylesheet" href="<?= base_url('estilos/estilosPaginas.css') ?>">
    <link rel="stylesheet" href="<?= base_url('estilos/Header.css') ?>">
    <title>Rutas de reparto</title>
</head>
<body>
<?php include 'Header.php'; ?>
<?php include 'sidebar.php'; ?>
<div class="lista-wrapper" style="padding-top:80px;">
    <div class="w3-panel w3-pale-yellow w3-border">
        <h3>Falta preparar la base de datos para las rutas</h3>
        <p>Ejecuta en la carpeta del proyecto:</p>
        <pre>php spark migrate</pre>
        <p>o, si prefieres phpMyAdmin, el script <code>database_cambios/rutas_reparto_cambios.sql</code>.
           Después configura la llave de openrouteservice (ver <code>docs/RUTAS_REPARTO.md</code>).</p>
    </div>
</div>
</body>
</html>
