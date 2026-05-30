<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css"
          crossorigin="anonymous" referrerpolicy="no-referrer" />
    <link rel="stylesheet" href="https://www.w3schools.com/w3css/5/w3.css">
    <link rel="stylesheet" href="<?= base_url('estilos/estilosPaginas.css') ?>">
    <link rel="stylesheet" href="<?= base_url('estilos/Header.css') ?>">
    <title>Lista Pedidos</title>
</head>
<body>
<?php include 'Header.php'; ?>
<?php include 'sidebar.php'; ?>

<div class="lista-wrapper">

<div class="flash-container">
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
</div>

<div class="contenedor-boton" style="padding-top: 20px;">
    <button onclick="document.getElementById('modalCrearPpedido').style.display='block'"
            class="btn-agregar">
        + Nuevo Pedido
    </button>
</div>

<form method="get" action="<?= base_url('lista_p_pedido') ?>" class="w3-margin-bottom">
    <div style="display:flex; gap:8px;">
        <input
            type="text"
            name="buscar"
            value="<?= esc($buscar ?? '') ?>"
            placeholder="Buscar por id..."
            class="w3-input w3-border"
            style="max-width:350px;"
        >
        <button type="submit" class="w3-button w3-green">
            <i class="fa-solid fa-magnifying-glass"></i> Buscar
        </button>
        <?php if (!empty($buscar)): ?>
            <a href="<?= base_url('lista_p_pedido') ?>" class="w3-button w3-red">✕ Limpiar</a>
        <?php endif; ?>
    </div>
</form>

<!-- ══ TABLA PRINCIPAL ══ -->
<div class="tabla-wrapper">
<table>
    <thead>
        <tr>
            <th></th>
            <th>ID Pedido</th>
            <th>Fecha</th>
            <th>Cliente</th>
            <th>Productos</th>
            <th>Total pedido</th>
            <th>Repartidor</th>
            <th>Estatus</th>
            <th>Editar</th>
            <th>Eliminar</th>
        </tr>
    </thead>
    <tbody>
    <?php foreach ($pedidos_agrupados as $pedido): ?>

        <tr class="fila-pedido" style="cursor:pointer;" onclick="toggleDetalle(<?= $pedido['id_pedido'] ?>)">
            <td>
                <button class="btn-expandir" id="btn-<?= $pedido['id_pedido'] ?>">
                    <i class="fa-solid fa-chevron-right"></i>
                </button>
            </td>
            <td><b>#<?= esc($pedido['id_pedido']) ?></b></td>
            <td><?= esc($pedido['fecha_pedido'] ?? '—') ?></td>
            <td><?= esc($pedido['nombre_cliente'].' '.$pedido['ape_pat_cliente'].' '.$pedido['ape_mat_cliente']) ?></td>
            <td>
                <span class="resumen-productos" title="<?= esc(implode(', ', array_column($pedido['items'], 'nombre_producto'))) ?>">
                    <?= esc(implode(', ', array_column($pedido['items'], 'nombre_producto'))) ?>
                </span>
            </td>
            <td class="total-pedido">$<?= number_format($pedido['total_pedido'], 2) ?></td>
            <td><?= esc($pedido['nombre_repartidor'].' '.$pedido['ape_pat_repartidor'].' '.$pedido['ape_mat_repartidor']) ?></td>
            <td>
                <span class="w3-tag w3-round w3-blue-gray">
                    <?= esc(str_replace('_', ' ', $pedido['estado_actual'] ?? 'pendiente')) ?>
                </span>
            </td>
            <td onclick="event.stopPropagation()">
                <button onclick="abrirEditarPedido('<?= esc($pedido['id_pedido']) ?>')"
                        style="border:none; cursor:pointer; background:none;">
                    <i class="fa-solid fa-pen-to-square"></i>
                </button>
            </td>
            <td onclick="event.stopPropagation()">
                <a href="<?= base_url('borra_pedido_completo/'.$pedido['id_pedido']) ?>"
                onclick="return confirm('¿Eliminar este pedido y todos sus productos?')">
                    <button style="border:none; cursor:pointer; background:none;">
                        <i class="fa-solid fa-trash-can"></i>
                    </button>
                </a>
            </td>
        </tr>

        <tr class="fila-detalle" id="detalle-<?= $pedido['id_pedido'] ?>">
            <td colspan="10">
                <div class="detalle-inner">
                    <b>Productos del pedido #<?= $pedido['id_pedido'] ?>:</b>
                    <table class="w3-table w3-bordered w3-small">
                        <thead>
                            <tr>
                                <th>Producto</th>
                                <th>Unidad</th>
                                <th>Cantidad</th>
                                <th>Precio unit.</th>
                                <th>Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($pedido['items'] as $item): ?>
                            <tr>
                                <td><?= esc($item['nombre_producto']) ?></td>
                                <td><?= esc($item['unidad_venta']) ?></td>
                                <td><?= esc($item['cant']) ?></td>
                                <td>$<?= number_format($item['precio_venta'], 2) ?></td>
                                <td>$<?= number_format($item['total'], 2) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </td>
        </tr>

    <?php endforeach; ?>
    </tbody>
