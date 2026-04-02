<!DOCTYPE html>
<html lang="en">
<head>
    <link rel="stylesheet" href="<?= base_url('estilos/main_page.css') ?>">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://www.w3schools.com/w3css/5/w3.css">
    <title>main_page</title>
</head>
<body>
    <header>
        <?php include 'Header.php'; ?>
    </header>
    <div class="vacio"></div>
   <button onclick="document.getElementById('modalProducto').style.display='block'"
        class="action-btn" style="border:none; cursor:pointer;">Crea Producto</button>
    <a href="<?= base_url('lista_producto') ?>"><button>Lista producto</button></a><br>
    
    <button onclick="document.getElementById('modalCliente').style.display='block'"
        class="action-btn" 
        style="border:none; cursor:pointer;">Crea Cliente</button>
    <a href="<?= base_url('lista_cliente') ?>"><button>Lista cliente</button></a><br>
    
    
    <button onclick="document.getElementById('modalRepartidor').style.display='block'"
        class="action-btn" style="border:none; cursor:pointer;">Crea Repartidor</button>
    <a href="<?= base_url('lista_repartidor') ?>"><button>Lista repartidor</button></a><br>
    
    
     <button onclick="document.getElementById('modalDireccion').style.display='block'"
        class="action-btn"
        style="border:none; cursor:pointer;">Crea Dirección</button>
    <a href="<?= base_url('lista_direccion') ?>"><button>Lista direccion</button></a><br>
    
    
    <button onclick="document.getElementById('modalEntrada').style.display='block'"
        class="action-btn" style="border:none; cursor:pointer;">Crea Entrada</button>
    <a href="<?= base_url('lista_entrada') ?>"><button>Lista entrada</button></a><br>
    
    
   <button onclick="document.getElementById('modalPedido').style.display='block'"
        class="action-btn" style="border:none; cursor:pointer;">Crea Pedido</button>
    <a href="<?= base_url('lista_pedido') ?>"><button>Lista pedido</button></a><br>
    
    
   <button onclick="document.getElementById('modalEstatus').style.display='block'"
        class="action-btn" style="border:none; cursor:pointer;">CreaEstatus</button>
    <a href="<?= base_url('lista_estatus') ?>"><button>Lista estatus</button></a><br>
    
    
    <button onclick="document.getElementById('modalExistencia').style.display='block'"
        class="action-btn" style="border:none; cursor:pointer;">Crea Existencia</button>
    <a href="<?= base_url('lista_existencia') ?>"><button>Lista existencia</button></a><br>
    
    
    <button onclick="document.getElementById('modalMerma').style.display='block'"
        class="action-btn" style="border:none; cursor:pointer;">Crea Merma</button>
    <a href="<?= base_url('lista_merma') ?>"><button>Lista merma</button></a><br>
    
   <button onclick="document.getElementById('modalPPedido').style.display='block'"
        class="action-btn" style="border:none; cursor:pointer;">Crea P. Pedido</button>
    <a href="<?= base_url('lista_p_pedido') ?>"><button>Lista p_pedido</button></a><br>

 <div id="modalCliente" class="w3-modal" style="padding-top:100px; z-index:9999;">
        <div class="w3-modal-content w3-animate-zoom" style="max-width:500px; max-height:90vh; overflow-y:auto;">
           
            <form action="<?= base_url('guarda_cliente') ?>" method="post" class="w3-container w3-padding-16">

                <label><b>Nombre</b></label>
                <input type="text" name="nom" class="w3-input w3-border w3-margin-bottom" required>

                <label><b>Apellido Paterno</b></label>
                <input type="text" name="ape_pat" class="w3-input w3-border w3-margin-bottom" required>

                <label><b>Apellido Materno</b></label>
                <input type="text" name="ape_mat" class="w3-input w3-border w3-margin-bottom" required>

                <label><b>Teléfono</b></label>
                <input type="text" name="tel" class="w3-input w3-border w3-margin-bottom" required>

                <footer class="w3-container w3-green w3-padding">
                    <button type="submit" class="w3-button w3-white w3-right">Guardar</button>
                    <button type="button"
                            onclick="document.getElementById('modalCliente').style.display='none'"
                            class="w3-button w3-white">Cancelar</button>
                </footer>

            </form>
        </div>
    </div>

    <div id="modalDireccion" class="w3-modal" style="padding-top:100px; z-index:9999;">
        <div class="w3-modal-content w3-animate-zoom" style="max-width:500px; max-height:90vh; overflow-y:auto;">
            <form action="<?= base_url('guarda_direccion') ?>" method="post" class="w3-container w3-padding-16">
                <label><b>Colonia</b></label>
                <input type="text" name="col" class="w3-input w3-border w3-margin-bottom" required>
                <label><b>Calle</b></label>
                <input type="text" name="calle" class="w3-input w3-border w3-margin-bottom" required>
                <label><b>Número</b></label>
                <input type="text" name="num" class="w3-input w3-border w3-margin-bottom" required>
                <label><b>Municipio</b></label>
                <input type="text" name="mun" class="w3-input w3-border w3-margin-bottom" required>
                <label><b>Estado</b></label>
                <select name="edo" class="w3-select w3-border w3-margin-bottom" required>
                    <option value="Aguascalientes">Aguascalientes</option>
                    <option value="Baja California">Baja California</option>
                    <option value="Baja California Sur">Baja California Sur</option>
                    <option value="Campeche">Campeche</option>
                    <option value="Chiapas">Chiapas</option>
                    <option value="Chihuahua">Chihuahua</option>
                    <option value="Ciudad de Mexico">Ciudad de México</option>
                    <option value="Coahuila">Coahuila</option>
                    <option value="Colima">Colima</option>
                    <option value="Durango">Durango</option>
                    <option value="Estado de Mexico">Estado de México</option>
                    <option value="Guanajuato">Guanajuato</option>
                    <option value="Guerrero">Guerrero</option>
                    <option value="Hidalgo">Hidalgo</option>
                    <option value="Jalisco">Jalisco</option>
                    <option value="Michoacán">Michoacán</option>
                    <option value="Morelos">Morelos</option>
                    <option value="Nayarit">Nayarit</option>
                    <option value="Nuevo Leon">Nuevo León</option>
                    <option value="Oaxaca">Oaxaca</option>
                    <option value="Puebla">Puebla</option>
                    <option value="Queretaro">Querétaro</option>
                    <option value="Quintana Roo">Quintana Roo</option>
                    <option value="San Luis Potosi">San Luis Potosí</option>
                    <option value="Sinaloa">Sinaloa</option>
                    <option value="Sonora">Sonora</option>
                    <option value="Tabasco">Tabasco</option>
                    <option value="Tamaulipas">Tamaulipas</option>
                    <option value="Tlaxcala">Tlaxcala</option>
                    <option value="Veracruz" selected>Veracruz</option>
                    <option value="Yucatan">Yucatán</option>
                    <option value="Zacatecas">Zacatecas</option>
                </select>
                <label><b>Cliente</b></label>
                <select name="id_cliente" class="w3-select w3-border w3-margin-bottom" required>
                    <?php foreach ($clientes as $c): ?>
                        <option value="<?= esc($c['id']) ?>">
                            <?= esc($c['nombre'].' '.$c['ape_pat'].' '.$c['ape_mat']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <footer class="w3-container w3-green w3-padding">
                    <button type="submit" class="w3-button w3-white w3-right">Guardar</button>
                    <button type="button"
                            onclick="document.getElementById('modalDireccion').style.display='none'"
                            class="w3-button w3-white">Cancelar</button>
                </footer>
            </form>
        </div>
    </div>
    <div id="modalEntrada" class="w3-modal" style="padding-top:100px; z-index:9999;">
    <div class="w3-modal-content w3-animate-zoom" style="max-width:500px; max-height:90vh; overflow-y:auto;">
        <form action="<?= base_url('guarda_entrada') ?>" method="post" class="w3-container w3-padding-16">

            <label><b>Fecha de entrada</b></label>
            <input type="date" name="f_ent" class="w3-input w3-border w3-margin-bottom" required>

            <label><b>Fecha de caducidad</b></label>
            <input type="date" name="f_cad" class="w3-input w3-border w3-margin-bottom" required>

            <label><b>Cantidad</b></label>
            <input type="number" name="cant" class="w3-input w3-border w3-margin-bottom" required>

            <label><b>Unidad de compra</b></label>
            <select name="u_com" class="w3-select w3-border w3-margin-bottom" required>
                <option value="Caja">Caja</option>
                <option value="Arpilla">Arpilla</option>
                <option value="Bulto">Bulto</option>
                <option value="Tonelada">Tonelada</option>
            </select>

            <label><b>Unidad de venta</b></label>
            <select name="u_ven" class="w3-select w3-border w3-margin-bottom" required>
                <option value="Kilogramo">Kilogramo</option>
                <option value="Litro">Litro</option>
                <option value="Caja">Caja</option>
            </select>

            <label><b>Precio de compra</b></label>
            <input type="number" name="p_compra" class="w3-input w3-border w3-margin-bottom" required>

            <label><b>Producto</b></label>
            <select name="id_producto" class="w3-select w3-border w3-margin-bottom" required>
                <?php foreach ($productos as $p): ?>
                    <option value="<?= esc($p['id']) ?>"><?= esc($p['nombre']) ?></option>
                <?php endforeach; ?>
            </select>

            <footer class="w3-container w3-green w3-padding">
                <button type="submit" class="w3-button w3-white w3-right">Guardar</button>
                <button type="button"
                        onclick="document.getElementById('modalEntrada').style.display='none'"
                        class="w3-button w3-white">Cancelar</button>
            </footer>

        </form>
    </div>
</div>
<!-- MODAL PEDIDO -->
<div id="modalPedido" class="w3-modal" style="padding-top:100px; z-index:9999;">
    <div class="w3-modal-content w3-animate-zoom" style="max-width:500px; max-height:90vh; overflow-y:auto;">
        <form action="<?= base_url('guarda_pedido') ?>" method="post" class="w3-container w3-padding-16">

            <label><b>Fecha</b></label>
            <input type="date" name="fecha" class="w3-input w3-border w3-margin-bottom" required>

            <label><b>Cliente</b></label>
            <select name="id_cliente" class="w3-select w3-border w3-margin-bottom" required>
                <?php foreach ($clientes as $c): ?>
                    <option value="<?= esc($c['id']) ?>">
                        <?= esc($c['nombre'].' '.$c['ape_pat'].' '.$c['ape_mat']) ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <label><b>Repartidor</b></label>
            <select name="id_repartidor" class="w3-select w3-border w3-margin-bottom" required>
                <?php foreach ($repartidores as $r): ?>
                    <option value="<?= esc($r['id']) ?>">
                        <?= esc($r['nombre'].' '.$r['ape_pat'].' '.$r['ape_mat']) ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <footer class="w3-container w3-green w3-padding">
                <button type="submit" class="w3-button w3-white w3-right">Guardar</button>
                <button type="button"
                        onclick="document.getElementById('modalPedido').style.display='none'"
                        class="w3-button w3-white">Cancelar</button>
            </footer>

        </form>
    </div>
</div>
<!-- MODAL PRODUCTO -->
<div id="modalProducto" class="w3-modal" style="padding-top:100px; z-index:9999;">
    <div class="w3-modal-content w3-animate-zoom" style="max-width:500px; max-height:90vh; overflow-y:auto;">
        <form action="<?= base_url('guarda_producto') ?>" method="post" enctype="multipart/form-data" class="w3-container w3-padding-16">

            <label><b>Nombre</b></label>
            <input type="text" name="nom" placeholder="Ej: Tomate Saladet"
                   class="w3-input w3-border w3-margin-bottom" required>

            <label><b>Descripción</b></label>
            <textarea name="desc" rows="4"
                      class="w3-input w3-border w3-margin-bottom" required></textarea>

            <label><b>Categoría</b></label>
            <select name="cat" class="w3-select w3-border w3-margin-bottom" required>
                <option value="frutas">Frutas</option>
                <option value="verduras">Verdura</option>
                <option value="yerbas">Yerba</option>
            </select>

            <label><b>Imagen</b></label>
            <input type="file" name="img" class="w3-input w3-border w3-margin-bottom" required>

            <footer class="w3-container w3-green w3-padding">
                <button type="submit" class="w3-button w3-white w3-right">Guardar</button>
                <button type="button"
                        onclick="document.getElementById('modalProducto').style.display='none'"
                        class="w3-button w3-white">Cancelar</button>
            </footer>

        </form>
    </div>
</div>
<!-- MODAL REPARTIDOR -->
<div id="modalRepartidor" class="w3-modal" style="padding-top:100px; z-index:9999;">
    <div class="w3-modal-content w3-animate-zoom" style="max-width:500px; max-height:90vh; overflow-y:auto;">
        <form action="<?= base_url('guarda_repartidor') ?>" method="post" class="w3-container w3-padding-16">

            <label><b>Nombre</b></label>
            <input type="text" name="nom" class="w3-input w3-border w3-margin-bottom" required>

            <label><b>Apellido Paterno</b></label>
            <input type="text" name="ape_pat" class="w3-input w3-border w3-margin-bottom" required>

            <label><b>Apellido Materno</b></label>
            <input type="text" name="ape_mat" class="w3-input w3-border w3-margin-bottom" required>

            <label><b>Teléfono</b></label>
            <input type="text" name="tel" class="w3-input w3-border w3-margin-bottom" required>

            <label><b>Dirección</b></label>
            <input type="text" name="dir" class="w3-input w3-border w3-margin-bottom" required>

            <label><b>Notas</b></label>
            <textarea name="not" rows="4"
                      class="w3-input w3-border w3-margin-bottom" required></textarea>

            <footer class="w3-container w3-green w3-padding">
                <button type="submit" class="w3-button w3-white w3-right">Guardar</button>
                <button type="button"
                        onclick="document.getElementById('modalRepartidor').style.display='none'"
                        class="w3-button w3-white">Cancelar</button>
            </footer>

        </form>
    </div>
</div>
<!-- MODAL ESTATUS -->
<div id="modalEstatus" class="w3-modal" style="padding-top:100px; z-index:9999;">
    <div class="w3-modal-content w3-animate-zoom" style="max-width:500px; max-height:90vh; overflow-y:auto;">
        <form action="<?= base_url('guarda_estatus') ?>" method="post" class="w3-container w3-padding-16">

            <label><b>Estado</b></label>
            <select name="edo" class="w3-select w3-border w3-margin-bottom" required>
                <option value="pedido_realizado">Pedido realizado</option>
                <option value="pedido_confirmado">Pedido confirmado</option>
                <option value="pedido_en_transito">Pedido en tránsito</option>
                <option value="pedido_entregado">Pedido entregado</option>
                <option value="pedido_a_credito">Pedido a crédito</option>
                <option value="pedido_pagado">Pedido pagado</option>
                <option value="pedido_cancelado">Pedido cancelado</option>
            </select>

            <label><b>Fecha</b></label>
            <input type="datetime-local" name="fecha"
                   class="w3-input w3-border w3-margin-bottom" required>

            <label><b>Pedido</b></label>
            <select name="id_pedido" class="w3-select w3-border w3-margin-bottom" required>
                <?php foreach ($pedidos as $p): ?>
                    <option value="<?= $p['id'] ?>"><?= $p['id'] ?></option>
                <?php endforeach; ?>
            </select>

            <footer class="w3-container w3-green w3-padding">
                <button type="submit" class="w3-button w3-white w3-right">Guardar</button>
                <button type="button"
                        onclick="document.getElementById('modalEstatus').style.display='none'"
                        class="w3-button w3-white">Cancelar</button>
            </footer>

        </form>
    </div>
</div>
<!-- MODAL EXISTENCIA -->
<div id="modalExistencia" class="w3-modal" style="padding-top:100px; z-index:9999;">
    <div class="w3-modal-content w3-animate-zoom" style="max-width:500px; max-height:90vh; overflow-y:auto;">
        <form action="<?= base_url('guarda_existencia') ?>" method="post" class="w3-container w3-padding-16">

            <label><b>Existencias totales</b></label>
            <input type="number" name="e_total"
                   class="w3-input w3-border w3-margin-bottom" required>

            <label><b>Existencias bloqueadas</b></label>
            <input type="number" name="e_bloqueado"
                   class="w3-input w3-border w3-margin-bottom" required>

            <label><b>Existencias para venta</b></label>
            <input type="number" name="e_venta"
                   class="w3-input w3-border w3-margin-bottom" required>

            <label><b>Fecha</b></label>
            <input type="datetime-local" name="fecha"
                   class="w3-input w3-border w3-margin-bottom" required>

            <label><b>Producto</b></label>
            <select name="id_producto" class="w3-select w3-border w3-margin-bottom" required>
                <?php foreach ($productos as $p): ?>
                    <option value="<?= $p['id'] ?>"><?= esc($p['nombre']) ?></option>
                <?php endforeach; ?>
            </select>

            <footer class="w3-container w3-green w3-padding">
                <button type="submit" class="w3-button w3-white w3-right">Guardar</button>
                <button type="button"
                        onclick="document.getElementById('modalExistencia').style.display='none'"
                        class="w3-button w3-white">Cancelar</button>
            </footer>

        </form>
    </div>
</div>
<!-- MODAL MERMA -->
<div id="modalMerma" class="w3-modal" style="padding-top:100px; z-index:9999;">
    <div class="w3-modal-content w3-animate-zoom" style="max-width:500px; max-height:90vh; overflow-y:auto;">
        <form action="<?= base_url('guarda_merma') ?>" method="post" class="w3-container w3-padding-16">

            <label><b>Cantidad</b></label>
            <input type="number" name="cant"
                   class="w3-input w3-border w3-margin-bottom" required>

            <label><b>Fecha</b></label>
            <input type="date" name="fecha"
                   class="w3-input w3-border w3-margin-bottom" required>

            <label><b>Notas</b></label>
            <textarea name="notas" rows="4"
                      class="w3-input w3-border w3-margin-bottom"></textarea>

            <label><b>Entrada</b></label>
            <select name="id_entrada" class="w3-select w3-border w3-margin-bottom" required>
                <?php foreach ($entradas as $e): ?>
                    <option value="<?= $e['id'] ?>"><?= $e['id'] ?></option>
                <?php endforeach; ?>
            </select>

            <footer class="w3-container w3-green w3-padding">
                <button type="submit" class="w3-button w3-white w3-right">Guardar</button>
                <button type="button"
                        onclick="document.getElementById('modalMerma').style.display='none'"
                        class="w3-button w3-white">Cancelar</button>
            </footer>

        </form>
    </div>
</div>
<!-- MODAL P_PEDIDO -->
<div id="modalPPedido" class="w3-modal" style="padding-top:100px; z-index:9999;">
    <div class="w3-modal-content w3-animate-zoom" style="max-width:500px; max-height:90vh; overflow-y:auto;">
        <form action="<?= base_url('guarda_p_pedido') ?>" method="post" class="w3-container w3-padding-16">

            <label><b>Cantidad</b></label>
            <input type="number" name="cant"
                   class="w3-input w3-border w3-margin-bottom" required>

            <label><b>Precio de venta</b></label>
            <input type="number" name="p_venta" step="0.01"
                   class="w3-input w3-border w3-margin-bottom" required>

            <label><b>Unidad de venta</b></label>
            <select name="u_venta" class="w3-select w3-border w3-margin-bottom" required>
                <option value="Kilogramo">Kilogramo</option>
                <option value="Domo">Domo</option>
                <option value="Ramos">Ramo</option>
                <option value="Caja">Caja</option>
            </select>

            <label><b>Total</b></label>
            <input type="number" name="tot" step="0.01"
                   class="w3-input w3-border w3-margin-bottom" required>

            <label><b>Pedido</b></label>
            <select name="id_pedido" class="w3-select w3-border w3-margin-bottom" required>
                <?php foreach ($pedidos as $p): ?>
                    <option value="<?= $p['id'] ?>"><?= $p['id'] ?></option>
                <?php endforeach; ?>
            </select>

            <label><b>Producto</b></label>
            <select name="id_producto" class="w3-select w3-border w3-margin-bottom" required>
                <?php foreach ($productos as $pr): ?>
                    <option value="<?= $pr['id'] ?>"><?= esc($pr['nombre']) ?></option>
                <?php endforeach; ?>
            </select>

            <footer class="w3-container w3-green w3-padding">
                <button type="submit" class="w3-button w3-white w3-right">Guardar</button>
                <button type="button"
                        onclick="document.getElementById('modalPPedido').style.display='none'"
                        class="w3-button w3-white">Cancelar</button>
            </footer>

        </form>
    </div>
</div>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="<?= base_url('js/dashboard.js') ?>"></script>
    <script>
   window.onclick = function(event) {
    const modalCliente = document.getElementById('modalCliente');
    const modalDireccion = document.getElementById('modalDireccion');
    const modalEntrada = document.getElementById('modalEntrada');
    const modalPedido = document.getElementById('modalPedido');
    const modalProducto = document.getElementById('modalProducto');
    const modalRepartidor = document.getElementById('modalRepartidor');
    const modalEstatus = document.getElementById('modalEstatus');
    const modalExistencia = document.getElementById('modalExistencia');
    const modalMerma = document.getElementById('modalMerma');
    const modalPPedido = document.getElementById('modalPPedido');
    if (event.target === modalCliente) modalCliente.style.display = 'none';
    if (event.target === modalDireccion) modalDireccion.style.display = 'none';
    if (event.target === modalEntrada) modalEntrada.style.display = 'none';
    if (event.target === modalPedido) modalPedido.style.display = 'none';
    if (event.target === modalProducto) modalProducto.style.display = 'none';
    if (event.target === modalRepartidor) modalRepartidor.style.display = 'none';
    if (event.target === modalEstatus) modalEstatus.style.display = 'none';
    if (event.target === modalExistencia) modalExistencia.style.display = 'none';
    if (event.target === modalMerma) modalMerma.style.display = 'none';
    if (event.target === modalPPedido) modalPPedido.style.display = 'none';
};
    </script>

</body>
</html>