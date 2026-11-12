<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <?= view('partials/estilos_panel') ?>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Repartidores</title>
</head>
<body>
    <?php include 'Header.php'; ?>
    <?php include 'sidebar.php'; ?>
<?php
// Si el servidor rechazó el formulario, se reabre el mismo modal con lo que se había escrito
// y el error se muestra dentro de él. El de edición manda un "id" oculto; el de alta no.
$hayOld   = session()->has('_ci_old_input');
$editando = $hayOld && old('id') !== null;
$reabrir  = $hayOld ? ($editando ? 'modalEditarRepartidor' : 'modalCrearRepartidor') : null;
$conCuenta ??= false;
$nuevos    ??= 0;
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
</div>

<div class="contenedor-boton">
    <h1>Repartidores</h1>
    <button type="button" onclick="abrirModalRepartidor('modalCrearRepartidor')" class="btn-agregar">
        <i class="fa-solid fa-plus" aria-hidden="true"></i> Nuevo repartidor
    </button>
</div>

<?php if ($nuevos > 0): ?>
    <div class="aviso aviso-info aviso-lista" role="status">
        <i class="fa-solid fa-user-plus" aria-hidden="true"></i>
        <span><?= $nuevos === 1 ? 'Se dio de alta 1 repartidor' : 'Se dieron de alta ' . (int) $nuevos . ' repartidores' ?>
            a partir de cuentas del grupo "repartidor". Completa sus datos con <i class="fa-solid fa-pen-to-square" aria-hidden="true"></i>.</span>
    </div>
<?php endif; ?>

<form method="get" action="<?= base_url('lista_repartidor') ?>">
    <div>
        <input type="text" name="buscar" value="<?= esc($buscar ?? '', 'attr') ?>"
               placeholder="Buscar por id, nombre, teléfono o cuenta…" class="w3-input w3-border" aria-label="Buscar repartidor">
        <button type="submit" class="w3-button w3-green"><i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i> Buscar</button>
        <?php if (! empty($buscar)): ?>
            <a href="<?= base_url('lista_repartidor') ?>" class="w3-button w3-border">Limpiar búsqueda</a>
        <?php endif; ?>
    </div>
</form>

<div class="tabla-wrapper">
    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Nombre</th>
                <th>Teléfono</th>
                <th>Dirección</th>
                <th>Vehículo</th>
                <?php if ($conCuenta): ?><th>Cuenta de acceso</th><?php endif; ?>
                <th>Notas</th>
                <th class="col-acciones">Acciones</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($repartidores as $r): ?>
                <?php $incompleto = \App\Models\Modelo_repartidor::faltanDatos($r); ?>
                <tr>
                    <td><?= (int) $r['id'] ?></td>
                    <td>
                        <?= esc(trim($r['nombre'] . ' ' . $r['ape_pat'] . ' ' . ($r['ape_mat'] ?? ''))) ?>
                        <?php if ($incompleto): ?><span class="est est-pendiente" title="Alta automática: completa teléfono y dirección">Faltan datos</span><?php endif; ?>
                    </td>
                    <td><?= esc($r['telefono'] !== '' ? $r['telefono'] : '—') ?></td>
                    <td class="celda-texto"><?= esc($r['direccion'] !== '' ? $r['direccion'] : '—') ?></td>
                    <td><?= esc(\App\Models\Modelo_repartidor::VEHICULOS[$r['vehiculo'] ?? 'moto'] ?? '—') ?></td>
                    <?php if ($conCuenta): ?>
                        <td>
                            <?php if (! empty($r['cuenta'])): ?>
                                <span class="est est-entregado" title="Ve y actualiza sus entregas con esta cuenta"><?= esc($r['cuenta']) ?></span>
                            <?php else: ?>
                                <span class="est est-cancelada" title="Sin cuenta no puede ver sus entregas. Ponle una cuenta en el grupo repartidor.">Sin cuenta</span>
                            <?php endif; ?>
                        </td>
                    <?php endif; ?>
                    <td class="celda-texto"><?= esc($r['notas'] ?? '') ?></td>
                    <td class="col-acciones">
                        <div class="acciones-fila">
                            <button type="button" class="btn-icono" title="Editar repartidor" aria-label="Editar repartidor <?= (int) $r['id'] ?>"
                                    data-id="<?= (int) $r['id'] ?>"
                                    data-nom="<?= esc((string) $r['nombre'], 'attr') ?>"
                                    data-ape_pat="<?= esc((string) $r['ape_pat'], 'attr') ?>"
                                    data-ape_mat="<?= esc((string) ($r['ape_mat'] ?? ''), 'attr') ?>"
                                    data-tel="<?= esc((string) $r['telefono'], 'attr') ?>"
                                    data-dir="<?= esc((string) $r['direccion'], 'attr') ?>"
                                    data-not="<?= esc((string) ($r['notas'] ?? ''), 'attr') ?>"
                                    data-vehiculo="<?= esc((string) ($r['vehiculo'] ?? 'moto'), 'attr') ?>"
                                    onclick="editarRepartidor(this)">
                                <i class="fa-solid fa-pen-to-square" aria-hidden="true"></i>
                            </button>
                            <form method="post" action="<?= base_url('borra_id_repartidor/' . (int) $r['id']) ?>" class="form-borrar" onsubmit="return confirm('¿Eliminar este repartidor?');">
                                <?= csrf_field() ?>
                                <button type="submit" class="btn-icono peligro" title="Eliminar repartidor" aria-label="Eliminar repartidor <?= (int) $r['id'] ?>"><i class="fa-solid fa-trash-can" aria-hidden="true"></i></button>
                            </form>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?= $pager->links('default', 'w3_pager') ?>
