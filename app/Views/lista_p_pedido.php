<!DOCTYPE html>
<html lang="en">
<head>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/7.0.1/css/all.min.css" integrity="sha512-2SwdPD6INVrV/lHTZbO2nodKhrnDdJK9/kg2XD1r9uGqPo1cUbujc+IYdlYdEErWNu69gVcYgdxlmVmzTWnetw==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <link rel="stylesheet" href="https://www.w3schools.com/w3css/5/w3.css">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lista p_pedido</title>
</head>
<body>

    <table>
        <tr>
            <th>ID</th>
            <th>Cantidad</th>
            <th>Precio de venta</th>
            <th>Unidad de venta</th>
            <th>Total</th>
            <th>ID Pedido</th>
            <th>Producto</th>
            <th>Editar</th>
            <th>Eliminar</th>
        </tr>
        <?php foreach($p_pedidos as $pp): ?>
        <tr>
            <td><?= $pp['id'] ?></td>
            <td><?= $pp['cantidad'] ?></td>
            <td><?= $pp['precio_venta'] ?></td>
            <td><?= $pp['unidad_venta'] ?></td>
            <td><?= $pp['total'] ?></td>
            <td><?= $pp['id_pedido'] ?></td>
            <td><?= $productos[$pp['id_producto']]['nombre'] ?></td>
            <td>
                <button onclick="abrirModal(
                            '<?= $pp['id'] ?>',
                            '<?= $pp['cantidad'] ?>',
                            '<?= $pp['precio_venta'] ?>',
                            '<?= $pp['unidad_venta'] ?>',
                            '<?= $pp['total'] ?>',
                            '<?= $pp['id_pedido'] ?>',
                            '<?= $pp['id_producto'] ?>'
                        )"
                        style="border:none; cursor:pointer; background:none;">
                    <i class="fa-solid fa-pen-to-square"></i>
                </button>
            </td>
            <td>
                <a href="<?= base_url('borra_id_p_pedido/'.$pp['id']) ?>"
                   onclick="return confirm('¿Estás seguro de que quieres eliminar este registro?')">
                    <button style="border:none; cursor:pointer; background:none;">
                        <i class="fa-solid fa-trash-can"></i>
                    </button>
                </a>
            </td>
        </tr>
        <?php endforeach; ?>
    </table>

    <!-- MODAL EDITAR P_PEDIDO -->
    <div id="modalEditarPPedido" class="w3-modal" style="padding-top:100px; z-index:9999;">
        <div class="w3-modal-content w3-animate-zoom" style="max-width:500px; max-height:90vh; overflow-y:auto;">
            <form action="<?= base_url('modifica_p_pedido') ?>" method="post" class="w3-container w3-padding-16">

                <input type="hidden" name="id" id="edit_id">

                <label><b>Cantidad</b></label>
                <input type="number" name="cant" id="edit_cant"
                       class="w3-input w3-border w3-margin-bottom" required>

                <label><b>Precio de venta</b></label>
                <input type="number" name="p_venta" id="edit_p_venta" step="0.01"
                       class="w3-input w3-border w3-margin-bottom" required>

                <label><b>Unidad de venta</b></label>
                <select name="u_venta" id="edit_u_venta"
                        class="w3-select w3-border w3-margin-bottom" required>
                    <option value="Kilogramo">Kilogramo</option>
                    <option value="Domo">Domo</option>
                    <option value="Ramos">Ramo</option>
                    <option value="Caja">Caja</option>
                </select>

                <label><b>Total</b></label>
                <input type="number" name="tot" id="edit_tot" step="0.01"
                       class="w3-input w3-border w3-margin-bottom" required>

                <label><b>Pedido</b></label>
                <select name="id_pedido" id="edit_id_pedido"
                        class="w3-select w3-border w3-margin-bottom" required>
                    <?php foreach($pedidos as $p): ?>
                        <option value="<?= $p['id'] ?>"><?= $p['id'] ?></option>
                    <?php endforeach; ?>
                </select>

                <label><b>Producto</b></label>
                <select name="id_producto" id="edit_id_producto"
                        class="w3-select w3-border w3-margin-bottom" required>
                    <?php foreach($productos as $pr): ?>
                        <option value="<?= $pr['id'] ?>"><?= esc($pr['nombre']) ?></option>
                    <?php endforeach; ?>
                </select>

                <footer class="w3-container w3-green w3-padding">
                    <button type="submit" class="w3-button w3-white w3-right">Guardar</button>
                    <button type="button"
                            onclick="document.getElementById('modalEditarPPedido').style.display='none'"
                            class="w3-button w3-white">Cancelar</button>
                </footer>

            </form>
        </div>
    </div>

    <script>
        function abrirModal(id, cantidad, precio_venta, unidad_venta, total, id_pedido, id_producto) {
            document.getElementById('edit_id').value = id;
            document.getElementById('edit_cant').value = cantidad;
            document.getElementById('edit_p_venta').value = precio_venta;
            document.getElementById('edit_u_venta').value = unidad_venta;
            document.getElementById('edit_tot').value = total;
            document.getElementById('edit_id_pedido').value = id_pedido;
            document.getElementById('edit_id_producto').value = id_producto;
            document.getElementById('modalEditarPPedido').style.display = 'block';
        }

        window.onclick = function(event) {
            const modal = document.getElementById('modalEditarPPedido');
            if (event.target === modal) modal.style.display = 'none';
        };
    </script>

</body>
</html>