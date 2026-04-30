<!DOCTYPE html>
<html lang="en">
<head>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/7.0.1/css/all.min.css" integrity="sha512-2SwdPD6INVrV/lHTZbO2nodKhrnDdJK9/kg2XD1r9uGqPo1cUbujc+IYdlYdEErWNu69gVcYgdxlmVmzTWnetw==" crossorigin="anonymous" referrerpolicy="no-referrer" />    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://www.w3schools.com/w3css/5/w3.css">
    <link rel="stylesheet" href="<?= base_url('estilos/estilosPaginas.css') ?>">
    <title>Lista Entrada</title>
</head>
<body>

<div class="contenedor-boton">
    <button onclick="document.getElementById('modalCrearEntrada').style.display='block'"
            class="btn-agregar">
        + Nueva Entrada
    </button>
</div>

    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Fecha de entrada</th>
                <th>Fecha de caducidad</th>
                <th>Cantidad</th>
                <th>Unidad de compra</th>
                <th>Equivalente</th>
                <th>Conversión</th>
                <th>Unidad de venta</th>
                <th>Precio compra</th>
                <th>Nombre del producto</th>
                <th>Editar</th>
                <th>Eliminar</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach($entradas as $e): ?>
            <tr>
                <td><?= $e['id'] ?></td>
                <td><?= $e['fecha'] ?></td>
                <td><?= $e['fecha_cad'] ?></td>
                <td><?= $e['cantidad'] ?></td>
                <td><?= $e['u_compra'] ?></td>
                <td><?= $e['equivalente'] ?></td>
                <td><?= $e['conversion'] ?></td>
                <td><?= $e['u_venta'] ?></td>
                <td><?= $e['precio_compra_u'] ?></td>
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
                                '<?= $e['precio_compra_u'] ?>',
                                '<?= $e['id_producto'] ?>'
                            )"
                            class="btn-icono">
                        <i class="fa-solid fa-pen-to-square"></i>
                    </button>
                </td>
                <td>
                    <a href="<?= base_url('borra_id_entrada/'.$e['id']) ?>">
                        <button class="btn-icono">
                            <i class="fa-solid fa-trash-can"></i>
                        </button>
                    </a>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <!-- MODAL CREAR ENTRADA -->
    <div id="modalCrearEntrada" class="w3-modal" style="display:none;">
        <div class="modal-contenido w3-animate-zoom">

            <header class="modal-header">
                <span onclick="document.getElementById('modalCrearEntrada').style.display='none'"
                    class="modal-cerrar">&times;</span>
                <h2>Registrar Entrada</h2>
            </header>

            <form action="<?= base_url('guarda_entrada') ?>" method="post" class="modal-form">

                <label><b>Fecha de entrada</b></label>
                <input type="date" name="f_ent" class="modal-input" required>

                <label><b>Fecha de caducidad</b></label>
                <input type="date" name="f_cad" class="modal-input">

                <label><b>Producto</b></label>
                <select name="id_producto" class="modal-input" required>
                    <?php foreach ($productos as $p): ?>
                        <option value="<?= $p['id'] ?>"><?= $p['nombre'] ?></option>
                    <?php endforeach; ?>
                </select>

                <label><b>Cantidad</b></label>
                <input type="number" name="cant" class="modal-input" required>

                <label><b>Unidad de compra</b></label>
                <select name="u_com" class="modal-input" required>
                     <option value="Caja">Caja</option>
                    <option value="Arpilla">Arpilla</option>
                    <option value="Bulto">Bulto</option>
                    <option value="Tonelada">Tonelada</option>
                    <option value="Mazo">Mazo</option>
                </select>

                <label><b>Equivalente</b></label>
                <input type="number" name="equi" class="modal-input" required>


                <label><b>Unidad de venta</b></label>
                <select name="u_ven" class="modal-input" required>
                    <option value="Kilogramo">Kilogramo</option>
                    <option value="Litro">Litro</option>
                    <option value="Caja">Caja</option>
                    <option value="Pieza">Pieza</option>
                    <option value="Domo">Domo</option>
                </select>

                <label><b>Precio de compra</b></label>
                <input type="number" name="p_compra" class="modal-input" required>

                <footer class="modal-footer">
                    <button type="submit" class="btn-guardar">Guardar</button>
                    <button type="button"
                            onclick="document.getElementById('modalCrearEntrada').style.display='none'"
                            class="btn-cancelar">Cancelar</button>
                </footer>

            </form>
        </div>
    </div>

    <!-- MODAL EDITAR ENTRADA -->
    <div id="modalEditarEntrada" class="w3-modal" style="display:none;">
        <div class="modal-contenido w3-animate-zoom">

            <header class="modal-header">
                <span onclick="document.getElementById('modalEditarEntrada').style.display='none'"
                      class="modal-cerrar">&times;</span>
                <h2>Editar Entrada</h2>
            </header>

            <form action="<?= base_url('modifica_entrada') ?>" method="post" class="modal-form">

                <input type="hidden" name="id" id="edit_id">

                <label><b>Fecha de entrada</b></label>
                <input type="date" name="f_ent" id="edit_f_ent" class="modal-input" required>

                <label><b>Fecha de caducidad</b></label>
                <input type="date" name="f_cad" id="edit_f_cad" class="modal-input">

                <label><b>Cantidad</b></label>
                <input type="number" name="cant" id="edit_cant" class="modal-input" required>

                <label><b>Unidad de compra</b></label>
                <select name="u_com" id="edit_u_com" class="modal-input" required>
                    <option value="Caja">Caja</option>
                    <option value="Arpilla">Arpilla</option>
                    <option value="Bulto">Bulto</option>
                    <option value="Tonelada">Tonelada</option>
                    <option value="Mazo">Mazo</option>
                </select>


                <label><b>Unidad de venta</b></label>
                <select name="u_ven" id="edit_u_ven" class="modal-input" required>
                    <option value="Kilogramo">Kilogramo</option>
                    <option value="Litro">Litro</option>
                    <option value="Caja">Caja</option>
                    <option value="Pieza">Pieza</option>
                    <option value="Domo">Domo</option>
                </select>
                <label for="">Equivalente</label>
                <input type="number" name="equi" id="">

                <label><b>Precio de compra</b></label>
                <input type="number" name="p_compra" id="edit_precio_compra" class="modal-input" required>

                <label><b>Producto</b></label>
                <select name="id_producto" id="edit_id_producto" class="modal-input" required>
                    <?php foreach ($productos as $p): ?>
                        <option value="<?= $p['id'] ?>"><?= $p['nombre'] ?></option>
                    <?php endforeach; ?>
                </select>

                <footer class="modal-footer">
                    <input type="submit" value="enviar" class="btn-guardar">

                    <button type="button"
                            onclick="document.getElementById('modalEditarEntrada').style.display='none'"
                            class="btn-cancelar">Cancelar</button>
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
            const modalEditar = document.getElementById('modalEditarEntrada');
            const modalCrear = document.getElementById('modalCrearEntrada');
            if (event.target === modalEditar) modalEditar.style.display = 'none';
            if (event.target === modalCrear) modalCrear.style.display = 'none';
        };
    </script>

</body>
</html>