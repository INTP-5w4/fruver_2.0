<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Modifica dirección</title>
</head>
<body>
    <form action="<?= base_url('modifica_direccion') ?>" method="post">
            <label>Colonia</label><br>
            <input type="text" name="col" id="" value="<?= $direcciones['colonia']?>" required><br>
            <label>Calle</label><br>
            <input type="text" name="calle" id="" value="<?= $direcciones['calle']?>" required><br>
            <label>Numero</label><br>
            <input type="text" name="num" id="" value="<?= $direcciones['numero']?>" required><br>
            <label>Municipio</label><br>
            <input type="text" name="mun" id="" value="<?= $direcciones['municipio']?>" required><br>
            <label>Estado</label><br>
            <?php 
                $estados = ["Aguascalientes","Baja California","Baja California Sur","Campeche","Chiapas","Chihuahua","Ciudad de Mexico","Coahuila","Colima","Durango","Estado de Mexico","Guanajuato","Guerrero","Hidalgo","Jalisco","Michoacán","Morelos","Nayarit","Nuevo Leon","Oaxaca","Puebla","Queretaro","Quintana Roo","San Luis Potosi","Sinaloa","Sonora","Tabasco","Tamaulipas","Tlaxcala","Veracruz","Yucatan","Zacatecas"];
                ?>

                <select name="edo">
                <?php foreach ($estados as $e): ?>
                    <option value="<?= $e ?>" <?= ( $direcciones['estado']== $e) ? 'selected' : '' ?>>
                        <?= $e ?>
                    </option>
                <?php endforeach; ?>
                </select><br>


                <label>Cliente</label><br>
                    <select name="id_cliente">
                        <?php foreach($clientes as $cliente): ?>
                            <option 
                                value="<?= $cliente['id'] ?>"
                                <?= ($cliente['id'] == $direcciones['id_cliente']) ? 'selected' : '' ?>
                            >
                                <?= $cliente['nombre'] ?>
                            </option>
                        <?php endforeach; ?>
                    </select><br>

            <input type="submit" value="Enviar">
            
    </form>
</body>
</html>