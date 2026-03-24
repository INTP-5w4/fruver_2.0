<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Crea Direccion</title>
</head>
<body>
    <form action="<?= base_url('guarda_direccion') ?>" method="post">
            <label>Colonia</label><br>
            <input type="text" name="col" id="" required><br>
            <label>Calle</label><br>
            <input type="text" name="calle" id="" required><br>
            <label>Numero</label><br>
            <input type="text" name="num" id="" required><br>
            <label>Municipio</label><br>
            <input type="text" name="mun" id="" required><br>
            <label>Estado</label><br>
            <select name="edo" id="estado" required><br>
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
            </select><br>
            <label>Cliente</label><br>
            <select name="id_cliente" id="" required>
                <?php foreach ($clientes as $c):?>
                <option value="<?= esc($c['id'])?>">
                    <?= esc($c['nombre'].' '.$c['ape_pat'].' '.$c['ape_mat'])?>
                </option>
                <?php endforeach;?>
            </select><br>
            <input type="submit" value="Enviar">
            
    </form>
</body>
</html>