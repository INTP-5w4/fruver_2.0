<!DOCTYPE html>
<html lang="en">
<head>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/7.0.1/css/all.min.css" integrity="sha512-2SwdPD6INVrV/lHTZbO2nodKhrnDdJK9/kg2XD1r9uGqPo1cUbujc+IYdlYdEErWNu69gVcYgdxlmVmzTWnetw==" crossorigin="anonymous" referrerpolicy="no-referrer" />    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://www.w3schools.com/w3css/5/w3.css">
    <link rel="stylesheet" href="<?= base_url('estilos/estilosPaginas.css') ?>">
    <title>Lista existencia</title>
</head>
<body>

<?php if (session()->getFlashdata('error')): ?>
    <div class="w3-panel w3-red w3-animate-opacity">
        <p><?= session()->getFlashdata('error') ?></p>
    </div>
<?php endif; ?>

<?php if (session()->getFlashdata('mensaje')): ?>
    <div class="w3-panel w3-green w3-animate-opacity">
        <p><?= session()->getFlashdata('mensaje') ?></p>
    </div>
<?php endif; ?>
<div class="contenedor-boton">
    <button onclick="document.getElementById('modalCrearExistencias').style.display='block'"
            class="btn-agregar">
        + Nueva Existencia
    </button>
</div>


    <table>
        <thead>
            <th>ID</th>
            <th>Existencias totales</th>
            <th>Existencias bloqueadas</th>
            <th>Existencias para venta</th>
            <th>Fecha</th>
            <th>Producto</th>
            <th>Editar</th>
            <th>Eliminar</th>
        </thead>
        <tbody>
            <?php foreach($existencias as $e): ?>
                <tr>
                    <td><?= $e['id'] ?></td>
                    <td><?= $e['e_total'] ?></td>
                    <td><?= $e['e_bloqueado'] ?></td>
                    <td><?= $e['e_venta'] ?></td>
                    <td><?= $e['fecha'] ?></td>
                    <td><?= $productos[$e['id_producto']]['nombre'] ?></td>
                    <td>
                        <button onclick="abrirModal(
                                    '<?= $e['id'] ?>',
                                    '<?= $e['e_total'] ?>',
                                    '<?= $e['e_bloqueado'] ?>',
                                    '<?= $e['e_venta'] ?>',
                                    '<?= $e['fecha'] ?>',
                                    '<?= $e['id_producto'] ?>'
                                )"
                                style="border:none; cursor:pointer; background:none;">
                            <i class="fa-solid fa-pen-to-square"></i>
                        </button>
                    </td>
                    <td>
                        <a href="<?= base_url('borra_id_existencia/'.$e['id']) ?>">
                            <i class="fa-solid fa-trash"></i>
                        </a>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <!-- MODAL EDITAR EXISTENCIA -->
    <div id="modalEditarExistencia" class="w3-modal" style="padding-top:100px; z-index:9999;">
        <div class="w3-modal-content w3-animate-zoom" style="max-width:500px; max-height:90vh; overflow-y:auto;">
            <form action="<?= base_url('modifica_existencia') ?>" method="post" class="w3-container w3-padding-16">

                <input type="hidden" name="id" id="edit_id">

                <label><b>Existencias totales</b></label>
                <input type="number" name="e_total" id="edit_e_total"
                    class="w3-input w3-border w3-margin-bottom" required>

                <label><b>Existencias bloqueadas</b></label>
                <input type="number" name="e_bloqueado" id="edit_e_bloqueado"
                    class="w3-input w3-border w3-margin-bottom" required>

                <label><b>Existencias para venta</b></label>
                <input type="number" name="e_venta" id="edit_e_venta"
                    class="w3-input w3-border w3-margin-bottom" required>

                <label><b>Fecha</b></label>
                <input type="datetime-local" name="fecha" id="edit_fecha"
                    class="w3-input w3-border w3-margin-bottom" required>

                <label><b>Producto</b></label>
                <select name="id_producto" id="edit_id_producto"
                        class="w3-select w3-border w3-margin-bottom" required>
                    <?php foreach($productos as $p): ?>
                        <option value="<?= esc($p['id']) ?>"><?= esc($p['nombre']) ?></option>
                    <?php endforeach; ?>
                </select>

                <footer class="w3-container w3-green w3-padding">
                    <button type="submit" class="w3-button w3-white w3-right">Guardar</button>
                    <button type="button"
                            onclick="document.getElementById('modalEditarExistencia').style.display='none'"
                            class="w3-button w3-white">Cancelar</button>
                </footer>

            </form>
        </div>
    </div>

<div id="modalCrearExistencias" class="w3-modal" style="padding-top:100px;z-index:9999;">
    <div class="w3-modal-content w3-animate-zoom" style="max-width:500px;max-height:90vh;overflow-y:auto;">
        <form action="<?= base_url('guarda_existencia') ?>" method="post" class="w3-container w3-padding-16">

            <label><b>Producto</b></label>
            <select name="id_producto" class="w3-select w3-border w3-margin-bottom" required>
                <?php foreach ($productos as $p): ?>
                    <option value="<?= esc($p['id']) ?>"><?= esc($p['nombre']) ?></option>
                <?php endforeach; ?>
            </select>

            <label><b>Existencias totales</b></label>
            <input type="number" name="e_total" class="w3-input w3-border w3-margin-bottom">

            <label><b>Existencias bloqueadas</b></label>
            <input type="number" name="e_bloqueado" class="w3-input w3-border w3-margin-bottom">

            <label><b>Existencias para venta</b></label>
            <input type="number" name="e_venta" class="w3-input w3-border w3-margin-bottom">

            <label><b>Fecha</b></label>
            <input type="timestamp" name="fecha" class="w3-input w3-border w3-margin-bottom" value="<?= date('Y-m-d H:i:s') ?>">

            <footer class="w3-container w3-green w3-padding">
                <button type="submit" class="w3-button w3-white w3-right">Guardar</button>
                <button type="button"
                        onclick="document.getElementById('modalCrearExistencias').style.display='none'"
                        class="w3-button w3-white">Cancelar</button>
            </footer>

        </form>
    </div>
</div>

    <script>
        function abrirModal(id, e_total, e_bloqueado, e_venta, fecha, id_producto) {
            document.getElementById('edit_id').value = id;
            document.getElementById('edit_e_total').value = e_total;
            document.getElementById('edit_e_bloqueado').value = e_bloqueado;
            document.getElementById('edit_e_venta').value = e_venta;
            document.getElementById('edit_fecha').value = fecha;
            document.getElementById('edit_id_producto').value = id_producto;
            document.getElementById('modalEditarExistencia').style.display = 'block';
        }

        window.onclick = function(event) {
            const modal = document.getElementById('modalEditarExistencia');
            if (event.target === modal) modal.style.display = 'none';
        };
    </script>

</body>
</html>