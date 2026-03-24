<!DOCTYPE html>
<html lang="en">
<head>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/7.0.1/css/all.min.css" integrity="sha512-2SwdPD6INVrV/lHTZbO2nodKhrnDdJK9/kg2XD1r9uGqPo1cUbujc+IYdlYdEErWNu69gVcYgdxlmVmzTWnetw==" crossorigin="anonymous" referrerpolicy="no-referrer" />    <meta charset="UTF-8">    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lista direccion</title>
</head>
<body>
    <table>
        <thead>
            <th>ID</th>
            <th>Colonia</th>
            <th>Calle</th>
            <th>Numero</th>
            <th>Municipio</th>
            <th>Estado</th>
            <th>ID Cliente</th>
            <th>Editar</th>
            <th>Eliminar</th>
        </thead>
        <tbody>
            <?php foreach ($direcciones as $d):?>
                <tr>
                    <td><?= $d['id'] ?></td>
                    <td><?= $d['colonia'] ?></td>
                    <td><?= $d['calle'] ?></td>
                    <td><?= $d['numero'] ?></td>
                    <td><?= $d['municipio'] ?></td>
                    <td><?= $d['estado'] ?></td>
                    <td><?= $d['id_cliente'] ?></td>
                    <td><a href="<?= base_url('pasa_id_direccion/').$d['id']?>"><button><i class="fa-solid fa-pen-to-square"></i></button></a></td>
                    <td><a href="<?= base_url('borra_id_direccion/'.$d['id']) ?>"><button><i class="fa-solid fa-trash-can"></i></button></a></td> 
                </tr>
            <?php endforeach;?>
        </tbody>
    </table>
</body>
</html>