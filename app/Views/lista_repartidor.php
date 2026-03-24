<!DOCTYPE html>
<html lang="en">
<head>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/7.0.1/css/all.min.css" integrity="sha512-2SwdPD6INVrV/lHTZbO2nodKhrnDdJK9/kg2XD1r9uGqPo1cUbujc+IYdlYdEErWNu69gVcYgdxlmVmzTWnetw==" crossorigin="anonymous" referrerpolicy="no-referrer" />    <meta charset="UTF-8">    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document Title</title>
</head>
<body>
    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Nombre</th>
                <th>Apellido Paterno</th>
                <th>Apellido Materno</th>
                <th>Telefono</th>
                <th>Dirección</th>
                <th>Notas</th>
                <th>Editar</th>
                <th>Eliminar</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($repartidores as $r):?>
                <tr>
            <td><?= $r['id'] ?></td>
            <td><?= esc($r['nombre']) ?></td>
            <td><?= esc($r['ape_pat']) ?></td>
            <td><?= esc($r['ape_mat']) ?></td>
            <td><?= esc($r['telefono']) ?></td>
            <td><?= esc($r['direccion']) ?></td>
            <td><?= esc($r['notas']) ?></td>
            <td><a href="<?= base_url('pasa_id_repartidor/'.$r['id']) ?>"><button><i class="fa-solid fa-pen-to-square"></i></button></a></td> 
            <td><a href="<?= base_url('borra_id_repartidor/'.$r['id']) ?>"><button><i class="fa-solid fa-trash-can"></i></button></a></td> 
            <?php endforeach?>
        </tbody>
    </table>    
</body>
</html>