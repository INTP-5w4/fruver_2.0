<!DOCTYPE html>
<html lang="en">
<head>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/7.0.1/css/all.min.css" integrity="sha512-2SwdPD6INVrV/lHTZbO2nodKhrnDdJK9/kg2XD1r9uGqPo1cUbujc+IYdlYdEErWNu69gVcYgdxlmVmzTWnetw==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lista de productos</title>
</head>
<body>
    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Nombre</th>
                <th>Descripción</th>
                <th>Editar</th>
                <th>Eliminar</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($productos as $p):?>
                <tr>
            <td><?= $p['id'] ?></td>
            <td><?= esc($p['nombre']) ?></td>
            <td><?= esc($p['descripcion']) ?></td>
            <td><a href="<?= base_url('pasa_id_producto/'.$p['id']) ?>"><button><i class="fa-solid fa-pen-to-square"></i></button></a></td> 
            <td><a href="<?= base_url('borra_id_producto/'.$p['id']) ?>"><button><i class="fa-solid fa-trash-can"></i></button></a></td> 
            <?php endforeach?>
        </tbody>
    </table>
</body>
</html>