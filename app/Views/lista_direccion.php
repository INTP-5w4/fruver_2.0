<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <?= view('partials/estilos_panel') ?>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?= view('partials/mapa_config') ?>
    <script src="<?= base_url('js/geo_direccion.js') ?>" defer></script>
    <title>Lista Direcciones</title>
</head>
<body>
        <?php include 'Header.php'; ?>
    <?php include 'sidebar.php'; ?>

<div class="lista-wrapper">
 <div class="flash-container">
    <?php if (session()->getFlashdata('error')): ?>
        <div class="w3-panel w3-red w3-animate-opacity">
            <p><?= esc(session()->getFlashdata('error')) ?></p>
        </div>
    <?php endif; ?>

    <?php if (session()->getFlashdata('mensaje')): ?>
        <div class="w3-panel w3-green w3-animate-opacity">
            <p><?= esc(session()->getFlashdata('mensaje')) ?></p>
        </div>
    <?php endif; ?>

    <?php if (session()->getFlashdata('aviso')): ?>
        <div class="w3-panel w3-pale-yellow w3-border w3-animate-opacity">
            <p><?= esc(session()->getFlashdata('aviso')) ?></p>
        </div>
    <?php endif; ?>

    <?php if (! empty($conGeo) && $resumenGeo['total'] > $resumenGeo['ubicadas']): ?>
        <div class="w3-panel w3-pale-yellow w3-border">
            <p><b><?= (int) ($resumenGeo['total'] - $resumenGeo['ubicadas']) ?></b> de <?= (int) $resumenGeo['total'] ?> direcciones aún no están ubicadas en el mapa
               (no podrán entrar a una ruta). Usa el botón <i class="fa-solid fa-location-crosshairs"></i> de cada fila,
               o ejecuta <code>php spark direcciones:geocodificar</code>.</p>
        </div>
    <?php endif; ?>
</div>

<div class="contenedor-boton">
    <h1>Direcciones</h1>
    <button type="button" onclick="document.getElementById('modalCrearDireccion').style.display='block'" class="btn-agregar">
        <i class="fa-solid fa-plus" aria-hidden="true"></i> Nueva dirección
    </button>
</div>

<form method="get" action="<?= base_url('lista_direccion') ?>">
    <div>
        <input type="text" name="buscar" placeholder="Buscar por cliente, colonia, calle, municipio o estado…"
               value="<?= esc($buscar ?? '', 'attr') ?>" class="w3-input w3-border" aria-label="Buscar dirección">
        <button type="submit" class="w3-button w3-green"><i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i> Buscar</button>
        <?php if (!empty($buscar)): ?>
            <a href="<?= base_url('lista_direccion') ?>" class="w3-button w3-border">Limpiar búsqueda</a>
        <?php endif; ?>
    </div>
