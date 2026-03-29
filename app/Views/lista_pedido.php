<!DOCTYPE html>
<html lang="en">
<head>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/7.0.1/css/all.min.css" integrity="sha512-2SwdPD6INVrV/lHTZbO2nodKhrnDdJK9/kg2XD1r9uGqPo1cUbujc+IYdlYdEErWNu69gVcYgdxlmVmzTWnetw==" crossorigin="anonymous" referrerpolicy="no-referrer" />    <meta charset="UTF-8">    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lista pedido</title>
</head>
<body>
    <table>
        <thead>
            <th>ID</th>
            <th>Fecha</th>
            <th>Nombre del cliente</th>
            <th>Nombre del repartidor</th>
            <th>Editar</th>
            <th>Eliminar</th>
        </thead>
        <tbody>
            <?php foreach($pedidos as $p): ?>
            <tr>
                <td><?= $p['id'] ?></td>
                <td><?= $p['fecha'] ?></td>
                <td><?php $c = $clientes[$p['id_cliente']] ?? null; 
                            echo $c ? "{$c['nombre']} {$c['ape_pat']} {$c['ape_mat']}" : 'Desconocido';?>
                </td>
                <td><?php $r = $repartidores[$p['id_repartidor']] ?? null; 
                            echo $r ? "{$r['nombre']} {$r['ape_pat']} {$r['ape_mat']}" : 'Desconocido';?>
                </td>
                <td> <a href="<?= base_url('pasa_id_pedido/'.$p['id'])?>"><button><i class="fa-solid fa-pen-to-square"></i></button></a></td>
                <td> <a href="<?= base_url('borra_id_pedido/'.$p['id'])?>"><button><i class="fa-solid fa-trash-can"></i></button></a></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    
</body>
</html>