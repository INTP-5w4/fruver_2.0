<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <?= view('partials/estilos_panel') ?>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mermas · Fruver</title>
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
    <h1>Mermas</h1>
    <button onclick="document.getElementById('modalCrearMerma').style.display='block'"
            class="btn-agregar">
        <i class="fa-solid fa-plus" aria-hidden="true"></i> Nueva merma
    </button>
</div>

<?= view('partials/buscador_lista', ['ruta' => 'lista_merma', 'buscar' => $buscar ?? '', 'placeholder' => 'Buscar por producto, notas, fecha o número…']) ?>
<div class="tabla-wrapper" data-busqueda-resultados>
<table>
    <thead>
        <tr>
            <th>ID</th>
            <th>Cantidad</th>
            <th>Fecha</th>
            <th>Notas</th>
            <th>Entrada</th>
            <th>Unidad de Venta</th>
            <th>Editar</th>
            <th>Eliminar</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($mermas as $m): ?>
            <?php
                $entradaEncontrada = array_filter($entradas, fn($e) => $e['id'] == $m['id_entrada']);
                $entradaEncontrada = reset($entradaEncontrada);
            ?>
            <tr>
                <td><?= esc($m['id']) ?></td>
                <td><?= esc($m['cantidad']) ?></td>
                <td><?= esc($m['fecha']) ?></td>
                <td><?= esc($m['notas']) ?></td>

                <td>
                    <?= esc($entradaEncontrada
                        ? '#'.$entradaEncontrada['id'].' — '.esc($entradaEncontrada['nombre_producto']).' ('.$entradaEncontrada['fecha'].')'
                        : 'N/A') ?>
                </td>

                <td>
                    <?= esc($entradaEncontrada ? esc($entradaEncontrada['u_venta']) : 'N/A') ?>
                </td>

                <td>
                    <button onclick="abrirModal(
                                '<?= esc($m['id'], 'js') ?>',
                                '<?= esc($m['cantidad'], 'js') ?>',
                                '<?= esc($m['fecha'], 'js') ?>',
                                '<?= esc($m['notas'], 'js') ?>',
                                '<?= esc($m['id_entrada'], 'js') ?>'
                            )"
                            style="border:none; cursor:pointer; background:none;" aria-label="Editar" title="Editar">
                        <i class="fa-solid fa-pen-to-square" aria-hidden="true"></i>
                    </button>
                </td>
                <td>
                    <form method="post" action="<?= base_url('borra_id_merma/' . (int) $m['id']) ?>" class="form-borrar" style="display:inline; margin:0;" onsubmit="return confirm('¿Estás seguro de que quieres eliminar esta merma?');">
<?= csrf_field() ?>
<button type="submit" style="border:none; cursor:pointer; background:none;" aria-label="Eliminar" title="Eliminar"><i class="fa-solid fa-trash-can" aria-hidden="true"></i></button>
</form>
                </td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>
<?= $pager->links('default', 'w3_pager') ?>
</div>
</div> 

<div id="modalCrearMerma" class="w3-modal" style="padding-top:100px;z-index:9999;">
    <div class="w3-modal-content w3-animate-zoom" style="max-width:500px;max-height:90vh;overflow-y:auto;">
        <form action="<?= base_url('guarda_merma') ?>" method="post" class="w3-container w3-padding-16">
<?= csrf_field() ?>
            <?php
            // Se piensa en PRODUCTO, no en «entrada #id»: mapa de cada producto con existencias a
            // su entrada (lote) más reciente. El <datalist> deja buscar escribiendo.
            $mermaProductos = [];
            foreach ($entradas as $e) {
                if (($stockPorProducto[$e['id_producto']] ?? 0) <= 0) { continue; }
                $k = (int) $e['id_producto'];
                if (! isset($mermaProductos[$k]) || (int) $e['id'] > (int) $mermaProductos[$k]['id_entrada']) {
                    $mermaProductos[$k] = [
                        'id_entrada' => (int) $e['id'],
                        'nombre'     => $e['nombre_producto'],
                        'u_venta'    => $e['u_venta'],
                        'fecha'      => $e['fecha'],
                    ];
                }
            }
            $mermaPorNombre = [];
            foreach ($mermaProductos as $prod) { $mermaPorNombre[$prod['nombre']] = $prod; }
            ?>
            <label for="merma-producto"><b>Producto*</b></label>
            <input list="merma-lista-productos" id="merma-producto" name="producto_nombre"
                   class="w3-input w3-border w3-margin-bottom" autocomplete="off"
                   placeholder="Escribe o elige un producto…" required>
            <datalist id="merma-lista-productos">
                <?php foreach ($mermaProductos as $prod): ?>
                    <option value="<?= esc($prod['nombre'], 'attr') ?>"></option>
                <?php endforeach; ?>
            </datalist>
            <input type="hidden" name="id_entrada" id="id_entrada_modal">
            <p id="merma-entrada-info" class="w3-small w3-text-grey" aria-live="polite"></p>

            <input type="hidden" id="u_venta_modal" name="u_venta">

            <label for="merma-cant"><b>Cantidad*</b></label>
            <input id="merma-cant" type="number" placeholder="Ej: 45" name="cant" class="w3-input w3-border w3-margin-bottom" min="1" required>

            <label for="merma-fecha"><b>Fecha*</b></label>
            <input id="merma-fecha" type="date" name="fecha" class="w3-input w3-border w3-margin-bottom" value="<?= date('Y-m-d') ?>" required>

            <label for="merma-notas"><b>Notas</b></label>
            <textarea id="merma-notas" name="notas" placeholder="Ej: Caducó durante el transporte" rows="3" class="w3-input w3-border w3-margin-bottom"></textarea>

            <footer class="w3-container w3-green w3-padding">
                <button type="submit" class="w3-button w3-white w3-right"><i class="fa-solid fa-floppy-disk" aria-hidden="true"></i> Guardar</button>
                <button type="button"
                        onclick="document.getElementById('modalCrearMerma').style.display='none'"
                        class="w3-button w3-white"><i class="fa-solid fa-xmark" aria-hidden="true"></i> Cancelar</button>
            </footer>
        </form>
    </div>
