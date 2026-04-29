<!DOCTYPE html>
<html lang="en">
<head>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/7.0.1/css/all.min.css" integrity="sha512-2SwdPD6INVrV/lHTZbO2nodKhrnDdJK9/kg2XD1r9uGqPo1cUbujc+IYdlYdEErWNu69gVcYgdxlmVmzTWnetw==" crossorigin="anonymous" referrerpolicy="no-referrer" />    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://www.w3schools.com/w3css/5/w3.css">
    <link rel="stylesheet" href="<?= base_url('estilos/estilosPaginas.css') ?>">
    <title>Document</title>
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
    <button onclick="document.getElementById('modalCrearCliente').style.display='block'"
            class="btn-agregar">
        + Nuevo Cliente
    </button>
</div>
    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Nombre</th>
                <th>Apellido Paterno</th>
                <th>Apellido Materno</th>
                <th>Telefono</th>
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
                    <td>
                        <!-- ↓ Botón que abre el modal y llena los datos -->
                        <button onclick="abrirModal(
                                    '<?= esc($c['id']) ?>',
                                    '<?= esc($c['nombre']) ?>',
                                    '<?= esc($c['ape_pat']) ?>',
                                    '<?= esc($c['ape_mat']) ?>',
                                    '<?= esc($c['telefono']) ?>'
                                )"
                                style="border:none; cursor:pointer; background:none;">
                            <i class="fa-solid fa-pen-to-square"></i>
                        </button>
                    </td>
                    <td>
                        <a href="<?= base_url('borra_id_cliente/'.$c['id']) ?>">
                            <button style="border:none; cursor:pointer; background:none;">
                                <i class="fa-solid fa-trash-can"></i>
                            </button>
                        </a>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <!-- MODAL EDITAR CLIENTE -->
    <div id="modalEditarCliente" class="w3-modal" style="padding-top:100px; z-index:9999;">
        <div class="w3-modal-content w3-animate-zoom" style="max-width:500px; max-height:90vh; overflow-y:auto;">
            <form action="<?= base_url('modifica_cliente') ?>" method="post" class="w3-container w3-padding-16">

                <input type="hidden" name="id" id="edit_id">

                <label><b>Nombre</b></label>
                <input type="text" name="nom" id="edit_nom"
                    class="w3-input w3-border w3-margin-bottom" required>

                <label><b>Apellido Paterno</b></label>
                <input type="text" name="ape_pat" id="edit_ape_pat"
                    class="w3-input w3-border w3-margin-bottom" required>

                <label><b>Apellido Materno</b></label>
                <input type="text" name="ape_mat" id="edit_ape_mat"
                    class="w3-input w3-border w3-margin-bottom" required>

                <label><b>Teléfono</b></label>
                <input type="text" name="tel" id="edit_tel"
                    class="w3-input w3-border w3-margin-bottom" required>

                <footer class="w3-container w3-green w3-padding">
                    <button type="submit" class="w3-button w3-white w3-right">Guardar</button>
                    <button type="button"
                            onclick="document.getElementById('modalEditarCliente').style.display='none'"
                            class="w3-button w3-white">Cancelar</button>
                </footer>

            </form>
        </div>
    </div>

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
    </script>
<!-- MODAL CREAR CLIENTE -->
<div id="modalCrearCliente" class="w3-modal">
    <div class="modal-contenido w3-animate-zoom">

        <header class="modal-header">
            <span onclick="document.getElementById('modalCrearCliente').style.display='none'"
                class="modal-cerrar">&times;</span>
            <h2>Registrar Cliente</h2>
            <form action="<?= base_url('guarda_cliente') ?>" method="post" class="modal-form">

    <label><b>Nombre</b></label>
    <input type="text" name="nom" class="modal-input" required>

    <label><b>Apellido Paterno</b></label>
    <input type="text" name="ape_pat" class="modal-input" required>

    <label><b>Apellido Materno</b></label>
    <input type="text" name="ape_mat" class="modal-input" required>

    <label><b>Teléfono</b></label>
    <input type="text" name="tel" class="modal-input" required>

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
</body>
</html>