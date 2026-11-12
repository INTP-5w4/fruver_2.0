<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <?= view('partials/estilos_panel') ?>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lista de productos</title>
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
</div>

<div class="contenedor-boton">
    <h1>Productos</h1>
    <button onclick="document.getElementById('modalCrearProducto').style.display='block'"
            class="btn-agregar">
        <i class="fa-solid fa-plus" aria-hidden="true"></i> Nuevo producto
    </button>
</div>
<?= view('partials/buscador_lista', ['ruta' => 'lista_producto', 'buscar' => $buscar ?? '', 'placeholder' => 'Buscar por nombre, código, descripción, categoría o id…']) ?>

<div class="tabla-wrapper" data-busqueda-resultados>
    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Código</th>
                <th>Nombre</th>
                <th>Descripción</th>
                <th>Categoria</th>
                <th>Imagen</th>
                <th>Editar</th>
                <th>Eliminar</th>
            </tr>
        </thead>
        <tbody>
            <?php helper('tienda'); // tienda_img_url(): miniatura del producto ?>
            <?php foreach ($productos as $p): ?>
                <tr>
                    <td><?= esc($p['id']) ?></td>
                    <td><?= esc($p['codigo'] ?? '—') ?></td>
                    <td><?= esc($p['nombre']) ?></td>
                    <td><?= esc($p['descripcion']) ?></td>
                    <td><?= esc($p['categoria']) ?></td>
                    <td>
                        <?php $foto = tienda_img_url($p['img']); ?>
                        <?php if ($foto !== ''): ?>
                            <img src="<?= esc($foto, 'attr') ?>" alt="<?= esc($p['nombre'], 'attr') ?>" class="miniatura" width="44" height="44" loading="lazy">
                        <?php else: ?>
                            <span class="sin-foto">Sin foto</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <button onclick="abrirModal(
                                    '<?= esc($p['id'], 'js') ?>',
                                    '<?= esc($p['nombre'], 'js') ?>',
                                    '<?= esc($p['descripcion'], 'js') ?>',
                                    '<?= esc($p['categoria'], 'js') ?>'
                                )"
                                style="border:none; cursor:pointer; background:none;">
                            <i class="fa-solid fa-pen-to-square"></i>
                        </button>
                    </td>
                    <td>
                        <form method="post" action="<?= base_url('borra_id_producto/' . (int) $p['id']) ?>" class="form-borrar" style="display:inline; margin:0;" onsubmit="return confirm('¿Eliminar este registro?');">
<?= csrf_field() ?>
<button type="submit" style="border:none; cursor:pointer; background:none;"><i class="fa-solid fa-trash-can"></i></button>
</form>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?= $pager->links('default', 'w3_pager') ?>
</div>

    <!-- MODAL EDITAR PRODUCTO -->
    <div id="modalEditarProducto" class="w3-modal" style="padding-top:100px; z-index:9999;">
        <div class="w3-modal-content w3-animate-zoom" style="max-width:500px; max-height:90vh; overflow-y:auto;">
            <form action="<?= base_url('modifica_producto') ?>" method="post"
                enctype="multipart/form-data" class="w3-container w3-padding-16">
<?= csrf_field() ?>

                <input type="hidden" name="id" id="edit_id">

                <label><b>Nombre*</b></label>
                <input type="text" name="nom" id="edit_nom"
                    class="w3-input w3-border w3-margin-bottom" required>

                <label><b>Descripción*</b></label>
                <textarea name="desc" id="edit_desc" rows="4"
                    class="w3-input w3-border w3-margin-bottom" required></textarea>

                <label><b>Categoría*</b></label>
                <select name="cat" id="edit_cat"
                class="w3-select w3-border w3-margin-bottom" required>
                    <option value="frutas">Frutas</option>
                    <option value="verduras">Verduras</option>
                    <option value="hierbas">Hierbas</option>
                </select>

                <label><b>Imagen*</b> <span style="font-weight:normal;">(opcional, solo si deseas cambiarla)</span></label>
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
<?= csrf_field() ?>
                <label><b>Nombre*</b></label>
                <input type="text" name="nom" placeholder="Ej: Tomate Saladet" class="w3-input w3-border w3-margin-bottom" required>
                <label><b>Descripción*</b></label>
                <textarea name="desc" rows="4" class="w3-input w3-border w3-margin-bottom" required></textarea>
                <label><b>Categoría*</b></label>
                <select name="cat" class="w3-select w3-border w3-margin-bottom" required>
                    <option value="frutas">Frutas</option>
                    <option value="verduras">Verdura</option>
                    <option value="hierbas">Hierba</option>
                </select>
                <label><b>Imagen*</b></label>
                <input type="file" name="img" class="w3-input w3-border w3-margin-bottom" required>
                <footer class="w3-container w3-green w3-padding">
                    <button type="submit" class="w3-button w3-white w3-right">Guardar</button>
                    <button type="button" onclick="document.getElementById('modalCrearProducto').style.display='none'" class="w3-button w3-white">Cancelar</button>
                </footer>
            </form>
        </div>
    </div>
<?php include 'Footer.php'; ?>
</div><!-- /.lista-wrapper -->


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