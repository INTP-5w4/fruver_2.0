<!DOCTYPE html>
<html lang="en">
<head>
    <link rel="stylesheet" href="https://www.w3schools.com/w3css/5/w3.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/7.0.1/css/all.min.css" integrity="sha512-2SwdPD6INVrV/lHTZbO2nodKhrnDdJK9/kg2XD1r9uGqPo1cUbujc+IYdlYdEErWNu69gVcYgdxlmVmzTWnetw==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <link rel="stylesheet" href="<?= base_url('estilos/estilosPaginas.css') ?>">    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lista de productos</title>
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
    <button onclick="document.getElementById('modalCrearProducto').style.display='block'"
            class="btn-agregar">
        + Nuevo Producto
    </button>
</div>

    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Nombre</th>
                <th>Descripción</th>
                <th>Categoria</th>
                <th>Imagen</th>
                <th>Editar</th>
                <th>Eliminar</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($productos as $p): ?>
                <tr>
                    <td><?= $p['id'] ?></td>
                    <td><?= esc($p['nombre']) ?></td>
                    <td><?= esc($p['descripcion']) ?></td>
                    <td><?= esc($p['categoria']) ?></td>
                    <td><?= esc($p['img']) ?></td>
                    <td>
                        <button onclick="abrirModal(
                                    '<?= $p['id'] ?>',
                                    '<?= esc($p['nombre']) ?>',
                                    '<?= esc($p['descripcion']) ?>',
                                    '<?= esc($p['categoria']) ?>'
                                )"
                                style="border:none; cursor:pointer; background:none;">
                            <i class="fa-solid fa-pen-to-square"></i>
                        </button>
                    </td>
                    <td>
                        <a href="<?= base_url('borra_id_producto/'.$p['id']) ?>">
                            <button style="border:none; cursor:pointer; background:none;">
                                <i class="fa-solid fa-trash-can"></i>
                            </button>
                        </a>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <?= $pager->links('default', 'w3_pager') ?>

    <!-- MODAL EDITAR PRODUCTO -->
    <div id="modalEditarProducto" class="w3-modal" style="padding-top:100px; z-index:9999;">
        <div class="w3-modal-content w3-animate-zoom" style="max-width:500px; max-height:90vh; overflow-y:auto;">
            <form action="<?= base_url('modifica_producto') ?>" method="post"
                enctype="multipart/form-data" class="w3-container w3-padding-16">

                <input type="hidden" name="id" id="edit_id">

                <label><b>Nombre</b></label>
                <input type="text" name="nom" id="edit_nom"
                    class="w3-input w3-border w3-margin-bottom" required>

                <label><b>Descripción</b></label>
                <textarea name="desc" id="edit_desc" rows="4"
                    class="w3-input w3-border w3-margin-bottom" required></textarea>

                <label><b>Categoría</b></label>
                <select name="cat" id="edit_cat"
                class="w3-select w3-border w3-margin-bottom" required>
                    <option value="frutas">Frutas</option>
                    <option value="verduras">Verduras</option>
                    <option value="hierbas">Hierbas</option>
                </select>

                <label><b>Imagen</b> <span style="font-weight:normal;">(opcional, solo si deseas cambiarla)</span></label>
                <input type="file" name="img"
                    class="w3-input w3-border w3-margin-bottom">

                <footer class="w3-container w3-green w3-padding">
                    <button type="submit" class="w3-button w3-white w3-right">Guardar</button>
                    <button type="button"
                            onclick="document.getElementById('modalEditarProducto').style.display='none'"
                            class="w3-button w3-white">Cancelar</button>
                </footer>

            </form>
        </div>
    </div>
    <div id="modalCrearProducto" class="w3-modal" style="padding-top:100px;z-index:9999;">
        <div class="w3-modal-content w3-animate-zoom" style="max-width:500px;max-height:90vh;overflow-y:auto;">
            <form action="<?= base_url('guarda_producto') ?>" method="post" enctype="multipart/form-data" class="w3-container w3-padding-16">
                <label><b>Nombre</b></label>
                <input type="text" name="nom" placeholder="Ej: Tomate Saladet" class="w3-input w3-border w3-margin-bottom" required>
                <label><b>Descripción</b></label>
                <textarea name="desc" rows="4" class="w3-input w3-border w3-margin-bottom" required></textarea>
                <label><b>Categoría</b></label>
                <select name="cat" class="w3-select w3-border w3-margin-bottom" required>
                    <option value="frutas">Frutas</option>
                    <option value="verduras">Verdura</option>
                    <option value="hierbas">Hierba</option>
                </select>
                <label><b>Imagen</b></label>
                <input type="file" name="img" class="w3-input w3-border w3-margin-bottom" required>
                <footer class="w3-container w3-green w3-padding">
                    <button type="submit" class="w3-button w3-white w3-right">Guardar</button>
                    <button type="button" onclick="document.getElementById('modalCrearProducto').style.display='none'" class="w3-button w3-white">Cancelar</button>
                </footer>
            </form>
        </div>
    </div>

    <script>
        function abrirModal(id, nombre, descripcion, categoria) {
            document.getElementById('edit_id').value = id;
            document.getElementById('edit_nom').value = nombre;
            document.getElementById('edit_desc').value = descripcion;
            document.getElementById('edit_cat').value = categoria;
            document.getElementById('modalEditarProducto').style.display = 'block';
        }

        window.onclick = function(event) {
            const modal = document.getElementById('modalEditarProducto');
            if (event.target === modal) modal.style.display = 'none';
        };
    </script>

</body>
</html>