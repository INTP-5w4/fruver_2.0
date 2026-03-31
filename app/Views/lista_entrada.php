<!DOCTYPE html>
<html lang="en">
<head>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/7.0.1/css/all.min.css" integrity="sha512-2SwdPD6INVrV/lHTZbO2nodKhrnDdJK9/kg2XD1r9uGqPo1cUbujc+IYdlYdEErWNu69gVcYgdxlmVmzTWnetw==" crossorigin="anonymous" referrerpolicy="no-referrer" />    <meta charset="UTF-8">    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
      <link rel="stylesheet" href="https://www.w3schools.com/w3css/5/w3.css">
    <title>Lista entrada</title>
</head>
<body>
    <table>
        <thead>
            <th>ID</th>
            <th>Fecha de entrada</th>
            <th>Fecha de caducidad</th>
            <th>Cantidad</th>
            <th>Unidad de compra</th>
            <th>Unidad de venta</th>
            <th>Precio compra</th>
            <th>Nombre del producto</th>
            <th>Editar</th>
            <th>Eliminar</th>
        </thead>
        <tbody>
            <?php foreach($entradas as $e): ?>
            <tr>
                <td><?= $e['id']?></td>
                <td><?= $e['fecha']?></td>
                <td><?= $e['fecha_cad']?></td>
                <td><?= $e['cantidad']?></td>
                <td><?= $e['u_compra']?></td>
                <td><?= $e['u_venta']?></td>
                <td><?= $e['precio_compra']?></td>
                  <td>
                    <?php $p = $productos[$e['id_producto']] ?? null;
                    echo $p ? "{$p['nombre']}" : 'Desconocido'; ?>
                </td>
                <td>
                    <button onclick="abrirModal(
                                '<?= $e['id'] ?>',
                                '<?= $e['fecha'] ?>',
                                '<?= $e['fecha_cad'] ?>',
                                '<?= $e['cantidad'] ?>',
                                '<?= $e['u_compra'] ?>',
                                '<?= $e['u_venta'] ?>',
                                '<?= $e['precio_compra'] ?>',
                                '<?= $e['id_producto'] ?>'
                            )"
                            style="border:none; cursor:pointer; background:none;">
                        <i class="fa-solid fa-pen-to-square"></i>
                    </button>
                </td>
                <td>
                    <a href="<?= base_url('borra_id_entrada/'.$e['id']) ?>">
                        <button style="border:none; cursor:pointer; background:none;">
                            <i class="fa-solid fa-trash-can"></i>
                        </button>
                    </a>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <!-- MODAL EDITAR ENTRADA -->
    <div id="modalEditarEntrada" class="w3-modal" style="padding-top:100px; z-index:9999;">
        <div class="w3-modal-content w3-animate-zoom" style="max-width:500px; max-height:90vh; overflow-y:auto;">
            <form action="<?= base_url('modifica_entrada') ?>" method="post" class="w3-container w3-padding-16">

                <input type="hidden" name="id" id="edit_id">

                <label><b>Fecha de entrada</b></label>
                <input type="date" name="f_ent" id="edit_f_ent"
                       class="w3-input w3-border w3-margin-bottom" required>

                <label><b>Fecha de caducidad</b></label>
                <input type="date" name="f_cad" id="edit_f_cad"
                       class="w3-input w3-border w3-margin-bottom" required>

                <label><b>Cantidad</b></label>
                <input type="number" name="cant" id="edit_cant"
                       class="w3-input w3-border w3-margin-bottom" required>

                <label><b>Unidad de compra</b></label>
                <select name="u_com" id="edit_u_com" class="w3-select w3-border w3-margin-bottom" required>
                    <option value="Caja">Caja</option>
                    <option value="Arpilla">Arpilla</option>
                    <option value="Bulto">Bulto</option>
                    <option value="Tonelada">Tonelada</option>
                </select>

                <label><b>Unidad de venta</b></label>
                <select name="u_ven" id="edit_u_ven" class="w3-select w3-border w3-margin-bottom" required>
                    <option value="Kilogramo">Kilogramo</option>
                    <option value="Litro">Litro</option>
                    <option value="Caja">Caja</option>
                </select>

                <label><b>Precio de compra</b></label>
                <input type="number" name="precio_compra" id="edit_precio_compra"
                       class="w3-input w3-border w3-margin-bottom" required>

                <label><b>Producto</b></label>
                <select name="id_producto" id="edit_id_producto" class="w3-select w3-border w3-margin-bottom" required>
                    <?php foreach ($productos as $p): ?>
                        <option value="<?= $p['id'] ?>"><?= $p['nombre'] ?></option>
                    <?php endforeach; ?>
                </select>

                <footer class="w3-container w3-green w3-padding">
                    <button type="submit" class="w3-button w3-white w3-right">Guardar</button>
                    <button type="button"
                            onclick="document.getElementById('modalEditarEntrada').style.display='none'"
                            class="w3-button w3-white">Cancelar</button>
                </footer>

            </form>
        </div>
    </div>

    <script>
        function abrirModal(id, fecha, fecha_cad, cantidad, u_compra, u_venta, precio_compra, id_producto) {
            document.getElementById('edit_id').value = id;
            document.getElementById('edit_f_ent').value = fecha;
            document.getElementById('edit_f_cad').value = fecha_cad;
            document.getElementById('edit_cant').value = cantidad;
            document.getElementById('edit_u_com').value = u_compra;
            document.getElementById('edit_u_ven').value = u_venta;
            document.getElementById('edit_precio_compra').value = precio_compra;
            document.getElementById('edit_id_producto').value = id_producto;
            document.getElementById('modalEditarEntrada').style.display = 'block';
        }

        window.onclick = function(event) {
            const modal = document.getElementById('modalEditarEntrada');
            if (event.target === modal) modal.style.display = 'none';
        };
    </script>

</body>
</html>