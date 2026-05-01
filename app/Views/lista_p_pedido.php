<!DOCTYPE html>
<html lang="en">
<head>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/7.0.1/css/all.min.css" integrity="sha512-2SwdPD6INVrV/lHTZbO2nodKhrnDdJK9/kg2XD1r9uGqPo1cUbujc+IYdlYdEErWNu69gVcYgdxlmVmzTWnetw==" crossorigin="anonymous" referrerpolicy="no-referrer" />    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://www.w3schools.com/w3css/5/w3.css">
    <link rel="stylesheet" href="<?= base_url('estilos/estilosPaginas.css') ?>">
    <link rel="stylesheet" href="<?= base_url('estilos/Header.css') ?>">
    <title>Lista p_pedido</title>
</head>
<body>
    <?php include 'Header.php'; ?>

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
<div class="contenedor-boton" style="padding-top: 80px;">
    <button onclick="document.getElementById('modalCrearPpedido').style.display='block'"
            class="btn-agregar">
        + Nuevo Carrito
    </button>
</div>

    <table>
        <thead>
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
        </thead>
        
        <?php foreach($p_pedidos as $pp): ?>
        <tr>
            <td><?= $pp['id'] ?></td>
            <td><?= $pp['cant'] ?></td>
            <td><?= $pp['precio_venta'] ?></td>
            <td><?= $pp['unidad_venta'] ?></td>
            <td><?= $pp['total'] ?></td>
            <td><?= $pp['id_pedido'] ?></td>
            <td><?= $productos[$pp['id_producto']]['nombre'] ?></td>
            <td>
                <button onclick="abrirModal(
                            '<?= $pp['id'] ?>',
                            '<?= $pp['cant'] ?>',
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

                <label><b>Cantidad*</b></label>
                <input type="number" name="cant" id="edit_cant"
                    class="w3-input w3-border w3-margin-bottom" required>

                <label><b>Precio de venta*</b></label>
                <input type="number" name="p_venta" id="edit_p_venta" step="0.01"
                    class="w3-input w3-border w3-margin-bottom" required>

                <label><b>Unidad de venta*</b></label>
                <select name="u_venta" id="edit_u_venta"
                        class="w3-select w3-border w3-margin-bottom" required>
                    <option value="Kilogramo">Kilogramo</option>
                    <option value="Domo">Domo</option>
                    <option value="Ramos">Ramo</option>
                    <option value="Caja">Caja</option>
                </select>

                <label><b>Total*</b></label>
                <input type="number" name="tot" id="edit_tot" step="0.01"
                    class="w3-input w3-border w3-margin-bottom" required>

                <label><b>Pedido*</b></label>
                <select name="id_pedido" id="edit_id_pedido"
                        class="w3-select w3-border w3-margin-bottom" required>
                    <?php foreach($pedidos as $p): ?>
                        <option value="<?= $p['id'] ?>"><?= $p['id'] ?></option>
                    <?php endforeach; ?>
                </select>

                <label><b>Producto*</b></label>
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

  <div id="modalCrearPpedido" class="w3-modal" style="padding-top:100px;z-index:9999;">
    <div class="w3-modal-content w3-animate-zoom" style="max-width:560px;max-height:90vh;overflow-y:auto;">
      <div class="w3-container w3-padding-16">
      

      <!-- Campos del ítem actual -->
      <label><b>Pedido*</b></label>
      <select id="cp_id_pedido" class="w3-select w3-border w3-margin-bottom">
        <?php foreach ($pedidos as $p): ?>
          <option value="<?= esc($p['id']) ?>"><?= esc($p['id']) ?></option>
        <?php endforeach; ?>
      </select>

      <label><b>Producto*</b></label>
      <select id="cp_id_producto" class="w3-select w3-border w3-margin-bottom">
        <?php foreach ($productos as $pr): ?>
          <option value="<?= esc($pr['id']) ?>"><?= esc($pr['nombre']) ?></option>
        <?php endforeach; ?>
      </select>

      <label><b>Unidad de venta*</b></label>
      <select id="cp_u_venta" class="w3-select w3-border w3-margin-bottom">
        <option value="Kilogramo">Kilogramo</option>
        <option value="Domo">Domo</option>
        <option value="Ramos">Ramo</option>
        <option value="Caja">Caja</option>
      </select>

      <label><b>Cantidad*</b></label>
      <input type="number" placeholder="Ej: 45" id="cp_cant" class="w3-input w3-border w3-margin-bottom">

      <label><b>Precio de venta(Unitario)*</b></label>
      <input type="number" placeholder="Ej:50" id="cp_p_venta" step="0.01" class="w3-input w3-border w3-margin-bottom">

      <!-- Botón para agregar al carrito (NO envía al servidor) -->
      <button type="button" onclick="agregarAlCarrito()" class="w3-button w3-blue w3-margin-bottom">
        + Agregar producto
      </button>

      <!-- Mini-tabla del carrito -->
      <div id="carritoContainer" style="display:none;">
        <hr>
        <b>Carrito:</b>
        <table class="w3-table w3-bordered w3-small w3-margin-top">
          <thead class="w3-green">
            <tr>
              <th>Producto</th>
              <th>Unidad</th>
              <th>Cant</th>
              <th>Precio</th>
              <th>Total</th>
              <th></th>
            </tr>
          </thead>
          <tbody id="carritoBody"></tbody>
        </table>
      </div>

      <!-- Form oculto que hace el POST real -->
      <form id="formCarrito" action="<?= base_url('guarda_p_pedido') ?>" method="post">
  
        <input type="hidden" name="items" id="inputItems">
        <?= csrf_field() ?>
      </form>

      <footer class="w3-container w3-green w3-padding w3-margin-top">
        <button type="button" onclick="enviarCarrito()" class="w3-button w3-white w3-right">
          Guardar todo
        </button>
        <button type="button"
                onclick="cerrarModal()"
                class="w3-button w3-white">Cancelar</button>
      </footer>

    </div>
  </div>