</form>
<div class="tabla-wrapper">
    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Colonia</th>
                <th>Calle</th>
                <th>Numero</th>
                <th>Municipio</th>
                <th>Estado</th>
                <th>Nombre Cliente</th>
                <?php if (! empty($conGeo)): ?><th>Mapa</th><?php endif; ?>
                <th>Editar</th>
                <th>Eliminar</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($direcciones as $d): ?>
                <tr>
                    <td><?= esc($d['id']) ?></td>
                    <td><?= esc($d['colonia']) ?></td>
                    <td><?= esc($d['calle']) ?></td>
                    <td><?= esc($d['numero']) ?></td>
                    <td><?= esc($d['municipio']) ?></td>
                    <td><?= esc($d['estado']) ?></td>
                    <td>
                        <?= esc($d['nombre'].' '.$d['ape_pat'].' '.$d['ape_mat']) ?>
                        <?php if (! empty($d['es_principal'])): ?><span class="geo-tag geo-tag--ok" title="Dirección principal">★</span><?php endif; ?>
                    </td>
                    <?php if (! empty($conGeo)): ?>
                    <td>
                        <?php if ($d['lat'] === null): ?>
                            <span class="geo-tag geo-tag--no">Sin ubicar</span>
                        <?php elseif (($d['precision_geo'] ?? '') === 'APPROXIMATE'): ?>
                            <span class="geo-tag geo-tag--aprox" title="Se ubicó solo de forma aproximada (calle o colonia): acomoda el pin">Aproximada</span>
                        <?php else: ?>
                            <span class="geo-tag geo-tag--ok" title="<?= esc((string) $d['origen_coord'], 'attr') ?>">Ubicada</span>
                        <?php endif; ?>
                        <?php if (! in_array($d['origen_coord'] ?? null, ['manual', 'gps_entrega'], true)): ?>
                            <form method="post" action="<?= base_url('direcciones/geocodificar/' . (int) $d['id']) ?>" style="display:inline; margin:0;">
                                <?= csrf_field() ?>
                                <button type="submit" class="btn-icono" title="Ubicar automáticamente a partir del texto"><i class="fa-solid fa-location-crosshairs"></i></button>
                            </form>
                        <?php endif; ?>
                    </td>
                    <?php endif; ?>
                    <td>
                        <button data-lat="<?= esc((string) ($d['lat'] ?? ''), 'attr') ?>"
                                data-lng="<?= esc((string) ($d['lng'] ?? ''), 'attr') ?>"
                                data-cp="<?= esc((string) ($d['codigo_postal'] ?? ''), 'attr') ?>"
                                data-ref="<?= esc((string) ($d['referencias'] ?? ''), 'attr') ?>"
                                data-principal="<?= ! empty($d['es_principal']) ? '1' : '0' ?>"
                                onclick="prepararGeoEdicion(this); abrirModal(
                                    '<?= esc($d['id'], 'js') ?>',
                                    '<?= esc($d['colonia'], 'js') ?>',
                                    '<?= esc($d['calle'], 'js') ?>',
                                    '<?= esc($d['numero'], 'js') ?>',
                                    '<?= esc($d['municipio'], 'js') ?>',
                                    '<?= esc($d['estado'], 'js') ?>',
                                    '<?= esc($d['id_cliente'], 'js') ?>'
                                )"
                                class="btn-icono">
                            <i class="fa-solid fa-pen-to-square"></i>
                        </button>
                    </td>
                    <td>
                        <form method="post" action="<?= base_url('borra_id_direccion/' . (int) $d['id']) ?>" class="form-borrar" style="display:inline; margin:0;" onsubmit="return confirm('¿Eliminar este registro?');">
<?= csrf_field() ?>
<button type="submit" class="btn-icono"><i class="fa-solid fa-trash-can"></i></button>
</form>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?= $pager->links('default', 'w3_pager') ?>
</div>
    <!-- MODAL CREAR DIRECCIÓN -->
    <div id="modalCrearDireccion" class="w3-modal" style="display:none;">
        <div class="modal-contenido w3-animate-zoom">

            <header class="modal-header">
                <span onclick="document.getElementById('modalCrearDireccion').style.display='none'"
                      class="modal-cerrar">&times;</span>
                <h2>Registrar Dirección</h2>
            </header>

            <form action="<?= base_url('guarda_direccion') ?>" method="post" class="modal-form">
