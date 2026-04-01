<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Modifica p_pedido</title>
</head>
<body>
    <form action="<?php echo base_url('modifica_p_pedido'); ?>" method="post">
    <input type="hidden" name="id" value="<?= $p_pedidos['id'] ?>">
    <label for="cantidad">Cantidad:</label><br>
    <input type="number" id="cantidad" name="cant" required value="<?= $p_pedidos['cantidad'] ?>"><br>

    <label for="p_venta">Precio de venta:</label><br>
    <input type="number" id="p_venta" name="p_venta" step="0.01" required value="<?= $p_pedidos['precio_venta'] ?>"><br>

    <label for="u_venta">Unidad de venta:</label><br>
    <select name="u_venta" id="">
        <option value="Kilogramo" <?php echo ($p_pedidos['unidad_venta'] == 'Kilogramo') ? 'selected' : ''; ?>>Kilogramo</option>
        <option value="Domo" <?php echo ($p_pedidos['unidad_venta'] == 'Domo') ? 'selected' : ''; ?>>Domo</option>
        <option value="Ramos" <?php echo ($p_pedidos['unidad_venta'] == 'Ramos') ? 'selected' : ''; ?>>Ramo</option>
        <option value="Caja" <?php echo ($p_pedidos['unidad_venta'] == 'Caja') ? 'selected' : ''; ?>>Caja</option>
    </select><br>

    <label for="tot">Total:</label><br>
    <input type="number" id="tot" name="tot" step="0.01" required value="<?= $p_pedidos['total'] ?>"><br>

    <label for="id_pedido">ID Pedido:</label><br>
    <select name="id_pedido" id="">
        <?php foreach($pedidos as $p): ?>
            <option value="<?php echo $p['id']; ?>" <?php echo ($p_pedidos['id_pedido'] == $p['id']) ? 'selected' : ''; ?>><?= $p['id'] ?></option>
        <?php endforeach; ?>
    </select><br>

    <label for="id_producto">Producto:</label><br>
    <select name="id_producto" id="">
        <?php foreach($productos as $pr): ?>
            <option value="<?php echo $pr['id']; ?>" <?php echo ($p_pedidos['id_producto'] == $pr['id']) ? 'selected' : ''; ?>><?= $pr['nombre'] ?></option>
        <?php endforeach; ?>
    </select><br>
    <input type="submit" value="Enviar">
    </form>
    
</body>
</html>