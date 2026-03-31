<!DOCTYPE html>
<html lang="en">
<head>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/7.0.1/css/all.min.css" integrity="sha512-2SwdPD6INVrV/lHTZbO2nodKhrnDdJK9/kg2XD1r9uGqPo1cUbujc+IYdlYdEErWNu69gVcYgdxlmVmzTWnetw==" crossorigin="anonymous" referrerpolicy="no-referrer" />    <meta charset="UTF-8">    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
     <link rel="stylesheet" href="https://www.w3schools.com/w3css/5/w3.css">
    <title>Lista direccion</title>
</head>
<body>
    <table>
        <thead>
            <th>ID</th>
            <th>Colonia</th>
            <th>Calle</th>
            <th>Numero</th>
            <th>Municipio</th>
            <th>Estado</th>
            <th>Nombre Cliente</th>
            <th>Editar</th>
            <th>Eliminar</th>
        </thead>
        <tbody>
            <?php foreach ($direcciones as $d):?>
                <tr>
                    <td><?= $d['id'] ?></td>
                    <td><?= $d['colonia'] ?></td>
                    <td><?= $d['calle'] ?></td>
                    <td><?= $d['numero'] ?></td>
                    <td><?= $d['municipio'] ?></td>
                    <td><?= $d['estado'] ?></td>
                    <td>
                        <?php $c = $clientes[$d['id_cliente']] ?? null;
                        echo $c ? "{$c['nombre']} {$c['ape_pat']} {$c['ape_mat']}" : 'Desconocido'; ?>
                    </td>
                    <td>
                        <button onclick="abrirModal(
                                    '<?= $d['id'] ?>',
                                    '<?= esc($d['colonia']) ?>',
                                    '<?= esc($d['calle']) ?>',
                                    '<?= esc($d['numero']) ?>',
                                    '<?= esc($d['municipio']) ?>',
                                    '<?= esc($d['estado']) ?>',
                                    '<?= $d['id_cliente'] ?>'
                                )"
                                style="border:none; cursor:pointer; background:none;">
                            <i class="fa-solid fa-pen-to-square"></i>
                        </button>
                    </td>
                    <td>
                        <a href="<?= base_url('borra_id_direccion/'.$d['id']) ?>">
                            <button style="border:none; cursor:pointer; background:none;">
                                <i class="fa-solid fa-trash-can"></i>
                            </button>
                        </a>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <!-- MODAL EDITAR DIRECCIÓN -->
    <div id="modalEditarDireccion" class="w3-modal" style="padding-top:100px; z-index:9999;">
        <div class="w3-modal-content w3-animate-zoom" style="max-width:500px; max-height:90vh; overflow-y:auto;">
            <form action="<?= base_url('modifica_direccion') ?>" method="post" class="w3-container w3-padding-16">

                <input type="hidden" name="id" id="edit_id">

                <label><b>Colonia</b></label>
                <input type="text" name="col" id="edit_col"
                       class="w3-input w3-border w3-margin-bottom" required>

                <label><b>Calle</b></label>
                <input type="text" name="calle" id="edit_calle"
                       class="w3-input w3-border w3-margin-bottom" required>

                <label><b>Número</b></label>
                <input type="text" name="num" id="edit_num"
                       class="w3-input w3-border w3-margin-bottom" required>

                <label><b>Municipio</b></label>
                <input type="text" name="mun" id="edit_mun"
                       class="w3-input w3-border w3-margin-bottom" required>

                <label><b>Estado</b></label>
                <select name="edo" id="edit_edo" class="w3-select w3-border w3-margin-bottom" required>
                    <?php
                    $estados = ["Aguascalientes","Baja California","Baja California Sur","Campeche","Chiapas","Chihuahua","Ciudad de Mexico","Coahuila","Colima","Durango","Estado de Mexico","Guanajuato","Guerrero","Hidalgo","Jalisco","Michoacán","Morelos","Nayarit","Nuevo Leon","Oaxaca","Puebla","Queretaro","Quintana Roo","San Luis Potosi","Sinaloa","Sonora","Tabasco","Tamaulipas","Tlaxcala","Veracruz","Yucatan","Zacatecas"];
                    foreach ($estados as $e): ?>
                        <option value="<?= $e ?>"><?= $e ?></option>
                    <?php endforeach; ?>
                </select>

                <label><b>Cliente</b></label>
                <select name="id_cliente" id="edit_id_cliente" class="w3-select w3-border w3-margin-bottom" required>
                    <?php foreach ($clientes as $cliente): ?>
                        <option value="<?= $cliente['id'] ?>">
                            <?= $cliente['nombre'].' '.$cliente['ape_pat'].' '.$cliente['ape_mat'] ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <footer class="w3-container w3-green w3-padding">
                    <button type="submit" class="w3-button w3-white w3-right">Guardar</button>
                    <button type="button"
                            onclick="document.getElementById('modalEditarDireccion').style.display='none'"
                            class="w3-button w3-white">Cancelar</button>
                </footer>

            </form>
        </div>
    </div>

    <script>
        function abrirModal(id, colonia, calle, numero, municipio, estado, id_cliente) {
            document.getElementById('edit_id').value = id;
            document.getElementById('edit_col').value = colonia;
            document.getElementById('edit_calle').value = calle;
            document.getElementById('edit_num').value = numero;
            document.getElementById('edit_mun').value = municipio;
            document.getElementById('edit_edo').value = estado;
            document.getElementById('edit_id_cliente').value = id_cliente;
            document.getElementById('modalEditarDireccion').style.display = 'block';
        }

        window.onclick = function(event) {
            const modal = document.getElementById('modalEditarDireccion');
            if (event.target === modal) modal.style.display = 'none';
        };
    </script>

</body>
</html>