<!DOCTYPE html>
<html lang="en">
<head>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/7.0.1/css/all.min.css" integrity="sha512-2SwdPD6INVrV/lHTZbO2nodKhrnDdJK9/kg2XD1r9uGqPo1cUbujc+IYdlYdEErWNu69gVcYgdxlmVmzTWnetw==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <link rel="stylesheet" href="https://www.w3schools.com/w3css/5/w3.css">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lista Repartidores</title>
</head>
<body>

    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Nombre</th>
                <th>Apellido Paterno</th>
                <th>Apellido Materno</th>
                <th>Telefono</th>
                <th>Dirección</th>
                <th>Notas</th>
                <th>Editar</th>
                <th>Eliminar</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($repartidores as $r): ?>
                <tr>
                    <td><?= $r['id'] ?></td>
                    <td><?= esc($r['nombre']) ?></td>
                    <td><?= esc($r['ape_pat']) ?></td>
                    <td><?= esc($r['ape_mat']) ?></td>
                    <td><?= esc($r['telefono']) ?></td>
                    <td><?= esc($r['direccion']) ?></td>
                    <td><?= esc($r['notas']) ?></td>
                    <td>
                        <button onclick="abrirModal(
                                    '<?= $r['id'] ?>',
                                    '<?= esc($r['nombre']) ?>',
                                    '<?= esc($r['ape_pat']) ?>',
                                    '<?= esc($r['ape_mat']) ?>',
                                    '<?= esc($r['telefono']) ?>',
                                    '<?= esc($r['direccion']) ?>',
                                    '<?= esc($r['notas']) ?>'
                                )"
                                style="border:none; cursor:pointer; background:none;">
                            <i class="fa-solid fa-pen-to-square"></i>
                        </button>
                    </td>
                    <td>
                        <a href="<?= base_url('borra_id_repartidor/'.$r['id']) ?>">
                            <button style="border:none; cursor:pointer; background:none;">
                                <i class="fa-solid fa-trash-can"></i>
                            </button>
                        </a>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <!-- MODAL EDITAR REPARTIDOR -->
    <div id="modalEditarRepartidor" class="w3-modal" style="padding-top:100px; z-index:9999;">
        <div class="w3-modal-content w3-animate-zoom" style="max-width:500px; max-height:90vh; overflow-y:auto;">
            <form action="<?= base_url('modifica_repartidor') ?>" method="post" class="w3-container w3-padding-16">

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

                <label><b>Dirección</b></label>
                <input type="text" name="dir" id="edit_dir"
                       class="w3-input w3-border w3-margin-bottom" required>

                <label><b>Notas</b></label>
                <textarea name="not" id="edit_not" rows="4"
                          class="w3-input w3-border w3-margin-bottom" required></textarea>

                <footer class="w3-container w3-green w3-padding">
                    <button type="submit" class="w3-button w3-white w3-right">Guardar</button>
                    <button type="button"
                            onclick="document.getElementById('modalEditarRepartidor').style.display='none'"
                            class="w3-button w3-white">Cancelar</button>
                </footer>

            </form>
        </div>
    </div>

    <script>
        function abrirModal(id, nombre, ape_pat, ape_mat, telefono, direccion, notas) {
            document.getElementById('edit_id').value = id;
            document.getElementById('edit_nom').value = nombre;
            document.getElementById('edit_ape_pat').value = ape_pat;
            document.getElementById('edit_ape_mat').value = ape_mat;
            document.getElementById('edit_tel').value = telefono;
            document.getElementById('edit_dir').value = direccion;
            document.getElementById('edit_not').value = notas;
            document.getElementById('modalEditarRepartidor').style.display = 'block';
        }

        window.onclick = function(event) {
            const modal = document.getElementById('modalEditarRepartidor');
            if (event.target === modal) modal.style.display = 'none';
        };
    </script>

</body>
</html>