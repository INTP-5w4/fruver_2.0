<!DOCTYPE html>
<html lang="en">
<head>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/7.0.1/css/all.min.css" integrity="sha512-2SwdPD6INVrV/lHTZbO2nodKhrnDdJK9/kg2XD1r9uGqPo1cUbujc+IYdlYdEErWNu69gVcYgdxlmVmzTWnetw==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://www.w3schools.com/w3css/5/w3.css">
    <link rel="stylesheet" href="<?= base_url('estilos/estilosPaginas.css') ?>">
    <title>Lista merma</title>
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
    <button onclick="document.getElementById('modalCrearMerma').style.display='block'"
            class="btn-agregar">
        + Nueva Merma
    </button>
</div>

<!-- Buscador -->
<input type="text" id="buscador"
       onkeyup="filtrar()"
       placeholder="Buscar por producto..."
       class="w3-input w3-border w3-margin-bottom"
       style="max-width:300px;">

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
                <td><?= $m['id'] ?></td>
                <td><?= $m['cantidad'] ?></td>
                <td><?= $m['fecha'] ?></td>
                <td><?= esc($m['notas']) ?></td>

                <!-- Entrada con nombre del producto -->
                <td>
                    <?= $entradaEncontrada
                        ? '#'.$entradaEncontrada['id'].' — '.esc($entradaEncontrada['nombre_producto']).' ('.$entradaEncontrada['fecha'].')'
                        : 'N/A' ?>
                </td>

                <!-- Unidad de venta -->
                <td>
                    <?= $entradaEncontrada ? esc($entradaEncontrada['u_venta']) : 'N/A' ?>
                </td>

                <td>
                    <button onclick="abrirModal(
                                '<?= esc($m['id']) ?>',
                                '<?= esc($m['cantidad']) ?>',
                                '<?= esc($m['fecha']) ?>',
                                '<?= esc($m['notas']) ?>',
                                '<?= esc($m['id_entrada']) ?>'
                            )"
                            style="border:none; cursor:pointer; background:none;">
                        <i class="fa-solid fa-pen-to-square"></i>
                    </button>
                </td>
                <td>
                    <a href="<?= base_url('borra_id_merma/'.$m['id']) ?>"
                       onclick="return confirm('¿Estás seguro de que quieres eliminar esta merma?')">
                        <button style="border:none; cursor:pointer; background:none;">
                            <i class="fa-solid fa-trash-can"></i>
                        </button>
                    </a>
                </td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>

<div id="modalCrearMerma" class="w3-modal" style="padding-top:100px;z-index:9999;">
    <div class="w3-modal-content w3-animate-zoom" style="max-width:500px;max-height:90vh;overflow-y:auto;">
        <form action="<?= base_url('guarda_merma') ?>" method="post" class="w3-container w3-padding-16">
            <input type="hidden" name="origen" value="main_page">
            <label><b>Entrada</b></label>
            <select name="id_entrada" id="id_entrada_modal" class="w3-select w3-border w3-margin-bottom" required>
                <option value="">-- Selecciona una entrada --</option>
                <?php foreach ($entradas as $entrada): ?>
                    <option value="<?= $entrada['id'] ?>">
                        #<?= $entrada['id'] ?> — <?= esc($entrada['nombre_producto']) ?> (<?= $entrada['fecha'] ?>)
                    </option>
                <?php endforeach; ?>
            </select>

            <label><b>Unidad de venta</b></label>
            <input type="text" id="u_venta_modal" name="u_venta" class="w3-input w3-border w3-margin-bottom" readonly placeholder="Se llena automáticamente">

            <label><b>Cantidad</b></label>
            <input type="number" name="cant" class="w3-input w3-border w3-margin-bottom" required>

            <label><b>Fecha</b></label>
            <input type="date" name="fecha" class="w3-input w3-border w3-margin-bottom" value="<?= date('Y-m-d') ?>" required>

            <label><b>Notas</b></label>
            <textarea name="notas" rows="3" class="w3-input w3-border w3-margin-bottom"></textarea>

            

            <footer class="w3-container w3-green w3-padding">
                <button type="submit" class="w3-button w3-white w3-right">Guardar</button>
                <button type="button"
                        onclick="document.getElementById('modalCrearMerma').style.display='none'"
                        class="w3-button w3-white">Cancelar</button>
            </footer>

        </form>
    </div>
</div>
<!-- MODAL EDITAR MERMA -->
<div id="modalEditarMerma" class="w3-modal" style="padding-top:100px; z-index:9999;">
    <div class="w3-modal-content w3-animate-zoom" style="max-width:500px; max-height:90vh; overflow-y:auto;">
        <form action="<?= base_url('modifica_merma') ?>" method="post" class="w3-container w3-padding-16">

            <input type="hidden" name="id" id="edit_id">

            <label><b>Cantidad</b></label>
            <input type="number" name="cantidad" id="edit_cantidad"
                   class="w3-input w3-border w3-margin-bottom" required>

            <label><b>Fecha</b></label>
            <input type="date" name="fecha" id="edit_fecha"
                   class="w3-input w3-border w3-margin-bottom" required>

            <label><b>Notas</b></label>
            <textarea name="notas" id="edit_notas" rows="4"
                      class="w3-input w3-border w3-margin-bottom"></textarea>

            <label><b>Entrada</b></label>
            <select name="id_entrada" id="edit_id_entrada"
                    class="w3-select w3-border w3-margin-bottom" required>
                <?php foreach ($entradas as $e): ?>
                    <option value="<?= $e['id'] ?>">
                        #<?= $e['id'] ?> — <?= esc($e['nombre_producto']) ?> (<?= $e['fecha'] ?>)
                    </option>
                <?php endforeach; ?>
            </select>

            <footer class="w3-container w3-green w3-padding">
                <button type="submit" class="w3-button w3-white w3-right">Guardar</button>
                <button type="button"
                        onclick="document.getElementById('modalEditarMerma').style.display='none'"
                        class="w3-button w3-white">Cancelar</button>
            </footer>

        </form>
    </div>
</div>

<script>
    function abrirModal(id, cantidad, fecha, notas, id_entrada) {
        document.getElementById('edit_id').value = id;
        document.getElementById('edit_cantidad').value = cantidad;
        document.getElementById('edit_fecha').value = fecha;
        document.getElementById('edit_notas').value = notas;
        document.getElementById('edit_id_entrada').value = id_entrada;
        document.getElementById('modalEditarMerma').style.display = 'block';
    }

    window.onclick = function(event) {
        const modal = document.getElementById('modalEditarMerma');
        if (event.target === modal) modal.style.display = 'none';
    };

    // Buscador por nombre de producto en columna Entrada (índice 4)
    function filtrar() {
        const texto = document.getElementById('buscador').value.toLowerCase();
        const filas = document.querySelectorAll('tbody tr');
        filas.forEach(function(fila) {
            const celdaEntrada = fila.cells[4].textContent.toLowerCase();
            fila.style.display = celdaEntrada.includes(texto) ? '' : 'none';
        });
    }
</script>

</body>
</html>