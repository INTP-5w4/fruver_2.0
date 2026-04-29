<!DOCTYPE html>
<html lang="en">
<head>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/7.0.1/css/all.min.css" integrity="sha512-2SwdPD6INVrV/lHTZbO2nodKhrnDdJK9/kg2XD1r9uGqPo1cUbujc+IYdlYdEErWNu69gVcYgdxlmVmzTWnetw==" crossorigin="anonymous" referrerpolicy="no-referrer" />    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://www.w3schools.com/w3css/5/w3.css">
    <link rel="stylesheet" href="<?= base_url('estilos/estilosPaginas.css') ?>">
    <title>Lista pedido</title>
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
    <button onclick="document.getElementById('modalCrearPedido').style.display='block'"
            class="btn-agregar">
        + Nuevo Cliente
    </button>
</div>


    <table>
        <thead>
            <th>ID</th>
            <th>Fecha</th>
            <th>Nombre del cliente</th>
            <th>Nombre del repartidor</th>
            <th>Carrito</th>
            <th>Editar</th>
            <th>Eliminar</th>
        </thead>
        <tbody>
            <?php foreach($pedidos as $p): ?>
            <tr>
                <td><?= $p['id'] ?></td>
                <td><?= $p['fecha'] ?></td>
                <td><?php $c = $clientes[$p['id_cliente']] ?? null;
                    echo $c ? "{$c['nombre']} {$c['ape_pat']} {$c['ape_mat']}" : 'Desconocido'; ?>
                </td>
                <td><?php $r = $repartidores[$p['id_repartidor']] ?? null;
                    echo $r ? "{$r['nombre']} {$r['ape_pat']} {$r['ape_mat']}" : 'Desconocido'; ?>
                </td>
                <td><?= $p['id_producto_pedido']?></td>
                <td>
                    <button onclick="abrirModal(
                                '<?= $p['id'] ?>',
                                '<?= $p['fecha'] ?>',
                                '<?= $p['id_cliente'] ?>',
                                '<?= $p['id_repartidor'] ?>'
                            )"
                            style="border:none; cursor:pointer; background:none;">
                        <i class="fa-solid fa-pen-to-square"></i>
                    </button>
                </td>
                <td>
                    <a href="<?= base_url('borra_id_pedido/'.$p['id']) ?>">
                        <button style="border:none; cursor:pointer; background:none;">
                            <i class="fa-solid fa-trash-can"></i>
                        </button>
                    </a>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <!-- MODAL EDITAR PEDIDO -->
    <div id="modalEditarPedido" class="w3-modal" style="padding-top:100px; z-index:9999;">
        <div class="w3-modal-content w3-animate-zoom" style="max-width:500px; max-height:90vh; overflow-y:auto;">
            <form action="<?= base_url('modifica_pedido') ?>" method="post" class="w3-container w3-padding-16">

                <input type="hidden" name="id" id="edit_id">

                <label><b>Fecha</b></label>
                <input type="date" name="fecha" id="edit_fecha"
                    class="w3-input w3-border w3-margin-bottom" required>

                <label><b>Cliente</b></label>
                <select name="id_cliente" id="edit_id_cliente"
                        class="w3-select w3-border w3-margin-bottom" required>
                    <?php foreach ($clientes as $c): ?>
                        <option value="<?= $c['id'] ?>">
                            <?= $c['nombre'].' '.$c['ape_pat'].' '.$c['ape_mat'] ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <label><b>Repartidor</b></label>
                <select name="id_repartidor" id="edit_id_repartidor"
                        class="w3-select w3-border w3-margin-bottom" required>
                    <?php foreach ($repartidores as $r): ?>
                        <option value="<?= $r['id'] ?>">
                            <?= $r['nombre'].' '.$r['ape_pat'].' '.$r['ape_mat'] ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <label for=""><b>Carrito</b></label>
                <select name="id_pp" id="" class="w3-select w3-border w3-margin-bottom" required>
                <?php foreach ($pps as $pp) :?>
                    <option value="<?= esc($pp['id']) ?>"><?= $pp['id'] ?></option>
                    <?php endforeach; ?>
                </select>


                <footer class="w3-container w3-green w3-padding">
                    <button type="submit" class="w3-button w3-white w3-right">Guardar</button>
                    <button type="button"
                            onclick="document.getElementById('modalEditarPedido').style.display='none'"
                            class="w3-button w3-white">Cancelar</button>
                </footer>

            </form>
        </div>
    </div>

    <script>
        function abrirModal(id, fecha, id_cliente, id_repartidor) {
            document.getElementById('edit_id').value = id;
            document.getElementById('edit_fecha').value = fecha;
            document.getElementById('edit_id_cliente').value = id_cliente;
            document.getElementById('edit_id_repartidor').value = id_repartidor;
            document.getElementById('modalEditarPedido').style.display = 'block';
        }

        window.onclick = function(event) {
            const modal = document.getElementById('modalEditarPedido');
            if (event.target === modal) modal.style.display = 'none';
        };
    </script>
    <div id="modalCrearPedido" class="w3-modal" style="padding-top:100px;z-index:9999;">
        <div class="w3-modal-content w3-animate-zoom" style="max-width:500px;max-height:90vh;overflow-y:auto;">
            <form action="<?= base_url('guarda_pedido') ?>" method="post" class="w3-container w3-padding-16">
                <label><b>Fecha</b></label>
                <input type="date" name="fecha" class="w3-input w3-border w3-margin-bottom" required>
                <label><b>Cliente</b></label>
                <select name="id_cliente" class="w3-select w3-border w3-margin-bottom" required>
                    <?php foreach ($clientes as $c): ?>
                        <option value="<?= esc($c['id']) ?>"><?= esc($c['nombre'].' '.$c['ape_pat'].' '.$c['ape_mat']) ?></option>
                    <?php endforeach; ?>
                </select>
                <label for=""><b>Carrito</b></label>
                <select name="id_pp" id="" class="w3-select w3-border w3-margin-bottom" required>
                <?php foreach ($pps as $pp) :?>
                    <option value="<?= esc($pp['id']) ?>"><?= $pp['id'] ?></option>
                    <?php endforeach; ?>
                </select>                
                <label><b>Repartidor</b></label>
                <select name="id_repartidor" class="w3-select w3-border w3-margin-bottom" required>
                    <?php foreach ($repartidores as $r): ?>
                        <option value="<?= esc($r['id']) ?>"><?= esc($r['nombre'].' '.$r['ape_pat'].' '.$r['ape_mat']) ?></option>
                    <?php endforeach; ?>
                </select>
                <footer class="w3-container w3-green w3-padding">
                    <button type="submit" class="w3-button w3-white w3-right">Guardar</button>
                    <button type="button" onclick="document.getElementById('modalCrearPedido').style.display='none'" class="w3-button w3-white">Cancelar</button>
                </footer>
            </form>
        </div>
    </div>

</body>
</html>