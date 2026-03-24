<!DOCTYPE html>
<html lang="en">
<head>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/7.0.1/css/all.min.css" integrity="sha512-2SwdPD6INVrV/lHTZbO2nodKhrnDdJK9/kg2XD1r9uGqPo1cUbujc+IYdlYdEErWNu69gVcYgdxlmVmzTWnetw==" crossorigin="anonymous" referrerpolicy="no-referrer" />    <meta charset="UTF-8">    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lista entrada</title>
</head>
<body>
    <table>
        <thead>
            <th>ID</th>
            <th>Fecha de entrada</th>
            <th>Fecha de caducidad</th>
            <th>Cantidad</th>
            <th>Unidad de compra</th>
            <th>Unidad de venta</th>
            <th>Precio compra</th>
            <th>ID del producto</th>
            <th>Editar</th>
            <th>Eliminar</th>
        </thead>
        <tbody>
            <?php foreach($entradas as $e): ?>
            <tr>
                <td><?= $e['id']?></td>
                <td><?= $e['fecha']?></td>
                <td><?= $e['fecha_cad']?></td>
                <td><?= $e['cantidad']?></td>
                <td><?= $e['u_compra']?></td>
                <td><?= $e['u_venta']?></td>
                <td><?= $e['precio_compra']?></td>
                <td><?= $e['id_producto']?></td>
                <td><a href="<?= base_url('pasa_id_entrada/'.$e['id'])?>"><button><i class="fa-solid fa-pen-to-square"></i></button></a></td>
                <td><a href="<?= base_url('borra_id_entrada/'.$e['id'])?>"><button><i class="fa-solid fa-trash-can"></i></button></a></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

</body>
</html>