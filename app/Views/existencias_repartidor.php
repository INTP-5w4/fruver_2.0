<?php
/**
 * Existencias para el repartidor (solo lectura).
 * Muestra únicamente producto, existencia y unidad de venta.
 *
 * @var list<array<string,mixed>> $existencias  (de Modelo_existencia::listarFiltrado)
 */
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <?= view('partials/estilos_panel') ?>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Existencias · Fruver</title>
</head>
<body>
    <?php include 'Header.php'; ?>

<div class="lista-wrapper">
    <div class="contenedor-boton">
        <h1>Existencias</h1>
    </div>

    <div class="tabla-wrapper">
        <table>
            <thead>
                <tr>
                    <th>Producto</th>
                    <th>Existencia</th>
                    <th>Unidad</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($existencias)): ?>
                    <tr><td colspan="3">No hay existencias registradas.</td></tr>
                <?php endif; ?>
                <?php foreach ($existencias as $e): ?>
                    <tr>
                        <td><?= esc($e['nombre_producto'] ?? '—') ?></td>
                        <td><?= esc($e['e_total']) ?></td>
                        <td><?= esc($e['u_venta'] ?? '—') ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
</body>
</html>