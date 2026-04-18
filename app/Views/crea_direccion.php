<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Crea Direccion</title>
    <link rel="stylesheet" href="<?= base_url('estilos/estilosPaginas.css') ?>">
</head>
<body>
    <div class="formulario-pagina">

        <header class="modal-header">
            <h2>Registrar Dirección</h2>
        </header>

        <form action="<?= base_url('guarda_direccion') ?>" method="post" class="modal-form">

            <label><b>Colonia</b></label>
            <input type="text" name="col" class="modal-input" required>

            <label><b>Calle</b></label>
            <input type="text" name="calle" class="modal-input" required>

            <label><b>Número</b></label>
            <input type="text" name="num" class="modal-input" required>

            <label><b>Municipio</b></label>
            <input type="text" name="mun" class="modal-input" required>

            <label><b>Estado</b></label>
            <select name="edo" id="estado" class="modal-input" required>
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
            <select name="id_cliente" class="modal-input" required>
                <?php foreach ($clientes as $c): ?>
                    <option value="<?= esc($c['id']) ?>">
                        <?= esc($c['nombre'].' '.$c['ape_pat'].' '.$c['ape_mat']) ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <footer class="modal-footer">
                <button type="submit" class="btn-guardar">Guardar</button>
            </footer>

        </form>
    </div>
</body>
</html>