</div>
<script>
let carrito = [];

// Mapas para mostrar el nombre del producto en la tabla (no solo el id)
const nombreProducto = {
  <?php foreach ($productos as $pr): ?>
    <?= $pr['id'] ?>: "<?= esc($pr['nombre']) ?>",
  <?php endforeach; ?>
};

function agregarAlCarrito() {
  const id_pedido  = document.getElementById('cp_id_pedido').value;
  const id_producto = document.getElementById('cp_id_producto').value;
  const u_venta    = document.getElementById('cp_u_venta').value;
  const cant       = parseFloat(document.getElementById('cp_cant').value);
  const p_venta    = parseFloat(document.getElementById('cp_p_venta').value);

  if (!cant || !p_venta) {
    alert('Completa cantidad y precio antes de agregar.');
    return;
  }

  const total = (cant * p_venta).toFixed(2);

  carrito.push({ id_pedido, id_producto, u_venta, cant, p_venta, total });
  renderCarrito();

  // Limpia los campos numéricos para el siguiente ítem
  document.getElementById('cp_cant').value    = '';
  document.getElementById('cp_p_venta').value = '';
}

function renderCarrito() {
  const tbody = document.getElementById('carritoBody');
  tbody.innerHTML = '';

  carrito.forEach((item, i) => {
    tbody.innerHTML += `
      <tr>
        <td>${nombreProducto[item.id_producto]}</td>
        <td>${item.u_venta}</td>
        <td>${item.cant}</td>
        <td>$${item.p_venta}</td>
        <td>$${item.total}</td>
        <td><button type="button" onclick="quitarItem(${i})"
            class="w3-button w3-red w3-small">✕</button></td>
      </tr>`;
  });

  document.getElementById('carritoContainer').style.display =
    carrito.length ? 'block' : 'none';
}

function quitarItem(i) {
  carrito.splice(i, 1);
  renderCarrito();
}

function enviarCarrito() {
  if (carrito.length === 0) {
    alert('El carrito está vacío.');
    return;
  }
  document.getElementById('inputItems').value = JSON.stringify(carrito);
  document.getElementById('formCarrito').submit();
}

function cerrarModal() {
  carrito = [];
  renderCarrito();
  document.getElementById('modalCrearPpedido').style.display = 'none';
}
</script>

    <script>
        function abrirModal(id, cant, precio_venta, unidad_venta, total, id_pedido, id_producto) {
            document.getElementById('edit_id').value = id;
            document.getElementById('edit_cant').value = cant;
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