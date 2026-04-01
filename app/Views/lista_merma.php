<!DOCTYPE html>
<html lang="en">
<head>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/7.0.1/css/all.min.css" integrity="sha512-2SwdPD6INVrV/lHTZbO2nodKhrnDdJK9/kg2XD1r9uGqPo1cUbujc+IYdlYdEErWNu69gVcYgdxlmVmzTWnetw==" crossorigin="anonymous" referrerpolicy="no-referrer" />    <meta charset="UTF-8">    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lista merma</title>
</head>
<body>
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Cantidad</th>
                    <th>Fecha</th>
                    <th>Notas</th>
                    <th>ID Entrada</th>
                    <th>Editar</th>
                    <th>Eliminar</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($mermas as $m): ?>
                    <tr>
                        <td><?= $m['id'] ?></td>
                        <td><?= $m['cantidad'] ?></td>
                        <td><?= $m['fecha'] ?></td>
                        <td><?= $m['notas'] ?></td>
                        <td><?= $m['id_entrada'] ?></td>
                        <td>
                            <a href="<?= base_url("pasa_id_merma/".$m['id']) ?>">
                                <button>
                                    <i class="fa-solid fa-pen-to-square"></i>
                                </button>
                            </a>
                        </td>

                        <td>
                            <a href="<?= base_url("borra_id_merma/".$m['id']) ?>" onclick=" return confirm('¿Estás seguro de que quieres eliminar esta merma?')">
                                <button>
                                    <i class="fa-solid fa-trash-can"></i>
                                </button>
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
</body>
</html>