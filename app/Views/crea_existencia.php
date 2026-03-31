<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Crea existencia</title>
</head>
<body>
    <form action="<?= base_url('guarda_existencia') ?>" method="post">
        <label for="e_total">Existencias totales</label><br>
        <input type="number" name="e_total" id="e_total"><br>

        <label for="e_bloqueado">Existencias bloqueadas</label><br>
        <input type="number" name="e_bloqueado" id="e_bloqueado"><br>

        <label for="e_venta">Existencias para venta</label><br>
        <input type="number" name="e_venta" id="e_venta"><br>

        <label for="fecha">Fecha</label><br>
        <input type="timestamp" name="fecha" id="fecha" value="<?= $timestamp ?>"><br>

        <label for="id_producto">Producto</label><br>
        <select name="id_producto" id="id_producto">
            <?php foreach($productos as $p): ?>
                <option value="<?= $p['id'] ?>"><?= $p['nombre'] ?></option>
            <?php endforeach; ?>
        </select><br>
        <input type="submit" value="Enviar">
        <br>
    </form>
    
</body>
</html>