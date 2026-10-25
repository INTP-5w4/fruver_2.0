<!DOCTYPE html>
<html lang="en">
<head>
    <link rel="stylesheet" href="<?= base_url('estilos/Header.css') ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/7.0.1/css/all.min.css" integrity="sha512-2SwdPD6INVrV/lHTZbO2nodKhrnDdJK9/kg2XD1r9uGqPo1cUbujc+IYdlYdEErWNu69gVcYgdxlmVmzTWnetw==" crossorigin="anonymous" referrerpolicy="no-referrer" />    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://www.w3schools.com/w3css/5/w3.css">
    <link rel="stylesheet" href="<?= base_url('estilos/estilosPaginas.css') ?>">
    <link rel="stylesheet" href="<?= base_url('estilos/cliente_preferencial.css') ?>">
    <title>Lista Clientes</title>
</head>
<body>
    <?php include 'Header.php'; ?>
    <?php include 'sidebar.php'; ?>

<div class="lista-wrapper" style="padding-top: 55px;">
<div class="flash-container">
    <?php if (session()->getFlashdata('error')): ?>
        <div class="w3-panel w3-red w3-animate-opacity">
            <p><?= esc(session()->getFlashdata('error')) ?></p>
        </div>
    <?php endif; ?>
        <?php if (session()->getFlashdata('error2')): ?>
        <div class="w3-panel w3-red w3-animate-opacity">
            <p><?= esc(session()->getFlashdata('error2')) ?></p>
        </div>
    <?php endif; ?>

    <?php if (session()->getFlashdata('error_pref')): ?>
        <div class="w3-panel w3-red w3-animate-opacity">
            <p><?= esc(session()->getFlashdata('error_pref')) ?></p>
        </div>
    <?php endif; ?>
    <?php if (! $conPreferencial): ?>
        <div class="w3-panel w3-pale-yellow w3-border">
            <p><b>Falta ejecutar la migración</b> para poder cambiar el estado preferencial de un cliente
               (<code>php spark migrate</code> o <code>database_cambios/preferencial_cambios.sql</code>).
               Mientras tanto se decide solo por compras.</p>
        </div>
    <?php endif; ?>

    <?php if (session()->getFlashdata('mensaje')): ?>
        <div class="w3-panel w3-green w3-animate-opacity">
            <p><?= esc(session()->getFlashdata('mensaje')) ?></p>
        </div>
    <?php endif; ?>
</div>

<div class="contenedor-boton" style="padding-top: 40px;">
    <button onclick="document.getElementById('modalCrearCliente').style.display='block'"
            class="btn-agregar">
        + Nuevo Cliente
    </button>
</div>

<form method="get" action="<?= base_url('lista_cliente') ?>" class="w3-margin-bottom">
    <div style="display:flex; gap:8px;">
        <input
            type="text"
            name="buscar"
            value="<?= esc($buscar ?? '') ?>"
            placeholder="Buscar por id..."
            class="w3-input w3-border"
            style="max-width:350px;"
        >
        <button type="submit" class="w3-button w3-green">
            <i class="fa-solid fa-magnifying-glass"></i> Buscar
        </button>
        <?php if (!empty($buscar)): ?>
            <a href="<?= base_url('lista_cliente') ?>" class="w3-button w3-red">✕ Limpiar</a>
        <?php endif; ?>
    </div>
</form>

