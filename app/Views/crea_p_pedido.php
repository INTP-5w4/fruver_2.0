<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Crea p_pedido</title>
</head>
<body>
    <form action="<?=base_url('guarda_p_pedido') ?>" method="post">
    <label for="cantidad">Cantidad:</label><br>
    <input type="number" id="cantidad" name="cant" required><br>

    <label for="p_venta">Precio de venta:</label><br>
    <input type="number" id="p_venta" name="p_venta" step="0.01" required><br>

    <label for="u_venta">Unidad de venta:</label><br>
    <select name="u_venta" id="">
        <option value="Kilogramo">Kilogramo</option>
        <option value="Domo">Domo</option>
        <option value="Ramos">Ramo</option>
        <option value="Caja">Caja</option>
    </select><br>

    <label for="tot">Total:</label><br>
    <input type="number" id="tot" name="tot" step="0.01"><br>

    <label for="id_pedido">ID Pedido:</label><br>
    <select name="id_pedido" id="">
        <?php foreach($pedidos as $p): ?>
            <option value="<?php echo $p['id']; ?>"><?php echo $p['id']; ?></option>
        <?php endforeach; ?>
    </select><br>

    <label for="id_producto">Producto:</label><br>
    <select name="id_producto" id="">
        <?php foreach($productos as $pr): ?>
            <option value="<?php echo $pr['id']; ?>"><?php echo $pr['nombre']; ?></option>
        <?php endforeach; ?>
    </select><br>
    <input type="submit" value="Enviar">
    </form>
    
</body>
</html>