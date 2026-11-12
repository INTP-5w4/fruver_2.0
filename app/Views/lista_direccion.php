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

<?php
// Si el servidor rechazó el formulario, se reabre el mismo modal con lo que se había escrito
// y el error se muestra dentro de él. El de edición manda un "id" oculto; el de alta no.
$hayOld   = session()->has('_ci_old_input');
$editando = $hayOld && old('id') !== null;
$reabrir  = $hayOld ? ($editando ? 'modalEditarDireccion' : 'modalCrearDireccion') : null;
?>
<div class="lista-wrapper">
 <div class="flash-container">
    <?php if (session()->getFlashdata('error') && $reabrir === null): ?>
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
               (no podrán entrar a una ruta).
               <?php if (! empty($conBusqueda)): ?>
                   Usa el botón <i class="fa-solid fa-location-crosshairs"></i> de cada fila, o ejecuta <code>php spark direcciones:geocodificar</code>.
               <?php else: ?>
                   Usa el botón <i class="fa-solid fa-location-crosshairs"></i> de cada fila y toca el mapa en la entrada de la casa.
               <?php endif; ?></p>
        </div>
    <?php endif; ?>
</div>

<div class="contenedor-boton">
    <h1>Direcciones</h1>
    <button type="button" onclick="abrirModalDireccion('modalCrearDireccion')" class="btn-agregar">
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
                            <?php if (! empty($conBusqueda)): ?>
                                <form method="post" action="<?= base_url('direcciones/geocodificar/' . (int) $d['id']) ?>" style="display:inline; margin:0;">
                                    <?= csrf_field() ?>
                                    <button type="submit" class="btn-icono" title="Ubicar automáticamente a partir del texto" aria-label="Ubicar automáticamente la dirección <?= (int) $d['id'] ?>"><i class="fa-solid fa-location-crosshairs" aria-hidden="true"></i></button>
                                </form>
                            <?php else: ?>
                                <?php // Sin servicio de búsqueda configurado: se abre el mapa para colocar el pin a mano. ?>
                                <button type="button" class="btn-icono" title="Ubicar en el mapa (colocar el pin)" aria-label="Ubicar en el mapa la dirección <?= (int) $d['id'] ?>"
                                        onclick="ubicarEnMapa(<?= (int) $d['id'] ?>)"><i class="fa-solid fa-location-crosshairs" aria-hidden="true"></i></button>
                            <?php endif; ?>
                        <?php endif; ?>
                    </td>
                    <?php endif; ?>
                    <td>
                        <button type="button" class="btn-icono" title="Editar dirección" aria-label="Editar dirección <?= (int) $d['id'] ?>"
                                data-id="<?= (int) $d['id'] ?>"
                                data-colonia="<?= esc((string) $d['colonia'], 'attr') ?>"
                                data-calle="<?= esc((string) $d['calle'], 'attr') ?>"
                                data-numero="<?= esc((string) $d['numero'], 'attr') ?>"
                                data-municipio="<?= esc((string) $d['municipio'], 'attr') ?>"
                                data-estado="<?= esc((string) $d['estado'], 'attr') ?>"
                                data-cliente="<?= (int) $d['id_cliente'] ?>"
                                data-lat="<?= esc((string) ($d['lat'] ?? ''), 'attr') ?>"
                                data-lng="<?= esc((string) ($d['lng'] ?? ''), 'attr') ?>"
                                data-cp="<?= esc((string) ($d['codigo_postal'] ?? ''), 'attr') ?>"
                                data-ref="<?= esc((string) ($d['referencias'] ?? ''), 'attr') ?>"
                                data-principal="<?= ! empty($d['es_principal']) ? '1' : '0' ?>"
                                onclick="editarDireccion(this)">
                            <i class="fa-solid fa-pen-to-square" aria-hidden="true"></i>
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
    <div id="modalCrearDireccion" class="w3-modal" style="display:none;" role="dialog" aria-modal="true" aria-labelledby="titulo-crear-direccion">
        <div class="modal-contenido w3-animate-zoom">

            <header class="modal-header">
                <button type="button" class="modal-cerrar" data-cerrar-modal aria-label="Cerrar">&times;</button>
                <h2 id="titulo-crear-direccion">Registrar dirección</h2>
            </header>

            <form action="<?= base_url('guarda_direccion') ?>" method="post" class="modal-form">
                <?= csrf_field() ?>
                <?php if ($reabrir === 'modalCrearDireccion' && session()->getFlashdata('error')): ?>
                    <div class="aviso aviso-error" role="alert"><i class="fa-solid fa-circle-exclamation" aria-hidden="true"></i><span><?= esc(session()->getFlashdata('error')) ?></span></div>
                <?php endif; ?>

                <?= view('partials/form_direccion', [
                    'clientes' => $clientes_lista,
                    'conGeo'   => ! empty($conGeo),
                    'sufijo'   => '_crear',
                    'conOld'   => $hayOld && ! $editando,
                ]) ?>

                <footer class="modal-footer">
                    <button type="submit" class="btn-guardar">Guardar</button>
                    <button type="button" class="btn-cancelar" data-cerrar-modal>Cancelar</button>
                </footer>
            </form>
        </div>
    </div>

    <!-- MODAL EDITAR DIRECCIÓN -->
    <div id="modalEditarDireccion" class="w3-modal" style="display:none;" role="dialog" aria-modal="true" aria-labelledby="titulo-editar-direccion">
        <div class="modal-contenido w3-animate-zoom">

            <header class="modal-header">
                <button type="button" class="modal-cerrar" data-cerrar-modal aria-label="Cerrar">&times;</button>
                <h2 id="titulo-editar-direccion">Editar dirección</h2>
            </header>

            <form action="<?= base_url('modifica_direccion') ?>" method="post" class="modal-form">
                <?= csrf_field() ?>
                <?php if ($reabrir === 'modalEditarDireccion' && session()->getFlashdata('error')): ?>
                    <div class="aviso aviso-error" role="alert"><i class="fa-solid fa-circle-exclamation" aria-hidden="true"></i><span><?= esc(session()->getFlashdata('error')) ?></span></div>
                <?php endif; ?>

                <input type="hidden" name="id" id="id_editar" value="<?= $editando ? (int) old('id', null, false) : '' ?>">

                <?= view('partials/form_direccion', [
                    'clientes' => $clientes_lista,
                    'conGeo'   => ! empty($conGeo),
                    'sufijo'   => '_editar',
                    'conOld'   => $editando,
                ]) ?>

                <footer class="modal-footer">
                    <button type="submit" class="btn-guardar">Guardar</button>
                    <button type="button" class="btn-cancelar" data-cerrar-modal>Cancelar</button>
                </footer>
            </form>
        </div>
    </div>

