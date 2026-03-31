<!DOCTYPE html>
<html lang="en">
<head>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/7.0.1/css/all.min.css" integrity="sha512-2SwdPD6INVrV/lHTZbO2nodKhrnDdJK9/kg2XD1r9uGqPo1cUbujc+IYdlYdEErWNu69gVcYgdxlmVmzTWnetw==" crossorigin="anonymous" referrerpolicy="no-referrer" />    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lista existencia</title>
</head>
<body>
    <table>
        <thead>
            <th>ID</th>
            <th>Existencias totales</th>
            <th>Existencias bloqueadas</th>
            <th>Existencias para venta</th>
            <th>Fecha</th>
            <th>Producto</th>
            <th>Editar</th>
            <th>Eliminar</th>
        </thead>
        <tbody>
            <?php foreach($existencias as $e): ?>
                <tr>
                    <td><?= $e['id'] ?></td>
                    <td><?= $e['e_total'] ?></td>
                    <td><?= $e['e_bloqueado'] ?></td>
                    <td><?= $e['e_venta'] ?></td>
                    <td><?= $e['fecha'] ?></td>
                    <td><?= $productos[$e['id_producto']]['nombre'] ?></td>
                    <td><a href="<?= base_url('pasa_id_existencia/'.$e['id']) ?>"><i class="fa-solid fa-pen-to-square"></i></a></td>
                    <td><a href="<?= base_url('borra_id_existencia/'.$e['id']) ?>"><i class="fa-solid fa-trash"></i></a></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</body>
</html>