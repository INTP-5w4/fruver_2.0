<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <?= view('partials/estilos_panel') ?>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?= view('partials/mapa_config', ['almacenMapa' => $almacenMapa]) ?>
    <script src="<?= base_url('js/geo_direccion.js') ?>" defer></script>
    <title>Almacén de salida</title>
</head>
<body>
<?php include 'Header.php'; ?>
<?php include 'sidebar.php'; ?>
<div class="lista-wrapper lista-angosta">
    <?php foreach (['error' => 'w3-red', 'mensaje' => 'w3-green'] as $clave => $clase): ?>
        <?php if (session()->getFlashdata($clave)): ?>
            <div class="w3-panel <?= $clase ?>"><p><?= esc(session()->getFlashdata($clave)) ?></p></div>
        <?php endif; ?>
    <?php endforeach; ?>

    <p><a href="<?= base_url('rutas') ?>">&larr; Volver a rutas</a></p>
    <h2><i class="fa-solid fa-warehouse"></i> Almacén de salida</h2>
    <p>Las rutas salen de aquí y, si así se indica, regresan aquí. Ubica el pin en la entrada por donde salen los repartidores.</p>

    <?php foreach (array_merge($almacenes, [null]) as $a): ?>
        <?php if ($a === null && $almacenes !== []) { ?>
            <details class="rutas-panel w3-margin-bottom"><summary><b>+ Agregar otro punto de salida</b></summary>
        <?php } else { ?>
            <div class="rutas-panel w3-margin-bottom">
        <?php } ?>
            <form method="post" action="<?= base_url('rutas/almacen') ?>" class="modal-form">
                <?= csrf_field() ?>
                <input type="hidden" name="id" value="<?= $a ? (int) $a['id'] : '' ?>">
                <label><b>Nombre</b></label>
                <input type="text" name="nombre" class="modal-input" maxlength="100" required value="<?= esc($a['nombre'] ?? 'Bodega principal', 'attr') ?>">
                <label><b>Dirección (texto)</b></label>
                <input type="text" name="direccion" class="modal-input" maxlength="300" required value="<?= esc($a['direccion'] ?? '', 'attr') ?>">
                <?= view('partials/geo_direccion', ['geo' => $a, 'geoId' => 'geo_almacen_' . ($a['id'] ?? 'nuevo')]) ?>
                <?php if ($a): ?>
                    <label class="geo-check"><input type="checkbox" name="activo" value="0" <?= (int) $a['activo'] === 1 ? '' : 'checked' ?>> Desactivar (no se ofrece al planear)</label>
                <?php endif; ?>
                <button type="submit" class="btn-guardar">Guardar</button>
            </form>
        <?php if ($a === null && $almacenes !== []) { ?></details><?php } else { ?></div><?php } ?>
    <?php endforeach; ?>
</div>
</body>
</html>