</div>

<div id="modalEditarMerma" class="w3-modal" style="padding-top:100px; z-index:9999;">
    <div class="w3-modal-content w3-animate-zoom" style="max-width:500px; max-height:90vh; overflow-y:auto;">
        <form action="<?= base_url('modifica_merma') ?>" method="post" class="w3-container w3-padding-16">
<?= csrf_field() ?>

            <input type="hidden" name="id" id="edit_id">

            <label for="edit_cantidad"><b>Cantidad</b></label>
            <input type="number" name="cantidad" id="edit_cantidad"
                   class="w3-input w3-border w3-margin-bottom" required>

            <label for="edit_fecha"><b>Fecha</b></label>
            <input type="date" name="fecha" id="edit_fecha"
                   class="w3-input w3-border w3-margin-bottom" required>

            <label for="edit_notas"><b>Notas</b></label>
            <textarea name="notas" id="edit_notas" rows="4"
                      class="w3-input w3-border w3-margin-bottom"></textarea>

            <label for="edit_id_entrada"><b>Entrada</b></label>
            <select name="id_entrada" id="edit_id_entrada"
                    class="w3-select w3-border w3-margin-bottom" required>
                <?php foreach ($entradas as $e): ?>
                    <?php $tieneStock = ($stockPorProducto[$e['id_producto']] ?? 0) > 0; ?>
                    <option value="<?= esc($e['id']) ?>" <?= !$tieneStock ? 'data-sin-stock="true" style="display:none;"' : '' ?>>
                        #<?= esc($e['id']) ?> — <?= esc($e['nombre_producto']) ?> (<?= esc($e['fecha']) ?>)
                    </option>
                <?php endforeach; ?>
            </select>

            <footer class="w3-container w3-green w3-padding">
                <button type="submit" class="w3-button w3-white w3-right"><i class="fa-solid fa-floppy-disk" aria-hidden="true"></i> Guardar</button>
                <button type="button"
                        onclick="document.getElementById('modalEditarMerma').style.display='none'"
                        class="w3-button w3-white"><i class="fa-solid fa-xmark" aria-hidden="true"></i> Cancelar</button>
            </footer>

        </form>
    </div>
</div>
<?php include 'Footer.php'; ?>

<script>
    function abrirModal(id, cantidad, fecha, notas, id_entrada) {
        document.getElementById('edit_id').value = id;
        document.getElementById('edit_cantidad').value = cantidad;
        document.getElementById('edit_fecha').value = fecha;
        document.getElementById('edit_notas').value = notas;
        
        const selectEdit = document.getElementById('edit_id_entrada');
        
        // 1. Ocultar todas las opciones sin stock por defecto
        Array.from(selectEdit.options).forEach(opt => {
            if (opt.getAttribute('data-sin-stock') === 'true') {
                opt.style.display = 'none';
            }
        });

        // 2. Asignar el valor que tiene la merma en la base de datos
        selectEdit.value = id_entrada;
        
        // 3. Asegurar que la entrada histórica sea visible (aunque su stock actual sea 0)
        if (selectEdit.options[selectEdit.selectedIndex]) {
            selectEdit.options[selectEdit.selectedIndex].style.display = '';
        }

        document.getElementById('modalEditarMerma').style.display = 'block';
    }


    // Buscador
    function filtrar() {
        const texto = document.getElementById('buscador').value.toLowerCase();
        const filas = document.querySelectorAll('tbody tr');
        filas.forEach(function(fila) {
            const celdaEntrada = fila.cells[4].textContent.toLowerCase();
            fila.style.display = celdaEntrada.includes(texto) ? '' : 'none';
        });
    }

    // Crear merma: al elegir un producto se resuelve su entrada (lote) más reciente con
    // existencias y se llenan el id_entrada oculto y la unidad.
    (function () {
        const mapa   = <?= json_seguro($mermaPorNombre) ?>; // nombre → {id_entrada, u_venta, fecha, nombre}
        const input  = document.getElementById('merma-producto');
        const oculto = document.getElementById('id_entrada_modal');
        const unidad = document.getElementById('u_venta_modal');
        const info   = document.getElementById('merma-entrada-info');
        if (!input) { return; }
        const form = input.closest('form');

        function resolver() {
            const prod = mapa[input.value.trim()];
            if (prod) {
                oculto.value = String(prod.id_entrada);
                unidad.value = prod.u_venta || '';
                info.textContent = 'Lote más reciente con existencias: entrada #' + prod.id_entrada + ' · ' + prod.fecha + '.';
                info.classList.remove('w3-text-red');
            } else {
                oculto.value = '';
                unidad.value = '';
                info.textContent = input.value.trim() ? 'Elige un producto de la lista.' : '';
                info.classList.toggle('w3-text-red', input.value.trim() !== '');
            }
        }

        input.addEventListener('input', resolver);
        input.addEventListener('change', resolver);
        if (form) {
            form.addEventListener('submit', function (ev) {
                resolver();
                if (!oculto.value) { ev.preventDefault(); input.focus(); }
            });
        }
    })();
</script>

</body>
</html>