<div class="tabla-wrapper">
    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Nombre</th>
                <th>Apellido Paterno</th>
                <th>Apellido Materno</th>
                <th>Telefono</th>
                <th>Compras</th>
                <th>Preferencial</th>
                <th>Editar</th>
                <th>Eliminar</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($clientes as $c):?>
                <tr>
                    <td><?= esc($c['id'])?></td>
                    <td><?= esc($c['nombre']) ?></td>
                    <td><?= esc($c['ape_pat']) ?></td>
                    <td><?= esc($c['ape_mat']) ?></td>
                    <td><?= esc($c['telefono']) ?></td>
                    <td><?= (int) $c['compras'] ?></td>
                    <td class="pref-celda">
                        <?php $manual = in_array($c['preferencial_modo'], ['si', 'no'], true); ?>
                        <?php if ($c['es_preferencial']): ?>
                            <span class="w3-tag w3-round w3-amber"><i class="fa-solid fa-star"></i> Preferencial</span>
                        <?php else: ?>
                            <span class="w3-tag w3-round w3-light-grey">No preferencial</span>
                        <?php endif; ?>
                        <small class="pref-origen"><?= $manual ? 'manual' : 'por compras' ?></small>
                        <?php if ($manual && ! empty($c['preferencial_motivo'])): ?>
                            <small class="pref-motivo" title="<?= esc($c['preferencial_motivo'], 'attr') ?>">
                                “<?= esc($c['preferencial_motivo']) ?>”
                            </small>
                        <?php endif; ?>
                        <button type="button" class="btn-pref" <?= $conPreferencial ? '' : 'disabled' ?>
                                title="Cambiar el estado preferencial"
                                data-id="<?= (int) $c['id'] ?>"
                                data-nombre="<?= esc(trim($c['nombre'] . ' ' . $c['ape_pat'] . ' ' . $c['ape_mat']), 'attr') ?>"
                                data-modo="<?= esc($c['preferencial_modo'], 'attr') ?>"
                                data-motivo="<?= esc((string) ($c['preferencial_motivo'] ?? ''), 'attr') ?>"
                                data-por="<?= esc((string) ($c['preferencial_por'] ?? ''), 'attr') ?>"
                                data-fecha="<?= esc((string) ($c['preferencial_fecha'] ?? ''), 'attr') ?>"
                                data-compras="<?= (int) $c['compras'] ?>">
                            <i class="fa-solid fa-star"></i> Cambiar
                        </button>
                    </td>
                    <td>
                        <!-- ↓ Botón que abre el modal y llena los datos -->
                        <button onclick="abrirModal(
                                    '<?= esc($c['id'], 'js') ?>',
                                    '<?= esc($c['nombre'], 'js') ?>',
                                    '<?= esc($c['ape_pat'], 'js') ?>',
                                    '<?= esc($c['ape_mat'], 'js') ?>',
                                    '<?= esc($c['telefono'], 'js') ?>'

                                )"
                                style="border:none; cursor:pointer; background:none;">
                            <i class="fa-solid fa-pen-to-square"></i>
                        </button>
                    </td>
                    <td>
                        <form method="post" action="<?= base_url('borra_id_cliente/' . (int) $c['id']) ?>" class="form-borrar" style="display:inline; margin:0;" onsubmit="return confirm('¿Eliminar este registro?');">
<?= csrf_field() ?>
<button type="submit" style="border:none; cursor:pointer; background:none;"><i class="fa-solid fa-trash-can"></i></button>
</form>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?= $pager->links('default', 'w3_pager') ?>
</div><!-- /tabla-wrapper -->

    <!-- MODAL EDITAR CLIENTE -->
    <div id="modalEditarCliente" class="w3-modal" style="padding-top:100px; z-index:9999;">
        <div class="w3-modal-content w3-animate-zoom" style="max-width:500px; max-height:90vh; overflow-y:auto;">
            <form action="<?= base_url('modifica_cliente') ?>" method="post" class="w3-container w3-padding-16">
<?= csrf_field() ?>

                <input type="hidden" name="id" id="edit_id">

                <label><b>Nombre*</b></label>
                <input type="text" name="nom" id="edit_nom"
                    class="w3-input w3-border w3-margin-bottom" required value="<?= old('nom') ?>">

                <label><b>Apellido Paterno*</b></label>
                <input type="text" name="ape_pat" id="edit_ape_pat"
                    class="w3-input w3-border w3-margin-bottom" required value="<?= old('ape_pat') ?>">

                <label><b>Apellido Materno*</b></label>
                <input type="text" name="ape_mat" id="edit_ape_mat"
                    class="w3-input w3-border w3-margin-bottom" required value="<?= old('ape_mat') ?>"?>

                <label><b>Teléfono*</b></label>
                <input type="text" placeholder="Formato:1234567890" name="tel" id="edit_tel"
                    class="w3-input w3-border w3-margin-bottom" required value="<?= old('tel') ?>">

                <footer class="w3-container w3-green w3-padding">
                    <button type="submit" class="w3-button w3-white w3-right">Guardar</button>
                    <button type="button"
                            onclick="document.getElementById('modalEditarCliente').style.display='none'"
                            class="w3-button w3-white">Cancelar</button>
                </footer>

            </form>
        </div>
    </div>

    <!-- MODAL ESTADO PREFERENCIAL -->
    <div id="modalPreferencial" class="w3-modal" style="display:none; padding-top:100px; z-index:9999;">
        <div class="w3-modal-content w3-animate-zoom" style="max-width:520px; max-height:90vh; overflow-y:auto;">
            <form action="<?= base_url('preferencial_cliente') ?>" method="post" class="w3-container w3-padding-16" id="form-pref">
                <?= csrf_field() ?>
                <input type="hidden" name="id" id="pref_id">

                <h3 style="margin-top:0;">Estado preferencial</h3>
                <p id="pref_cliente" class="pref-cliente"></p>
                <p id="pref_actual" class="pref-actual"></p>

                <label class="pref-opcion"><input type="radio" name="modo" value="automatico" class="w3-radio">
                    <span><b>Automático</b> — preferencial al llegar a <?= (int) $umbral ?> compras <small>(quita cualquier cambio manual)</small></span></label>
                <label class="pref-opcion"><input type="radio" name="modo" value="si" class="w3-radio">
                    <span><b>Preferencial siempre</b> <small>(aunque no llegue a <?= (int) $umbral ?> compras)</small></span></label>
                <label class="pref-opcion"><input type="radio" name="modo" value="no" class="w3-radio">
                    <span><b>No preferencial</b> <small>(aunque tenga muchas compras)</small></span></label>

                <div id="pref_motivo_bloque">
                    <label for="pref_motivo"><b>Motivo*</b> <small>(<?= (int) \App\Models\Modelo_cliente::MOTIVO_MIN ?>–<?= (int) \App\Models\Modelo_cliente::MOTIVO_MAX ?> caracteres)</small></label>
                    <textarea name="motivo" id="pref_motivo" rows="3" maxlength="<?= (int) \App\Models\Modelo_cliente::MOTIVO_MAX ?>"
                              class="w3-input w3-border" placeholder="Ej: cliente mayorista con convenio verbal"></textarea>
                    <small id="pref_contador" class="pref-contador"></small>
                </div>

                <footer class="w3-container w3-green w3-padding w3-margin-top">
                    <button type="submit" class="w3-button w3-white w3-right">Guardar</button>
                    <button type="button" id="pref_cancelar" class="w3-button w3-white">Cancelar</button>
                </footer>
            </form>
        </div>
    </div>

