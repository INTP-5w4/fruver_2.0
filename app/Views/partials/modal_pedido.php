<?php
/**
 * Ventana de pedido en UNA sola pantalla (antes era un asistente de 3 pasos).
 * La usa lista_p_pedido.php dos veces: para crear y para editar. La lógica está en
 * public/js/pedido_formulario.js y los estilos en public/estilos/pedido_formulario.css.
 *
 * Crear un pedido de 2 productos con el ratón: 8 clics (abrir, cliente ×2, producto,
 * agregar, producto, agregar, guardar); con teclado, sin ratón:
 *   Cliente → Tab… → Producto: escribir, ↓/↑, Enter → Cantidad, Enter (agrega) → … → Guardar.
 * La fecha es hoy, el estatus inicial es «Pedido realizado» con la hora actual y el
 * precio y la unidad salen de la última entrada del producto.
 *
 * @var string $modo          'crear' | 'editar'
 * @var list<array>  $clientes
 * @var list<array>  $repartidores
 * @var list<int>    $idsPreferenciales
 */
$p        = $modo === 'crear' ? 'c' : 'e';
$crear    = $modo === 'crear';
$idModal  = $crear ? 'modalCrearPpedido' : 'modalEditarPedido';
$titulo   = $crear ? 'Nuevo pedido' : 'Editar pedido';
$rutas    = rutas_listas();
$unidades = ['Kilogramo' => 'Kilogramo', 'Domo' => 'Domo', 'Ramos' => 'Ramo', 'Caja' => 'Caja'];
?>
<div id="<?= $idModal ?>" class="w3-modal pedido-modal" style="z-index:9999;"
     data-al-cerrar="<?= $crear ? 'cerrarModal' : 'eCerrarModal' ?>" data-enfocar="#<?= $p ?>-cliente">
  <div class="w3-modal-content w3-animate-zoom">
    <form id="<?= $p ?>-form" class="pedido-form" method="post" novalidate
          action="<?= base_url($crear ? 'guarda_pedido_completo' : 'modifica_pedido_completo') ?>"
          data-pedido-modo="<?= $p ?>" aria-describedby="<?= $p ?>-ayuda">
      <?= csrf_field() ?>
      <?= $crear ? campo_envio_unico() : '' ?>
      <input type="hidden" name="origen" value="lista_p_pedido">
      <input type="hidden" name="items" id="<?= $p ?>-items">
      <?php if ($crear): ?>
        <input type="hidden" name="estado" value="pedido_realizado">
      <?php else: ?>
        <input type="hidden" name="id_pedido" id="e-id-pedido">
      <?php endif; ?>

      <header class="pedido-encabezado">
        <h2 id="<?= $p ?>-titulo"><?= esc($titulo) ?><span class="pedido-numero" id="<?= $p ?>-numero"></span></h2>
        <button type="button" class="pedido-cerrar" data-cerrar-pedido aria-label="Cerrar la ventana «<?= esc($titulo, 'attr') ?>»">
          <i class="fa-solid fa-xmark" aria-hidden="true"></i>
        </button>
      </header>

      <div class="pedido-cuerpo">
        <p id="<?= $p ?>-ayuda" class="pedido-ayuda">Los campos con <span aria-hidden="true">*</span><span class="sr-solo">asterisco</span> son obligatorios.</p>

        <fieldset class="pedido-seccion">
          <legend>Datos del pedido</legend>
          <div class="pedido-rejilla">
            <div class="pedido-campo">
              <label for="<?= $p ?>-cliente">Cliente <span aria-hidden="true">*</span></label>
              <select id="<?= $p ?>-cliente" name="id_cliente" class="w3-select w3-border" required>
                <option value="">Elige un cliente…</option>
                <?php foreach ($clientes as $c): ?>
                  <option value="<?= (int) $c['id'] ?>">
                    <?= in_array((int) $c['id'], $idsPreferenciales, true) ? '★ ' : '' ?><?= esc(trim($c['nombre'] . ' ' . $c['ape_pat'] . ' ' . $c['ape_mat'])) ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="pedido-campo">
              <label for="<?= $p ?>-fecha">Fecha <span aria-hidden="true">*</span></label>
              <input type="date" id="<?= $p ?>-fecha" name="fecha" class="w3-input w3-border" required
                     value="<?= $crear ? date('Y-m-d') : '' ?>">
            </div>
            <div class="pedido-campo">
              <label for="<?= $p ?>-repartidor">Repartidor <span aria-hidden="true">*</span></label>
              <select id="<?= $p ?>-repartidor" name="id_repartidor" class="w3-select w3-border" required>
                <?php if (count($repartidores) !== 1): ?>
                  <option value="">Elige un repartidor…</option>
                <?php endif; ?>
                <?php foreach ($repartidores as $r): ?>
                  <option value="<?= (int) $r['id'] ?>"><?= esc(trim($r['nombre'] . ' ' . $r['ape_pat'] . ' ' . $r['ape_mat'])) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <?php if ($rutas): ?>
              <div class="pedido-campo">
                <label for="<?= $p ?>-direccion">Dirección de entrega</label>
                <select id="<?= $p ?>-direccion" name="id_direccion" class="w3-select w3-border"
                        data-direcciones-de="<?= $p ?>-cliente" data-api="<?= base_url('api_direcciones_cliente/') ?>">
                  <option value="">Dirección principal del cliente</option>
                </select>
              </div>
              <div class="pedido-campo">
                <label for="<?= $p ?>-ventana">Entregar antes de <span class="pedido-opcional">(opcional)</span></label>
                <input type="time" id="<?= $p ?>-ventana" name="ventana_fin" class="w3-input w3-border">
              </div>
            <?php endif; ?>
          </div>
        </fieldset>

        <fieldset class="pedido-seccion">
          <legend>Productos <span aria-hidden="true">*</span></legend>
          <p class="pedido-teclas" id="<?= $p ?>-teclas">
            Escribe el producto y elige con <kbd>↓</kbd> <kbd>↑</kbd> y <kbd>Enter</kbd>; en Cantidad,
            <kbd>Enter</kbd> lo agrega al pedido.
          </p>
          <div class="pedido-linea">
            <div class="pedido-campo pedido-campo-producto">
              <label for="<?= $p ?>-producto" id="<?= $p ?>-producto-etiqueta">Producto</label>
              <div class="combo">
                <i class="fa-solid fa-magnifying-glass combo-icono" aria-hidden="true"></i>
                <input type="text" id="<?= $p ?>-producto" class="w3-input w3-border combo-entrada"
                       role="combobox" aria-autocomplete="list" aria-expanded="false"
                       aria-controls="<?= $p ?>-producto-lista" aria-describedby="<?= $p ?>-teclas"
                       autocomplete="off" spellcheck="false" placeholder="Buscar producto…" data-sin-reglas>
                <ul id="<?= $p ?>-producto-lista" class="combo-lista" role="listbox"
                    aria-labelledby="<?= $p ?>-producto-etiqueta" hidden></ul>
              </div>
            </div>
            <div class="pedido-campo">
              <label for="<?= $p ?>-cant">Cantidad</label>
              <input type="number" id="<?= $p ?>-cant" class="w3-input w3-border" min="0.01" step="any" inputmode="decimal"
                     placeholder="Ej. 5" data-sin-reglas>
            </div>
            <div class="pedido-campo">
              <label for="<?= $p ?>-unidad">Unidad</label>
              <select id="<?= $p ?>-unidad" class="w3-select w3-border">
                <?php foreach ($unidades as $valor => $texto): ?>
                  <option value="<?= esc($valor, 'attr') ?>"><?= esc($texto) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="pedido-campo">
              <label for="<?= $p ?>-precio">Precio unitario</label>
              <input type="number" id="<?= $p ?>-precio" class="w3-input w3-border" min="0.01" step="0.01" inputmode="decimal"
                     placeholder="Ej. 45.00" data-sin-reglas>
            </div>
            <button type="button" class="btn btn-secundario pedido-agregar" id="<?= $p ?>-agregar">
              <i class="fa-solid fa-plus" aria-hidden="true"></i> Agregar
            </button>
          </div>
          <!-- Altura reservada: estos textos cambian sin mover los botones -->
          <p class="pedido-info" id="<?= $p ?>-info" aria-live="polite"></p>

          <div class="pedido-barra-pref" id="<?= $p ?>-barra-pref" hidden>
            <span>★ Cliente preferencial: precio sugerido −<span class="pref-pct"></span>&nbsp;%.</span>
            <button type="button" class="btn btn-chico btn-primario" data-pref="aplicar">Aplicar a todo el pedido</button>
            <button type="button" class="btn btn-chico btn-secundario" data-pref="catalogo">Volver al precio de catálogo</button>
          </div>

          <div class="tabla-wrapper pedido-carrito">
            <table class="w3-table w3-bordered w3-small">
              <caption class="sr-solo">Productos del pedido</caption>
              <thead>
                <tr>
                  <th scope="col">Producto</th><th scope="col">Unidad</th><th scope="col">Cant.</th>
                  <th scope="col">Precio</th><th scope="col">Total</th><th scope="col"><span class="sr-solo">Quitar</span></th>
                </tr>
              </thead>
              <tbody id="<?= $p ?>-carrito">
                <tr class="pedido-vacio"><td colspan="6">Aún no hay productos en el pedido.</td></tr>
              </tbody>
            </table>
          </div>
        </fieldset>

        <?php if (! $crear): ?>
          <fieldset class="pedido-seccion">
            <legend>Estatus</legend>
            <div class="pedido-rejilla">
              <div class="pedido-campo">
                <label for="e-estado">Nuevo estatus <span aria-hidden="true">*</span></label>
                <select id="e-estado" name="estado" class="w3-select w3-border" required></select>
              </div>
              <div class="pedido-campo">
                <label for="e-fecha-estatus">Fecha y hora del estatus <span aria-hidden="true">*</span></label>
                <input type="datetime-local" id="e-fecha-estatus" name="fecha_estatus" class="w3-input w3-border" required>
              </div>
            </div>
          </fieldset>
        <?php endif; ?>
      </div>

      <footer class="pedido-pie">
        <p class="pedido-aviso" id="<?= $p ?>-aviso" role="alert"></p>
        <div class="pedido-pie-fila">
          <span class="pedido-total">Total: <b id="<?= $p ?>-total">$0.00</b></span>
          <button type="button" class="btn btn-secundario" data-cerrar-pedido>Cancelar</button>
          <button type="submit" class="btn btn-primario" id="<?= $p ?>-guardar">
            <i class="fa-solid fa-check" aria-hidden="true"></i> <?= $crear ? 'Guardar pedido' : 'Guardar cambios' ?>
          </button>
        </div>
      </footer>
    </form>
  </div>
</div>
