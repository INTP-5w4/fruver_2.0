<!DOCTYPE html>
<html lang="en">
<head>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/7.0.1/css/all.min.css" integrity="sha512-2SwdPD6INVrV/lHTZbO2nodKhrnDdJK9/kg2XD1r9uGqPo1cUbujc+IYdlYdEErWNu69gVcYgdxlmVmzTWnetw==" crossorigin="anonymous" referrerpolicy="no-referrer" />    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lista estatus</title>
</head>
<body>
    <table>
        <thead>
            <th>ID</th>
            <th>Estado</th>
            <th>Fecha</th>
            <th>ID Pedido</th>
            <th>Editar</th>
            <th>Eliminar</th>
        </thead>
        <tbody>
            <?php foreach($estatus as $e): ?>
                <tr>
                    <td><?= $e['id'] ?></td>
                    <td><?= $e['estado'] ?></td>
                    <td><?= $e['fecha'] ?></td>
                    <td><?= $e['id_pedido'] ?></td>
                    <td><a href="<?= base_url('pasa_id_estatus/'.$e['id']) ?>"><button><i class="fa-solid fa-pen-to-square"></i></button></a></td>
                    <td><a href="<?= base_url('borra_id_estatus/'.$e['id']) ?>"><button><i class="fa-solid fa-trash-can"></i></button></a></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    
</body>
</html>