</div>

    <!-- MODAL CREAR REPARTIDOR -->
    <div id="modalCrearRepartidor" class="w3-modal" style="display:none;" role="dialog" aria-modal="true" aria-labelledby="titulo-crear-repartidor">
        <div class="modal-contenido w3-animate-zoom">
            <header class="modal-header">
                <button type="button" class="modal-cerrar" data-cerrar-modal aria-label="Cerrar">&times;</button>
                <h2 id="titulo-crear-repartidor">Registrar repartidor</h2>
            </header>

            <form action="<?= base_url('guarda_repartidor') ?>" method="post" class="modal-form">
                <?= csrf_field() ?>
                <?php if ($reabrir === 'modalCrearRepartidor' && session()->getFlashdata('error')): ?>
                    <div class="aviso aviso-error" role="alert"><i class="fa-solid fa-circle-exclamation" aria-hidden="true"></i><span><?= esc(session()->getFlashdata('error')) ?></span></div>
                <?php endif; ?>

                <?= view('partials/form_repartidor', ['sufijo' => '_crear', 'conOld' => $hayOld && ! $editando]) ?>

                <footer class="modal-footer">
                    <button type="submit" class="btn-guardar">Guardar</button>
                    <button type="button" class="btn-cancelar" data-cerrar-modal>Cancelar</button>
                </footer>
            </form>
        </div>
    </div>

    <!-- MODAL EDITAR REPARTIDOR -->
    <div id="modalEditarRepartidor" class="w3-modal" style="display:none;" role="dialog" aria-modal="true" aria-labelledby="titulo-editar-repartidor">
        <div class="modal-contenido w3-animate-zoom">
            <header class="modal-header">
                <button type="button" class="modal-cerrar" data-cerrar-modal aria-label="Cerrar">&times;</button>
                <h2 id="titulo-editar-repartidor">Editar repartidor</h2>
            </header>

            <form action="<?= base_url('modifica_repartidor') ?>" method="post" class="modal-form">
                <?= csrf_field() ?>
                <?php if ($reabrir === 'modalEditarRepartidor' && session()->getFlashdata('error')): ?>
                    <div class="aviso aviso-error" role="alert"><i class="fa-solid fa-circle-exclamation" aria-hidden="true"></i><span><?= esc(session()->getFlashdata('error')) ?></span></div>
                <?php endif; ?>

                <input type="hidden" name="id" id="id_editar" value="<?= $editando ? (int) old('id', null, false) : '' ?>">

                <?= view('partials/form_repartidor', ['sufijo' => '_editar', 'conOld' => $editando]) ?>

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
        function abrirModalRepartidor(id) {
            document.getElementById(id).style.display = 'block';
        }

        /** Llena el modal de edición con los datos de la fila (botón con data-*) y lo abre. */
        function editarRepartidor(boton) {
            document.getElementById('id_editar').value = boton.dataset.id;
            ['nom', 'ape_pat', 'ape_mat', 'tel', 'dir', 'not', 'vehiculo'].forEach(function (campo) {
                var el = document.getElementById(campo + '_editar');
                el.value = boton.dataset[campo] || (campo === 'vehiculo' ? 'moto' : '');
                el.classList.remove('campo-con-error');
                el.removeAttribute('aria-invalid');
            });
            abrirModalRepartidor('modalEditarRepartidor');
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
        abrirModalRepartidor('<?= $reabrir ?>');
        <?php endif; ?>
    </script>

</body>
</html>
