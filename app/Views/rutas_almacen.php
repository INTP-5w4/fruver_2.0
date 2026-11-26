<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <?= view('partials/estilos_panel') ?>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?= view('partials/mapa_config', ['almacenMapa' => $almacenMapa]) ?>
    <script src="<?= base_url('js/geo_direccion.js') ?>" defer></script>
    <title>Almacén de salida</title>
    <style>
        .almacen-acciones { margin: 6px 0 16px; }
        .almacen-lista { display: flex; flex-direction: column; gap: 10px; }
        .almacen-tarjeta { border: 1px solid var(--linea); border-radius: 12px; background: var(--superficie); overflow: hidden; }
        .almacen-tarjeta > summary {
            list-style: none; cursor: pointer; display: flex; align-items: center; gap: 12px; padding: 14px 16px;
        }
        .almacen-tarjeta > summary::-webkit-details-marker { display: none; }
        .almacen-tarjeta > summary:hover { background: var(--superficie-2); }
        .almacen-sumario { display: flex; flex-direction: column; min-width: 0; flex: 1 1 auto; }
        .almacen-nombre { font-weight: 700; color: var(--tinta); }
        .almacen-dir { color: var(--tinta-suave); font-size: .88rem; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .almacen-chip { font-size: .68rem; font-weight: 700; color: #8a5a00; background: #fff3d6; border-radius: 8px; padding: 1px 7px; margin-left: 6px; vertical-align: middle; }
        .almacen-flecha { color: #8a938c; transition: transform .2s ease; flex: 0 0 auto; }
        .almacen-tarjeta[open] > summary .almacen-flecha { transform: rotate(180deg); }
        .almacen-cuerpo { padding: 4px 16px 16px; }
        .almacen-nuevo { margin-bottom: 16px; }
        .almacen-nuevo-cabeza { display: flex; align-items: center; justify-content: space-between; margin-bottom: 4px; }
        .almacen-nuevo-cabeza h3 { margin: 0; font-size: 1.05rem; }
        .almacen-cerrar { border: 0; background: transparent; color: #8a938c; cursor: pointer; font-size: 1rem; border-radius: 50%; width: 32px; height: 32px; }
        .almacen-cerrar:hover { background: var(--superficie-2); color: var(--tinta); }
    </style>
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

    <div class="almacen-acciones">
        <button type="button" class="btn-agregar" data-nuevo-almacen>
            <i class="fa-solid fa-plus" aria-hidden="true"></i> Agregar punto de salida
        </button>
    </div>

    <!-- Formulario para agregar (oculto; lo abre el botón de arriba) -->
    <div class="rutas-panel almacen-nuevo" id="almacenNuevo" hidden>
        <div class="almacen-nuevo-cabeza">
            <h3><i class="fa-solid fa-warehouse" aria-hidden="true"></i> Nuevo punto de salida</h3>
            <button type="button" class="almacen-cerrar" data-cerrar-nuevo aria-label="Cerrar"><i class="fa-solid fa-xmark" aria-hidden="true"></i></button>
        </div>
        <form method="post" action="<?= base_url('rutas/almacen') ?>" class="modal-form">
            <?= csrf_field() ?>
            <input type="hidden" name="id" value="">
            <label for="alm-nombre-nuevo"><b>Nombre</b></label>
            <input id="alm-nombre-nuevo" type="text" name="nombre" class="modal-input" maxlength="100" required value="Bodega principal">
            <label for="alm-direccion-nuevo"><b>Dirección (texto)</b></label>
            <input id="alm-direccion-nuevo" type="text" name="direccion" class="modal-input" maxlength="300" required value="">
            <?= view('partials/geo_direccion', ['geo' => null, 'geoId' => 'geo_almacen_nuevo']) ?>
            <button type="submit" class="btn-guardar"><i class="fa-solid fa-floppy-disk" aria-hidden="true"></i> Guardar</button>
        </form>
    </div>

    <!-- Puntos de salida existentes: tarjetas colapsadas (nombre + dirección) -->
    <?php if ($almacenes === []): ?>
        <p class="w3-text-grey">Aún no hay puntos de salida. Agrega el primero con el botón de arriba.</p>
    <?php else: ?>
        <div class="almacen-lista">
            <?php foreach ($almacenes as $a): ?>
                <?php $inactivo = (int) $a['activo'] !== 1; ?>
                <details class="almacen-tarjeta">
                    <summary>
                        <span class="almacen-sumario">
                            <span class="almacen-nombre">
                                <?= esc($a['nombre']) ?>
                                <?php if ($inactivo): ?><span class="almacen-chip">Inactivo</span><?php endif; ?>
                            </span>
                            <span class="almacen-dir"><?= esc($a['direccion']) ?></span>
                        </span>
                        <i class="fa-solid fa-chevron-down almacen-flecha" aria-hidden="true"></i>
                    </summary>
                    <div class="almacen-cuerpo">
                        <form method="post" action="<?= base_url('rutas/almacen') ?>" class="modal-form">
                            <?= csrf_field() ?>
                            <input type="hidden" name="id" value="<?= (int) $a['id'] ?>">
                            <label for="alm-nombre-<?= (int) $a['id'] ?>"><b>Nombre</b></label>
                            <input id="alm-nombre-<?= (int) $a['id'] ?>" type="text" name="nombre" class="modal-input" maxlength="100" required value="<?= esc($a['nombre'], 'attr') ?>">
                            <label for="alm-direccion-<?= (int) $a['id'] ?>"><b>Dirección (texto)</b></label>
                            <input id="alm-direccion-<?= (int) $a['id'] ?>" type="text" name="direccion" class="modal-input" maxlength="300" required value="<?= esc($a['direccion'], 'attr') ?>">
                            <?= view('partials/geo_direccion', ['geo' => $a, 'geoId' => 'geo_almacen_' . $a['id']]) ?>
                            <label class="geo-check"><input type="checkbox" name="activo" value="0" <?= $inactivo ? 'checked' : '' ?>> Desactivar (no se ofrece al planear)</label>
                            <button type="submit" class="btn-guardar"><i class="fa-solid fa-floppy-disk" aria-hidden="true"></i> Guardar</button>
                        </form>
                    </div>
                </details>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<script>
    document.addEventListener('click', function (e) {
        // Abrir el formulario de alta.
        if (e.target.closest('[data-nuevo-almacen]')) {
            var nuevo = document.getElementById('almacenNuevo');
            if (nuevo) {
                nuevo.hidden = false;
                nuevo.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                var n = document.getElementById('alm-nombre-nuevo');
                if (n) { n.focus(); n.select(); }
            }
            return;
        }
        // Cerrar el formulario de alta.
        if (e.target.closest('[data-cerrar-nuevo]')) {
            var caja = document.getElementById('almacenNuevo');
            if (caja) { caja.hidden = true; }
        }
    });
</script>
</body>
</html>