<?php include 'Footer.php'; ?>
<!-- MODAL CREAR CLIENTE -->
<div id="modalCrearCliente" class="w3-modal">
    <div class="modal-contenido w3-animate-zoom">

        <header class="modal-header">
            <span onclick="document.getElementById('modalCrearCliente').style.display='none'"
                class="modal-cerrar">&times;</span>
            <h2>Registrar Cliente</h2>
            <form action="<?= base_url('guarda_cliente') ?>" method="post" class="modal-form">
<?= csrf_field() ?>

    <label><b>Nombre*</b></label>
    <input type="text" placeholder="Ej: Mario" name="nom" class="modal-input" required value="<?= old('nom') ?>">

    <label><b>Apellido Paterno*</b></label>
    <input type="text" placeholder="Ej: Pérez"name="ape_pat" class="modal-input" required value="<?= old('ape_pat') ?>">

    <label><b>Apellido Materno*</b></label>
    <input type="text" placeholder="Ej: González" name="ape_mat" class="modal-input" required value="<?= old('ape_mat') ?>">

    <label><b>Teléfono*</b></label>
    <input type="text" placeholder="Formato:1234567890" name="tel" class="modal-input" required value="<?= old('tel') ?>">

        <footer class="modal-footer">
            <button type="submit" class="btn-guardar">Guardar</button>
            <button type="button"
                onclick="document.getElementById('modalCrearCliente').style.display='none'"
                class="btn-cancelar">Cancelar</button>
        </footer>
    </header>
</form>

</div>
</div>
</div><!-- /.lista-wrapper -->
<script>
    // Llena el modal con los datos del cliente y lo abre
        function abrirModal(id, nombre, ape_pat, ape_mat, telefono) {
            document.getElementById('edit_id').value = id;
            document.getElementById('edit_nom').value = nombre;
            document.getElementById('edit_ape_pat').value = ape_pat;
            document.getElementById('edit_ape_mat').value = ape_mat;
            document.getElementById('edit_tel').value = telefono;
            document.getElementById('modalEditarCliente').style.display = 'block';
        }

        // Cerrar al hacer clic fuera
        window.onclick = function(event) {
            const modal = document.getElementById('modalEditarCliente');
            if (event.target === modal) modal.style.display = 'none';
        };
    // Si hay un error, abrimos el modal de crear cliente automáticamente
    <?php if (session()->has('error')): ?>
        document.getElementById('modalCrearCliente').style.display = 'block';
    <?php endif; ?>
        // Si hay un error, abrimos el modal de crear cliente automáticamente
    <?php if (session()->has('error2')): ?>
        document.getElementById('modalEditarCliente').style.display = 'block';
    <?php endif; ?>
</script>
<script src="<?= base_url('js/cliente_preferencial.js') ?>"></script>
</body>
</html>