<?php include 'Footer.php'; ?>
</div><!-- /.lista-wrapper -->

    <script>
        function abrirModalDireccion(id) {
            document.getElementById(id).style.display = 'block';
        }

        /** Llena el modal de edición con los datos de la fila (botón con data-*) y lo abre. */
        /** Botón ⊕ sin servicio de búsqueda: abre la edición y lleva al mapa para colocar el pin. */
        function ubicarEnMapa(id) {
            var boton = document.querySelector('button[data-id="' + id + '"]');
            if (!boton) { return; }
            editarDireccion(boton);
            var geo = document.getElementById('geo_editar');
            if (!geo) { return; }
            setTimeout(function () {
                geo.scrollIntoView({ behavior: 'smooth', block: 'center' });
                var estado = geo.querySelector('[data-mapa-estado]');
                if (estado) {
                    estado.textContent = 'Toca el mapa en la entrada de la casa (o arrastra el pin) y pulsa Guardar.';
                    estado.hidden = false;
                }
            }, 250);
        }

        function editarDireccion(boton) {
            var d = boton.dataset;
            var poner = function (campo, valor) {
                var el = document.getElementById(campo + '_editar');
                if (!el) { return; }
                el.classList.remove('campo-con-error');
                el.removeAttribute('aria-invalid');
                if (el.type === 'checkbox') { el.checked = valor === '1'; return; }
                if (el.tagName === 'SELECT' && valor !== '' && !Array.prototype.some.call(el.options, function (o) { return o.value === valor; })) {
                    // Estado escrito distinto en una dirección antigua: se agrega para no perderlo.
                    el.add(new Option(valor, valor));
                }
                el.value = valor;
            };
            document.getElementById('id_editar').value = d.id;
            poner('col', d.colonia);
            poner('calle', d.calle);
            poner('num', d.numero);
            poner('mun', d.municipio);
            poner('edo', d.estado);
            poner('id_cliente', d.cliente);
            poner('cp', d.cp);
            poner('referencias', d.ref);
            poner('principal', d.principal);
            abrirModalDireccion('modalEditarDireccion');
            var geo = document.getElementById('geo_editar');
            if (geo && window.FruverGeo) { FruverGeo.posicionar(geo, d.lat, d.lng); }
        }

        document.addEventListener('click', function (ev) {
            var cerrar = ev.target.closest('[data-cerrar-modal]');
            if (cerrar) { cerrar.closest('.w3-modal').style.display = 'none'; return; }
            if (ev.target.classList && ev.target.classList.contains('w3-modal')) { ev.target.style.display = 'none'; }
        });
        document.addEventListener('keydown', function (ev) {
            if (ev.key !== 'Escape') { return; }
            document.querySelectorAll('.w3-modal').forEach(function (m) { m.style.display = 'none'; });
        });

        <?php if ($reabrir !== null): ?>
        abrirModalDireccion('<?= $reabrir ?>');
        <?php endif; ?>
    </script>

</body>
</html>