</table>

<?php if ($total_paginas > 1): ?>
<div style="display:flex; gap:8px; margin-top:16px; margin-bottom:16px;">
    <?php if ($pagina_actual > 1): ?>
        <a href="?buscar=<?= esc($buscar) ?>&page=<?= $pagina_actual - 1 ?>"
        class="w3-button w3-green">← Anterior</a>
    <?php endif; ?>

    <span class="w3-button w3-white w3-border">
        Página <?= $pagina_actual ?> de <?= $total_paginas ?>
        (<?= $total_pedidos ?> pedidos)
    </span>

    <?php if ($pagina_actual < $total_paginas): ?>
        <a href="?buscar=<?= esc($buscar) ?>&page=<?= $pagina_actual + 1 ?>"
           class="w3-button w3-green">Siguiente →</a>
    <?php endif; ?>
</div>
<?php endif; ?>

</div><!-- /tabla-wrapper -->


<!-- ══════════════════════════════════════════════════════════
     MODAL CREAR — Wizard 3 pasos
     ══════════════════════════════════════════════════════════ -->
<div id="modalCrearPpedido" class="w3-modal" style="padding-top:100px;z-index:9999;">
  <div class="w3-modal-content w3-animate-zoom" style="max-width:580px;max-height:90vh;overflow-y:auto;">
    <div class="w3-container w3-padding-16">

      <div class="w3-bar w3-margin-bottom" style="border-bottom:1px solid #ddd;">
        <div id="tab1" class="w3-bar-item w3-center w3-padding-small"
             style="width:33%;border-bottom:3px solid green;font-weight:bold;cursor:default">1. Pedido</div>
        <div id="tab2" class="w3-bar-item w3-center w3-padding-small"
             style="width:33%;border-bottom:3px solid #ccc;cursor:default">2. Productos</div>
        <div id="tab3" class="w3-bar-item w3-center w3-padding-small"
             style="width:33%;border-bottom:3px solid #ccc;cursor:default">3. Estatus</div>
      </div>

      <!-- PASO 1 -->
      <div id="paso1">
        <label><b>Fecha*</b></label>
        <input type="date" id="ped_fecha" class="w3-input w3-border w3-margin-bottom">
        <label><b>Cliente*</b></label>
        <select id="ped_id_cliente" class="w3-select w3-border w3-margin-bottom">
          <?php foreach ($clientes as $c): ?>
            <option value="<?= esc($c['id']) ?>">
              <?= esc($c['nombre'].' '.$c['ape_pat'].' '.$c['ape_mat']) ?>
            </option>
          <?php endforeach; ?>
        </select>
        <label><b>Repartidor*</b></label>
        <select id="ped_id_repartidor" class="w3-select w3-border w3-margin-bottom">
          <?php foreach ($repartidores as $r): ?>
            <option value="<?= esc($r['id']) ?>">
              <?= esc($r['nombre'].' '.$r['ape_pat'].' '.$r['ape_mat']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <!-- PASO 2 -->
      <div id="paso2" style="display:none;">

        <label><b>Producto*</b></label>
        <p class="search-hint"><i class="fa-solid fa-circle-info"></i> Escribe al menos 3 caracteres para buscar</p>

        <!-- Buscador -->
        <div class="prod-search-wrap">
          <i class="fa-solid fa-magnifying-glass search-icon"></i>
          <input type="text"
                 id="cp_buscar"
                 class="prod-search-input"
                 placeholder="Buscar producto..."
                 autocomplete="off">
          <div id="cp_resultados" class="prod-resultados"></div>
        </div>

        <!-- Badge del producto seleccionado -->
        <div id="cp_badge" class="prod-badge" style="display:none;">
          <span class="badge-nombre" id="cp_badge_nombre"></span>
          <span class="badge-stock"  id="cp_badge_stock"></span>
          <button type="button" class="badge-limpiar" onclick="cpLimpiarSeleccion()" title="Cambiar producto">✕</button>
        </div>

        <!-- ID del producto seleccionado (hidden) -->
        <input type="hidden" id="cp_id_producto">

        <label><b>Unidad de venta*</b></label>
        <select id="cp_u_venta" class="w3-select w3-border w3-margin-bottom">
          <option value="Kilogramo">Kilogramo</option>
          <option value="Domo">Domo</option>
          <option value="Ramos">Ramo</option>
          <option value="Caja">Caja</option>
        </select>
        <label><b>Cantidad*</b></label>
        <input type="number" id="cp_cant" placeholder="Ej: 5" class="w3-input w3-border w3-margin-bottom">
        <label><b>Precio de venta (unitario)*</b></label>
        <input type="number" id="cp_p_venta" placeholder="Ej: 45.00" step="0.01" class="w3-input w3-border w3-margin-bottom">
        <button type="button" onclick="agregarAlCarrito()" class="w3-button w3-blue w3-margin-bottom">
          + Agregar producto
        </button>

        <div id="carritoContainer" style="display:none;">
          <hr><b>Carrito:</b>
          <table class="w3-table w3-bordered w3-small w3-margin-top">
            <thead class="w3-green">
              <tr><th>Producto</th><th>Unidad</th><th>Cant</th><th>Precio</th><th>Total</th><th></th></tr>
            </thead>
            <tbody id="carritoBody"></tbody>
          </table>
        </div>
      </div>

      <!-- PASO 3 -->
      <div id="paso3" style="display:none;">
        <label><b>Estado inicial*</b></label>
        <select id="est_estado" class="w3-select w3-border w3-margin-bottom">
        <option value="pedido_realizado">Pedido realizado</option>
        </select>
        <label><b>Fecha y hora*</b></label>
        <input type="datetime-local" id="est_fecha" class="w3-input w3-border w3-margin-bottom">
      </div>

      <form id="formCarrito" action="<?= base_url('guarda_pedido_completo') ?>" method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="origen"        value="lista_p_pedido">
        <input type="hidden" name="fecha"         id="fn_fecha">
        <input type="hidden" name="id_cliente"    id="fn_id_cliente">
        <input type="hidden" name="id_repartidor" id="fn_id_repartidor">
        <input type="hidden" name="items"         id="inputItems">
        <input type="hidden" name="estado"        id="fn_estado">
        <input type="hidden" name="fecha_estatus" id="fn_fecha_estatus">
      </form>

      <footer class="w3-container w3-green w3-padding w3-margin-top">
        <button type="button" id="btnGuardar" onclick="enviarCarrito()"
                class="w3-button w3-white w3-right" style="display:none;">Guardar todo</button>
        <button type="button" id="btnSiguiente" onclick="siguientePaso()"
                class="w3-button w3-white w3-right">Siguiente →</button>
        <button type="button" id="btnAtras" onclick="anteriorPaso()"
                class="w3-button w3-white" style="display:none;">← Atrás</button>
        <button type="button" onclick="cerrarModal()" class="w3-button w3-white">Cancelar</button>
      </footer>

    </div>
  </div>
</div>


<!-- ══════════════════════════════════════════════════════════
     MODAL EDITAR — Wizard 3 pasos
     ══════════════════════════════════════════════════════════ -->
<div id="modalEditarPedido" class="w3-modal" style="padding-top:100px;z-index:9999;">
  <div class="w3-modal-content w3-animate-zoom" style="max-width:580px;max-height:90vh;overflow-y:auto;">
    <div class="w3-container w3-padding-16">

      <div class="w3-bar w3-margin-bottom" style="border-bottom:1px solid #ddd;">
        <div id="etab1" class="w3-bar-item w3-center w3-padding-small"
             style="width:33%;border-bottom:3px solid green;font-weight:bold;cursor:default">1. Pedido</div>
        <div id="etab2" class="w3-bar-item w3-center w3-padding-small"
             style="width:33%;border-bottom:3px solid #ccc;cursor:default">2. Productos</div>
        <div id="etab3" class="w3-bar-item w3-center w3-padding-small"
             style="width:33%;border-bottom:3px solid #ccc;cursor:default">3. Estatus</div>
      </div>

      <!-- PASO 1 -->
      <div id="epaso1">
        <label><b>Fecha*</b></label>
        <input type="date" id="eped_fecha" class="w3-input w3-border w3-margin-bottom">
        <label><b>Cliente*</b></label>
        <select id="eped_id_cliente" class="w3-select w3-border w3-margin-bottom">
          <?php foreach ($clientes as $c): ?>
            <option value="<?= esc($c['id']) ?>">
              <?= esc($c['nombre'].' '.$c['ape_pat'].' '.$c['ape_mat']) ?>
            </option>
          <?php endforeach; ?>
        </select>
        <label><b>Repartidor*</b></label>
        <select id="eped_id_repartidor" class="w3-select w3-border w3-margin-bottom">
        <?php foreach ($repartidores as $r): ?>
            <option value="<?= esc($r['id']) ?>">
            <?= esc($r['nombre'].' '.$r['ape_pat'].' '.$r['ape_mat']) ?>
            </option>
        <?php endforeach; ?>
        </select>
    </div>

    <!-- PASO 2 -->
      <div id="epaso2" style="display:none;">

        <label><b>Producto*</b></label>
        <p class="search-hint"><i class="fa-solid fa-circle-info"></i> Escribe al menos 3 caracteres para buscar</p>

        <!-- Buscador -->
        <div class="prod-search-wrap">
          <i class="fa-solid fa-magnifying-glass search-icon"></i>
          <input type="text"
                 id="ecp_buscar"
                 class="prod-search-input"
                 placeholder="Buscar producto..."
                 autocomplete="off">
          <div id="ecp_resultados" class="prod-resultados"></div>
        </div>

        <!-- Badge del producto seleccionado -->
        <div id="ecp_badge" class="prod-badge" style="display:none;">
          <span class="badge-nombre" id="ecp_badge_nombre"></span>
          <span class="badge-stock"  id="ecp_badge_stock"></span>
          <button type="button" class="badge-limpiar" onclick="ecpLimpiarSeleccion()" title="Cambiar producto">✕</button>
        </div>

        <!-- ID del producto seleccionado (hidden) -->
        <input type="hidden" id="ecp_id_producto">

        <label><b>Unidad de venta*</b></label>
        <select id="ecp_u_venta" class="w3-select w3-border w3-margin-bottom">
          <option value="Kilogramo">Kilogramo</option>
          <option value="Domo">Domo</option>
          <option value="Ramos">Ramo</option>
          <option value="Caja">Caja</option>
        </select>
        <label><b>Cantidad*</b></label>
        <input type="number" id="ecp_cant" placeholder="Ej: 5" class="w3-input w3-border w3-margin-bottom">
        <label><b>Precio de venta (unitario)*</b></label>
        <input type="number" id="ecp_p_venta" placeholder="Ej: 45.00" step="0.01" class="w3-input w3-border w3-margin-bottom">
        <button type="button" onclick="eAgregarAlCarrito()" class="w3-button w3-blue w3-margin-bottom">
          + Agregar producto
        </button>

        <div id="eCarritoContainer" style="display:none;">
          <hr><b>Carrito:</b>
          <table class="w3-table w3-bordered w3-small w3-margin-top">
            <thead class="w3-green">
              <tr><th>Producto</th><th>Unidad</th><th>Cant</th><th>Precio</th><th>Total</th><th></th></tr>
            </thead>
            <tbody id="eCarritoBody"></tbody>
          </table>
        </div>
      </div>

      <!-- PASO 3 -->
      <div id="epaso3" style="display:none;">
        <label><b>Nuevo estado*</b></label>
        <select id="eest_estado" class="w3-select w3-border w3-margin-bottom">
          <option value="pedido_realizado">Pedido realizado</option>
          <option value="pedido_confirmado">Pedido confirmado</option>
          <option value="pedido_en_transito">Pedido en tránsito</option>
          <option value="pedido_entregado">Pedido entregado</option>
          <option value="pedido_a_credito">Pedido a crédito</option>
          <option value="pedido_pagado">Pedido pagado</option>
          <option value="pedido_cancelado">Pedido cancelado</option>
        </select>
        <label><b>Fecha y hora*</b></label>
        <input type="datetime-local" id="eest_fecha" class="w3-input w3-border w3-margin-bottom">
      </div>

      <form id="eFormCarrito" action="<?= base_url('modifica_pedido_completo') ?>" method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="origen"        value="lista_p_pedido">
        <input type="hidden" name="id_pedido"     id="efn_id_pedido">
        <input type="hidden" name="fecha"         id="efn_fecha">
        <input type="hidden" name="id_cliente"    id="efn_id_cliente">
        <input type="hidden" name="id_repartidor" id="efn_id_repartidor">
        <input type="hidden" name="items"         id="eInputItems">
        <input type="hidden" name="estado"        id="efn_estado">
        <input type="hidden" name="fecha_estatus" id="efn_fecha_estatus">
      </form>

      <footer class="w3-container w3-green w3-padding w3-margin-top">
        <button type="button" id="eBtnGuardar" onclick="eEnviarCarrito()"
                class="w3-button w3-white w3-right" style="display:none;">Guardar cambios</button>
        <button type="button" id="eBtnSiguiente" onclick="eSiguientePaso()"
                class="w3-button w3-white w3-right">Siguiente →</button>
        <button type="button" id="eBtnAtras" onclick="eAnteriorPaso()"
                class="w3-button w3-white" style="display:none;">← Atrás</button>
        <button type="button" onclick="eCerrarModal()" class="w3-button w3-white">Cancelar</button>
      </footer>

    </div>
  </div>
</div>

<?php include 'Footer.php'; ?>

<!-- ══════════════════════════════════════════════════════════
     DATOS DEL SERVIDOR
     ══════════════════════════════════════════════════════════ -->
<script>
const stockPorProducto = <?= json_encode($stockPorProducto) ?>;
 
const nombreProducto = {
  <?php foreach ($productos as $pr): ?>
    <?= $pr['id'] ?>: "<?= esc($pr['nombre']) ?>",
  <?php endforeach; ?>
};
 
// Catálogo completo para el buscador
// Ahora incluye u_venta y precio sugeridos desde la última entrada
const catalogoProductos = <?= json_encode(array_map(fn($p) => [
    'id'        => (int)$p['id'],
    'nombre'    => $p['nombre'],
    'categoria' => $p['categoria'] ?? '',
    'u_venta'   => $uVentaSugeridaPorProducto[$p['id']] ?? '',
    'precio'    => $precioSugeridoPorProducto[$p['id']] ?? '',
], $productos)) ?>;
</script>
 
 
<!-- ══════════════════════════════════════════════════════════
     BLOQUE 2 — JAVASCRIPT PRINCIPAL  (reemplaza el bloque original)
     ══════════════════════════════════════════════════════════ -->
<script>
 
// ─────────────────────────────────────────────────────────────
// UTILIDADES
// ─────────────────────────────────────────────────────────────
 
/** Debounce genérico */
function debounce(fn, ms) {
    let timer;
    return function (...args) {
        clearTimeout(timer);
        timer = setTimeout(() => fn.apply(this, args), ms);
    };
}
 
/** Expandir / colapsar detalle de la tabla */
function toggleDetalle(idPedido) {
    const fila = document.getElementById('detalle-' + idPedido);
    const btn  = document.getElementById('btn-' + idPedido);
    fila.classList.toggle('abierto');
    btn.classList.toggle('abierto');
}
 
 
// ─────────────────────────────────────────────────────────────
// BUSCADOR GENÉRICO DE PRODUCTOS
//
//   prefijo     → 'cp_'  (crear) | 'ecp_' (editar)
//   onSeleccion → callback(id, nombre, stock, producto)
//                 Se llama al elegir un producto. Úsalo para
//                 auto-rellenar unidad y precio.
// ─────────────────────────────────────────────────────────────
function crearBuscadorProducto(prefijo, onSeleccion) {
 
    const inputBuscar   = document.getElementById(prefijo + 'buscar');
    const divResultados = document.getElementById(prefijo + 'resultados');
    const divBadge      = document.getElementById(prefijo + 'badge');
    const spanNombre    = document.getElementById(prefijo + 'badge_nombre');
    const spanStock     = document.getElementById(prefijo + 'badge_stock');
    const inputHidden   = document.getElementById(prefijo + 'id_producto');
 
    const selectCat = prefijo === 'cp_'
        ? document.getElementById('filtroCategoriaCrear')
        : document.getElementById('filtroCategoriaEditar');
 
    /** Filtra el catálogo y renderiza resultados */
    function buscar(texto) {
        const query     = texto.trim().toLowerCase();
        const categoria = selectCat ? selectCat.value : '';
 
        if (query.length < 3) {
            divResultados.style.display = 'none';
            divResultados.innerHTML = '';
            return;
        }
 
        let candidatos = catalogoProductos.filter(p => {
            const coincideNombre    = p.nombre.toLowerCase().includes(query);
            const coincideCategoria = !categoria || p.categoria === categoria;
            return coincideNombre && coincideCategoria;
        });
 
        if (candidatos.length === 0) {
            divResultados.innerHTML = '<div class="sin-resultados">Sin resultados para "' + texto + '"</div>';
            divResultados.style.display = 'block';
            return;
        }
 
        // Ordenar: primero los que tienen stock
        candidatos.sort((a, b) => {
            const sa = stockPorProducto[a.id] ?? 0;
            const sb = stockPorProducto[b.id] ?? 0;
            return sb - sa;
        });
 
        divResultados.innerHTML = candidatos.map(p => {
            const stock     = stockPorProducto[p.id] ?? 0;
            const pillClass = stock > 0 ? 'con-stock' : 'sin-stock';
            const pillText  = stock > 0 ? 'Stock: ' + stock : 'Sin stock';
            return `<div class="prod-item"
                         data-id="${p.id}"
                         data-nombre="${p.nombre.replace(/"/g,'&quot;')}"
                         data-stock="${stock}">
                      <span>${p.nombre}</span>
                      <span class="stock-pill ${pillClass}">${pillText}</span>
                    </div>`;
        }).join('');
 
        // ── Click en cada ítem ──────────────────────────────────
        divResultados.querySelectorAll('.prod-item').forEach(el => {
            el.addEventListener('click', () => {
                const id       = el.dataset.id;
                const nombre   = el.dataset.nombre;
                const stock    = parseInt(el.dataset.stock, 10);
                // Recuperar el objeto completo del catálogo para obtener
                // u_venta y precio sugeridos
                const producto = catalogoProductos.find(p => p.id == id) ?? {};
 
                seleccionar(id, nombre, stock);
 
                // Disparar callback con datos completos del producto
                if (onSeleccion) onSeleccion(id, nombre, stock, producto);
            });
        });
 
        divResultados.style.display = 'block';
    }
 
    /** Muestra badge y oculta el input de búsqueda */
    function seleccionar(id, nombre, stock) {
        inputHidden.value      = id;
        spanNombre.textContent = nombre;
 
        const sinStock = stock <= 0;
        spanStock.textContent = sinStock ? '⚠ Sin stock disponible' : '✓ Stock disponible: ' + stock;
        divBadge.className    = 'prod-badge' + (sinStock ? ' sin-stock' : '');
        divBadge.style.display = 'flex';
 
        inputBuscar.value           = '';
        divResultados.style.display = 'none';
        divResultados.innerHTML     = '';
        inputBuscar.style.display   = 'none';
    }
 
    /** Limpia la selección para volver a buscar */
    window[prefijo.replace('_','') + 'LimpiarSeleccion'] = function () {
        inputHidden.value           = '';
        divBadge.style.display      = 'none';
        inputBuscar.style.display   = '';
        inputBuscar.value           = '';
        inputBuscar.focus();
    };
 
    // Escuchar escritura con debounce de 600 ms
    inputBuscar.addEventListener('input', debounce(function () {
        buscar(this.value);
    }, 600));
 
    // Cerrar resultados al hacer clic fuera
    document.addEventListener('click', function (e) {
        if (!inputBuscar.contains(e.target) && !divResultados.contains(e.target)) {
            divResultados.style.display = 'none';
        }
    });
 
    // Reactivar búsqueda si cambia la categoría
    if (selectCat) {
        selectCat.addEventListener('change', () => {
            if (inputBuscar.style.display !== 'none' && inputBuscar.value.length >= 3) {
                buscar(inputBuscar.value);
            }
        });
    }
}
 
// ── Helper: intenta seleccionar la opción más cercana en un <select> ──
function matchearUnidad(selectEl, sugerida) {
    if (!sugerida) return;
    const s = sugerida.toLowerCase();
    for (const opt of selectEl.options) {
        const v = opt.value.toLowerCase();
        if (v === s || v.startsWith(s) || s.startsWith(v)) {
            opt.selected = true;
            return;
        }
    }
    // Si no hay match exacto, dejar la primera opción (el usuario puede cambiar)
}
 
// ── Inicializar buscador CREAR con callback de auto-relleno ──────────
crearBuscadorProducto('cp_', function(id, nombre, stock, producto) {
    // Auto-rellenar unidad de venta
    matchearUnidad(document.getElementById('cp_u_venta'), producto.u_venta);
 
    // Auto-rellenar precio (editable por el usuario)
    if (producto.precio) {
        document.getElementById('cp_p_venta').value = parseFloat(producto.precio).toFixed(2);
    }
});
 
// ── Inicializar buscador EDITAR con callback de auto-relleno ─────────
crearBuscadorProducto('ecp_', function(id, nombre, stock, producto) {
    // Auto-rellenar unidad de venta
    matchearUnidad(document.getElementById('ecp_u_venta'), producto.u_venta);
 
    // Auto-rellenar precio (editable por el usuario)
    if (producto.precio) {
        document.getElementById('ecp_p_venta').value = parseFloat(producto.precio).toFixed(2);
    }
});
 
 
// ════════════════════════════════════════════════════════════
//  WIZARD CREAR
// ════════════════════════════════════════════════════════════
let carrito    = [];
let pasoActual = 1;
 
function mostrarPaso(n) {
    [1, 2, 3].forEach(i => {
        document.getElementById('paso' + i).style.display = i === n ? 'block' : 'none';
        const tab = document.getElementById('tab' + i);
        tab.style.borderBottom = i === n ? '3px solid green' : '3px solid #ccc';
        tab.style.fontWeight   = i === n ? 'bold' : 'normal';
    });
    document.getElementById('btnAtras').style.display     = n > 1 ? 'inline-block' : 'none';
    document.getElementById('btnSiguiente').style.display = n < 3 ? 'inline-block' : 'none';
    document.getElementById('btnGuardar').style.display   = n === 3 ? 'inline-block' : 'none';
    pasoActual = n;
}
 
function siguientePaso() {
    if (pasoActual === 1 && !validarPaso1()) return;
    if (pasoActual === 2 && !validarPaso2()) return;
    if (pasoActual < 3) mostrarPaso(pasoActual + 1);
    if (pasoActual === 3) {
        const now   = new Date();
        const local = new Date(now - now.getTimezoneOffset() * 60000).toISOString().slice(0, 16);
        document.getElementById('est_fecha').value = local;
    }
}
 
function anteriorPaso() {
    if (pasoActual > 1) mostrarPaso(pasoActual - 1);
}
 
function validarPaso1() {
    if (!document.getElementById('ped_fecha').value ||
        !document.getElementById('ped_id_cliente').value ||
        !document.getElementById('ped_id_repartidor').value) {
        alert('Completa todos los campos del pedido.');
        return false;
    }
    return true;
}
 
function validarPaso2() {
    if (carrito.length === 0) {
        alert('Agrega al menos un producto al carrito.');
        return false;
    }
    return true;
}
 
function agregarAlCarrito() {
    const id_producto = document.getElementById('cp_id_producto').value;
    const u_venta     = document.getElementById('cp_u_venta').value;
    const cant        = parseFloat(document.getElementById('cp_cant').value);
    const p_venta     = parseFloat(document.getElementById('cp_p_venta').value);
 
    if (!id_producto) { alert('Selecciona un producto primero.'); return; }
    if (!cant || !p_venta) { alert('Completa cantidad y precio.'); return; }
 
    const disponible  = stockPorProducto[id_producto] ?? 0;
    const yaEnCarrito = carrito
        .filter(i => i.id_producto === id_producto)
        .reduce((sum, i) => sum + i.cant, 0);
 
    if (yaEnCarrito + cant > disponible) {
        const maxPosible = disponible - yaEnCarrito;
        alert(maxPosible <= 0
            ? `"${nombreProducto[id_producto]}" ya no tiene stock disponible.`
            : `Stock insuficiente. Puedes agregar máximo ${maxPosible} más de "${nombreProducto[id_producto]}".`
        );
        return;
    }
 
    carrito.push({ id_producto, u_venta, cant, p_venta, total: (cant * p_venta).toFixed(2) });
    renderCarrito();
 
    cpLimpiarSeleccion();
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
    document.getElementById('carritoContainer').style.display = carrito.length ? 'block' : 'none';
}
 
function quitarItem(i) { carrito.splice(i, 1); renderCarrito(); }
 
function enviarCarrito() {
    const estado   = document.getElementById('est_estado').value;
    const fechaEst = document.getElementById('est_fecha').value;
    if (!estado || !fechaEst) { alert('Completa estado y fecha.'); return; }
 
    document.getElementById('fn_fecha').value         = document.getElementById('ped_fecha').value;
    document.getElementById('fn_id_cliente').value    = document.getElementById('ped_id_cliente').value;
    document.getElementById('fn_id_repartidor').value = document.getElementById('ped_id_repartidor').value;
    document.getElementById('inputItems').value       = JSON.stringify(carrito);
    document.getElementById('fn_estado').value        = estado;
    document.getElementById('fn_fecha_estatus').value = fechaEst;
    document.getElementById('formCarrito').submit();
}
 
function cerrarModal() {
    carrito = [];
    renderCarrito();
    mostrarPaso(1);
    cpLimpiarSeleccion();
    document.getElementById('ped_fecha').value          = '';
    document.getElementById('cp_cant').value    = '';
    document.getElementById('cp_p_venta').value = '';
    document.getElementById('modalCrearPpedido').style.display = 'none';
}
 
 
// ════════════════════════════════════════════════════════════
//  WIZARD EDITAR
// ════════════════════════════════════════════════════════════
let carritoEditar = [];
let ePasoActual   = 1;
 
function eMostrarPaso(n) {
    [1, 2, 3].forEach(i => {
        document.getElementById('epaso' + i).style.display = i === n ? 'block' : 'none';
        const tab = document.getElementById('etab' + i);
        tab.style.borderBottom = i === n ? '3px solid green' : '3px solid #ccc';
        tab.style.fontWeight   = i === n ? 'bold' : 'normal';
    });
    document.getElementById('eBtnAtras').style.display     = n > 1 ? 'inline-block' : 'none';
    document.getElementById('eBtnSiguiente').style.display = n < 3 ? 'inline-block' : 'none';
    document.getElementById('eBtnGuardar').style.display   = n === 3 ? 'inline-block' : 'none';
    ePasoActual = n;
}
 
function eSiguientePaso() {
    if (ePasoActual === 1 && !eValidarPaso1()) return;
    if (ePasoActual === 2 && !eValidarPaso2()) return;
    if (ePasoActual < 3) eMostrarPaso(ePasoActual + 1);
    if (ePasoActual === 3) {
        const now   = new Date();
        const local = new Date(now - now.getTimezoneOffset() * 60000).toISOString().slice(0, 16);
        document.getElementById('eest_fecha').value = local;
    }
}
 
function eAnteriorPaso() {
    if (ePasoActual > 1) eMostrarPaso(ePasoActual - 1);
}
 
function eValidarPaso1() {
    if (!document.getElementById('eped_fecha').value ||
        !document.getElementById('eped_id_cliente').value ||
        !document.getElementById('eped_id_repartidor').value) {
        alert('Completa todos los campos del pedido.');
        return false;
    }
    return true;
}
 
function eValidarPaso2() {
    if (carritoEditar.length === 0) {
        alert('El carrito no puede quedar vacío.');
        return false;
    }
    return true;
}
 
async function abrirEditarPedido(idPedido) {
    try {
        const res  = await fetch(`<?= base_url('api_pedido/') ?>${idPedido}`);
        const data = await res.json();
 
        document.getElementById('efn_id_pedido').value      = idPedido;
        document.getElementById('eped_fecha').value         = data.pedido.fecha;
        document.getElementById('eped_id_cliente').value    = data.pedido.id_cliente;
        document.getElementById('eped_id_repartidor').value = data.pedido.id_repartidor;
 
        carritoEditar = data.items.map(item => ({
            id:          item.id,
            id_producto: item.id_producto,
            u_venta:     item.unidad_venta,
            cant:        parseFloat(item.cant),
            p_venta:     parseFloat(item.precio_venta),
            total:       parseFloat(item.total).toFixed(2),
        }));
        eRenderCarrito();
 
        const select     = document.getElementById('eest_estado');
        const permitidos = data.transiciones_validas;
        Array.from(select.options).forEach(opt => {
            const esPermitido = permitidos.includes(opt.value);
            opt.disabled      = !esPermitido;
            opt.style.color   = esPermitido ? '' : '#aaa';
        });
        const primeraValida = select.querySelector('option:not([disabled])');
        if (primeraValida) primeraValida.selected = true;
 
        if (permitidos.length === 0) {
            alert(`Este pedido está en "${data.estado_actual.replace(/_/g,' ')}" y no admite más cambios.`);
            return;
        }
 
        ecpLimpiarSeleccion();
        eMostrarPaso(1);
        document.getElementById('modalEditarPedido').style.display = 'block';
 
    } catch (e) {
        alert('Error al cargar los datos del pedido.');
        console.error(e);
    }
}
 
function eAgregarAlCarrito() {
    const id_producto = document.getElementById('ecp_id_producto').value;
    const u_venta     = document.getElementById('ecp_u_venta').value;
    const cant        = parseFloat(document.getElementById('ecp_cant').value);
    const p_venta     = parseFloat(document.getElementById('ecp_p_venta').value);
 
    if (!id_producto) { alert('Selecciona un producto primero.'); return; }
    if (!cant || !p_venta) { alert('Completa cantidad y precio.'); return; }
 
    const disponible  = stockPorProducto[id_producto] ?? 0;
    const yaEnCarrito = carritoEditar
        .filter(i => i.id_producto === id_producto)
        .reduce((sum, i) => sum + i.cant, 0);
 
    if (yaEnCarrito + cant > disponible) {
        const maxPosible = disponible - yaEnCarrito;
        alert(maxPosible <= 0
            ? `"${nombreProducto[id_producto]}" ya no tiene stock disponible.`
            : `Stock insuficiente. Puedes agregar máximo ${maxPosible} más de "${nombreProducto[id_producto]}".`
        );
        return;
    }
 
    carritoEditar.push({ id: null, id_producto, u_venta, cant, p_venta, total: (cant * p_venta).toFixed(2) });
    eRenderCarrito();
 
    ecpLimpiarSeleccion();
    document.getElementById('ecp_cant').value    = '';
    document.getElementById('ecp_p_venta').value = '';
}
 
function eRenderCarrito() {
    const tbody = document.getElementById('eCarritoBody');
    tbody.innerHTML = '';
    carritoEditar.forEach((item, i) => {
        tbody.innerHTML += `
          <tr>
            <td>${nombreProducto[item.id_producto]}</td>
            <td>${item.u_venta}</td>
            <td>${item.cant}</td>
            <td>$${item.p_venta}</td>
            <td>$${item.total}</td>
            <td><button type="button" onclick="eQuitarItem(${i})"
                class="w3-button w3-red w3-small">✕</button></td>
          </tr>`;
    });
    document.getElementById('eCarritoContainer').style.display = carritoEditar.length ? 'block' : 'none';
}
 
function eQuitarItem(i) { carritoEditar.splice(i, 1); eRenderCarrito(); }
 
function eEnviarCarrito() {
    const estado   = document.getElementById('eest_estado').value;
    const fechaEst = document.getElementById('eest_fecha').value;
    if (!estado || !fechaEst) { alert('Completa estado y fecha.'); return; }
 
    document.getElementById('efn_fecha').value         = document.getElementById('eped_fecha').value;
    document.getElementById('efn_id_cliente').value    = document.getElementById('eped_id_cliente').value;
    document.getElementById('efn_id_repartidor').value = document.getElementById('eped_id_repartidor').value;
    document.getElementById('eInputItems').value       = JSON.stringify(carritoEditar);
    document.getElementById('efn_estado').value        = estado;
    document.getElementById('efn_fecha_estatus').value = fechaEst;
    document.getElementById('eFormCarrito').submit();
}
 
function eCerrarModal() {
    carritoEditar = [];
    eRenderCarrito();
    eMostrarPaso(1);
    ecpLimpiarSeleccion();
    document.getElementById('ecp_cant').value    = '';
    document.getElementById('ecp_p_venta').value = '';
    document.getElementById('modalEditarPedido').style.display = 'none';
}
 
// Cerrar modales al hacer clic fuera
window.onclick = function (event) {
    if (event.target === document.getElementById('modalCrearPpedido'))  cerrarModal();
    if (event.target === document.getElementById('modalEditarPedido')) eCerrarModal();
};
 
</script>

</div><!-- /.lista-wrapper -->
</body>
</html>