<?= csrf_field() ?>

                <label><b>Colonia*</b></label>
                <input type="text" placeholder="Ej: Centro" name="col" class="modal-input" required>

                <label><b>Calle*</b></label>
                <input type="text" placeholder="Ej: Benito Juárez" name="calle" class="modal-input" required>

                <label><b>Número*</b></label>
                <input type="text" placeholder="Ej: 59" name="num" class="modal-input" required>

                <label><b>Municipio*</b></label>
                <input type="text" placeholder="Ej: Rafael Lara Grajales" name="mun" class="modal-input" required>

                <label><b>Estado*</b></label>
                <select name="edo" class="modal-input" required>
                    <?php
                    $estados = ["Aguascalientes","Baja California","Baja California Sur","Campeche","Chiapas","Chihuahua","Ciudad de Mexico","Coahuila","Colima","Durango","Estado de Mexico","Guanajuato","Guerrero","Hidalgo","Jalisco","Michoacán","Morelos","Nayarit","Nuevo Leon","Oaxaca","Puebla","Queretaro","Quintana Roo","San Luis Potosi","Sinaloa","Sonora","Tabasco","Tamaulipas","Tlaxcala","Veracruz","Yucatan","Zacatecas"];
                    foreach ($estados as $e): ?>
                        <option value="<?= esc($e) ?>"><?= esc($e) ?></option>
                    <?php endforeach; ?>
                </select>

                <?php if (! empty($conGeo)): ?>
                    <?= view('partials/campos_extra_direccion', ['idSufijo' => '_crear']) ?>
                    <?= view('partials/geo_direccion', ['geoId' => 'geo_crear']) ?>
                <?php endif; ?>

                <label><b>Cliente*</b></label>
                <select name="id_cliente" class="modal-input" required>
                    <?php foreach ($clientes as $cliente): ?>
                        <option value="<?= esc($cliente['id']) ?>">
                            <?= esc($cliente['nombre'].' '.$cliente['ape_pat'].' '.$cliente['ape_mat']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <footer class="modal-footer">
                    <button type="submit" class="btn-guardar">Guardar</button>
                    <button type="button"
                            onclick="document.getElementById('modalCrearDireccion').style.display='none'"
                            class="btn-cancelar">Cancelar</button>
                </footer>

            </form>
        </div>
    </div>

    <!-- MODAL EDITAR DIRECCIÓN -->
    <div id="modalEditarDireccion" class="w3-modal" style="display:none;">
        <div class="modal-contenido w3-animate-zoom">

            <header class="modal-header">
                <span onclick="document.getElementById('modalEditarDireccion').style.display='none'"
                      class="modal-cerrar">&times;</span>
                <h2>Editar Dirección</h2>
            </header>

            <form action="<?= base_url('modifica_direccion') ?>" method="post" class="modal-form">
<?= csrf_field() ?>

                <input type="hidden" name="id" id="edit_id">

                <label><b>Colonia*</b></label>
                <input type="text" name="col" id="edit_col" class="modal-input" required>

                <label><b>Calle*</b></label>
                <input type="text" name="calle" id="edit_calle" class="modal-input" required>

                <label><b>Número*</b></label>
                <input type="text" name="num" id="edit_num" class="modal-input" required>

                <label><b>Municipio*</b></label>
                <input type="text" name="mun" id="edit_mun" class="modal-input" required>

                <label><b>Estado*</b></label>
                <select name="edo" id="edit_edo" class="modal-input" required>
                    <?php foreach ($estados as $e): ?>
                        <option value="<?= esc($e) ?>"><?= esc($e) ?></option>
                    <?php endforeach; ?>
                </select>

                <?php if (! empty($conGeo)): ?>
                    <?= view('partials/campos_extra_direccion', ['idSufijo' => '_editar']) ?>
                    <?= view('partials/geo_direccion', ['geoId' => 'geo_editar']) ?>
                <?php endif; ?>

                <label><b>Cliente*</b></label>
                <select name="id_cliente" id="edit_id_cliente" class="modal-input" required>
                    <?php foreach ($clientes as $cliente): ?>
                        <option value="<?= esc($cliente['id']) ?>">
                            <?= esc($cliente['nombre'].' '.$cliente['ape_pat'].' '.$cliente['ape_mat']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <footer class="modal-footer">
                    <button type="submit" class="btn-guardar">Guardar</button>
                    <button type="button"
                            onclick="document.getElementById('modalEditarDireccion').style.display='none'"
                            class="btn-cancelar">Cancelar</button>
                </footer>

            </form>
        </div>
    </div>

<?php include 'Footer.php'; ?>
</div><!-- /.lista-wrapper -->

    <script>
        function prepararGeoEdicion(boton) {
            var cp = document.getElementById('cp_editar');
            if (!cp) { return; }
            cp.value = boton.dataset.cp || '';
            document.getElementById('referencias_editar').value = boton.dataset.ref || '';
            document.getElementById('principal_editar').checked = boton.dataset.principal === '1';
            if (window.FruverGeo) { FruverGeo.posicionar(document.getElementById('geo_editar'), boton.dataset.lat, boton.dataset.lng); }
        }

        function abrirModal(id, colonia, calle, numero, municipio, estado, id_cliente) {
            document.getElementById('edit_id').value = id;
            document.getElementById('edit_col').value = colonia;
            document.getElementById('edit_calle').value = calle;
            document.getElementById('edit_num').value = numero;
            document.getElementById('edit_mun').value = municipio;
            document.getElementById('edit_edo').value = estado;
            document.getElementById('edit_id_cliente').value = id_cliente;
            document.getElementById('modalEditarDireccion').style.display = 'block';
        }

        window.onclick = function(event) {
            const modalEditar = document.getElementById('modalEditarDireccion');
            const modalCrear = document.getElementById('modalCrearDireccion');
            if (event.target === modalEditar) modalEditar.style.display = 'none';
            if (event.target === modalCrear) modalCrear.style.display = 'none';
        };
    </script>

</body>
</html>