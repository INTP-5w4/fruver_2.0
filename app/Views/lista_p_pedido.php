<!DOCTYPE html>
<html lang="en">
<head>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/7.0.1/css/all.min.css" integrity="sha512-2SwdPD6INVrV/lHTZbO2nodKhrnDdJK9/kg2XD1r9uGqPo1cUbujc+IYdlYdEErWNu69gVcYgdxlmVmzTWnetw==" crossorigin="anonymous" referrerpolicy="no-referrer" />    <meta charset="UTF-8">    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lista p_pedido</title>
</head>
<body>
    <table>
        <tr>
            <th>ID</th>
            <th>Cantidad</th>
            <th>Precio de venta</th>
            <th>Unidad de venta</th>
            <th>Total</th>
            <th>ID Pedido</th>
            <th>Producto</th>
            <th>Editar</th>
            <th>Eliminar</th>
        </tr>
        <?php foreach($p_pedidos as $pp): ?>
        <tr>
            <td><?= $pp['id']; ?></td>
            <td><?= $pp['cantidad']; ?></td>
            <td><?= $pp['precio_venta']; ?></td>
            <td><?= $pp['unidad_venta']; ?></td>
            <td><?= $pp['total']; ?></td>
            <td><?= $pp['id_pedido']; ?></td>
            <td><?php echo $productos[$pp['id_producto']]['nombre']; ?></td>
            <td><a href="<?=  base_url("pasa_id_p_pedido/{$pp['id']}"); ?>"><button><i class="fa-solid fa-pen-to-square"></i></button></a></td>
            <td><a href="<?=  base_url("borra_id_p_pedido/{$pp['id']}"); ?>" onclick="return confirm('¿Estás seguro de que quieres eliminar este registro?');"><button><i class="fa-solid fa-trash-can"></i></button></a></td>
        </tr>
        <?php endforeach; ?>
    </table>
    
</body>
</html>