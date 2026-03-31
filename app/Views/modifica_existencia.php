<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Modifica existencia</title>
</head>
<body>
    <form action="<?= base_url('modifica_existencia') ?>" method="post">
        <input type="hidden" name="id" value="<?= $existencias['id'] ?>">
        
        <label for="e_total">Existencias totales:</label><br>
        <input type="number" name="e_total" id="e_total" value="<?= esc($existencias['e_total']) ?>" required><br>
        
        <label for="e_bloqueado">Existencias bloqueadas:</label><br>
        <input type="number" name="e_bloqueado" id="e_bloqueado" value="<?= esc($existencias['e_bloqueado']) ?>" required><br>
        
        <label for="e_venta">Existencias para venta:</label><br>
        <input type="number" name="e_venta" id="e_venta" value="<?= esc($existencias['e_venta']) ?>" required><br>
        
        <label for="fecha">Fecha:</label><br>
        <input type="timestamp" name="fecha" id="fecha" value="<?= esc($existencias['fecha']) ?>" required><br>
        
        <label for="id_producto">Producto:</label><br>
        <select name="id_producto" id="id_producto" required>
            <?php foreach($productos as $p): ?>
                <option value="<?= esc($p['id']) ?>" <?= $p['id'] == $existencias['id_producto'] ? 'selected' : '' ?>><?= esc($p['nombre']) ?></option>
            <?php endforeach; ?>
        </select><br>
        <input type="submit" value="Enviar">
    </form>
</body>
</html>