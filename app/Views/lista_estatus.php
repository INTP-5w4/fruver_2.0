<!DOCTYPE html>
<html lang="en">
<head>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/7.0.1/css/all.min.css" integrity="sha512-2SwdPD6INVrV/lHTZbO2nodKhrnDdJK9/kg2XD1r9uGqPo1cUbujc+IYdlYdEErWNu69gVcYgdxlmVmzTWnetw==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <link rel="stylesheet" href="https://www.w3schools.com/w3css/5/w3.css">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lista estatus</title>
</head>
<body>
    <table>
        <thead>
            <th>ID</th>
            <th>Estado</th>
            <th>Fecha</th>
            <th>ID Pedido</th>
            <th>Editar</th>
            <th>Eliminar</th>
        </thead>
        <tbody>
            <?php foreach($estatus as $e): ?>
                <tr>
                    <td><?= $e['id'] ?></td>
                    <td><?= $e['estado'] ?></td>
                    <td><?= $e['fecha'] ?></td>
                    <td><?= $e['id_pedido'] ?></td>
                    <td>
                        <button onclick="abrirModal(
                                    '<?= $e['id'] ?>',
                                    '<?= $e['estado'] ?>',
                                    '<?= $e['fecha'] ?>',
                                    '<?= $e['id_pedido'] ?>'
                                )"
                                style="border:none; cursor:pointer; background:none;">
                            <i class="fa-solid fa-pen-to-square"></i>
                        </button>
                    </td>
                    <td>
                        <a href="<?= base_url('borra_id_estatus/'.$e['id']) ?>">
                            <button style="border:none; cursor:pointer; background:none;">
                                <i class="fa-solid fa-trash-can"></i>
                            </button>
                        </a>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <!-- MODAL EDITAR ESTATUS -->
    <div id="modalEditarEstatus" class="w3-modal" style="padding-top:100px; z-index:9999;">
        <div class="w3-modal-content w3-animate-zoom" style="max-width:500px; max-height:90vh; overflow-y:auto;">
            <form action="<?= base_url('modifica_estatus') ?>" method="post" class="w3-container w3-padding-16">

                <input type="hidden" name="id" id="edit_id">

                <label><b>Estado</b></label>
                <select name="edo" id="edit_edo" class="w3-select w3-border w3-margin-bottom" required>
                    <option value="pedido_realizado">Pedido realizado</option>
                    <option value="pedido_confirmado">Pedido confirmado</option>
                    <option value="pedido_en_transito">Pedido en tránsito</option>
                    <option value="pedido_entregado">Pedido entregado</option>
                    <option value="pedido_a_credito">Pedido a crédito</option>
                    <option value="pedido_pagado">Pedido pagado</option>
                    <option value="pedido_cancelado">Pedido cancelado</option>
                </select>

                <label><b>Fecha</b></label>
                <input type="date" name="fecha" id="edit_fecha"
                       class="w3-input w3-border w3-margin-bottom" required>

                <label><b>ID Pedido</b></label>
                <input type="number" name="id_pedido" id="edit_id_pedido"
                       class="w3-input w3-border w3-margin-bottom" required>

                <footer class="w3-container w3-green w3-padding">
                    <button type="submit" class="w3-button w3-white w3-right">Guardar</button>
                    <button type="button"
                            onclick="document.getElementById('modalEditarEstatus').style.display='none'"
                            class="w3-button w3-white">Cancelar</button>
                </footer>

            </form>
        </div>
    </div>

    <script>
        function abrirModal(id, estado, fecha, id_pedido) {
            document.getElementById('edit_id').value = id;
            document.getElementById('edit_edo').value = estado;
            document.getElementById('edit_fecha').value = fecha;
            document.getElementById('edit_id_pedido').value = id_pedido;
            document.getElementById('modalEditarEstatus').style.display = 'block';
        }

        window.onclick = function(event) {
            const modal = document.getElementById('modalEditarEstatus');
            if (event.target === modal) modal.style.display = 'none';
        };
    </script>

</body>
</html>