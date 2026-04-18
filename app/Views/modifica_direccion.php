<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Modifica Dirección</title>
    <link rel="stylesheet" href="<?= base_url('estilos/estilosPaginas.css') ?>">
</head>
<body>
    <div class="formulario-pagina">

        <header class="modal-header">
            <h2>Modificar Dirección</h2>
        </header>

        <form action="<?= base_url('modifica_direccion') ?>" method="post" class="modal-form">

            <input type="hidden" name="id" value="<?= $direcciones['id'] ?>">

            <label><b>Colonia</b></label>
            <input type="text" name="col" class="modal-input" value="<?= $direcciones['colonia'] ?>" required>

            <label><b>Calle</b></label>
            <input type="text" name="calle" class="modal-input" value="<?= $direcciones['calle'] ?>" required>

            <label><b>Número</b></label>
            <input type="text" name="num" class="modal-input" value="<?= $direcciones['numero'] ?>" required>

            <label><b>Municipio</b></label>
            <input type="text" name="mun" class="modal-input" value="<?= $direcciones['municipio'] ?>" required>

            <label><b>Estado</b></label>
            <?php $estados = ["Aguascalientes","Baja California","Baja California Sur","Campeche","Chiapas","Chihuahua","Ciudad de Mexico","Coahuila","Colima","Durango","Estado de Mexico","Guanajuato","Guerrero","Hidalgo","Jalisco","Michoacán","Morelos","Nayarit","Nuevo Leon","Oaxaca","Puebla","Queretaro","Quintana Roo","San Luis Potosi","Sinaloa","Sonora","Tabasco","Tamaulipas","Tlaxcala","Veracruz","Yucatan","Zacatecas"]; ?>
            <select name="edo" class="modal-input">
                <?php foreach ($estados as $e): ?>
                    <option value="<?= $e ?>" <?= ($direcciones['estado'] == $e) ? 'selected' : '' ?>>
                        <?= $e ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <label><b>Cliente</b></label>
            <select name="id_cliente" class="modal-input">
                <?php foreach ($clientes as $cliente): ?>
                    <option value="<?= $cliente['id'] ?>" <?= ($cliente['id'] == $direcciones['id_cliente']) ? 'selected' : '' ?>>
                        <?= $cliente['nombre'] ?>
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