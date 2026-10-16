<!DOCTYPE html>
<html lang="es">
<head>
    <link rel="stylesheet" href="<?= base_url('estilos/estilosPaginas.css') ?>">
    <link rel="stylesheet" href="https://www.w3schools.com/w3css/5/w3.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/7.0.1/css/all.min.css" integrity="sha512-2SwdPD6INVrV/lHTZbO2nodKhrnDdJK9/kg2XD1r9uGqPo1cUbujc+IYdlYdEErWNu69gVcYgdxlmVmzTWnetw==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="<?= base_url('estilos/Header.css') ?>">
    <title>Lista Entregas</title>
</head>
<body>
    <?php include 'Header.php'; ?>
    <?php include 'sidebar.php'; ?>

<div class="lista-wrapper">

<div class="flash-container">
    <?php if (session()->getFlashdata('error')): ?>
        <div class="w3-panel w3-red w3-animate-opacity">
            <p><?= esc(session()->getFlashdata('error')) ?></p>
        </div>
    <?php endif; ?>

    <?php if (session()->getFlashdata('mensaje')): ?>
        <div class="w3-panel w3-green w3-animate-opacity">
            <p><?= esc(session()->getFlashdata('mensaje')) ?></p>
        </div>
    <?php endif; ?>
</div>

<div class="contenedor-boton" style="padding-top: 20px;">
    <button onclick="document.getElementById('modalAsignarEntrega').style.display='block'"
            class="btn-agregar">
        + Asignar repartidor
    </button>
</div>

<form method="get" action="<?= base_url('lista_entrega') ?>" class="w3-margin-bottom">
    <div style="display:flex; gap:8px;">
        <input
            type="text"
            name="buscar"
            value="<?= esc($buscar ?? '') ?>"
            placeholder="Buscar por pedido, estado, repartidor o cliente..."
            class="w3-input w3-border"
            style="max-width:350px;"
        >
        <button type="submit" class="w3-button w3-green">
            <i class="fa-solid fa-magnifying-glass"></i> Buscar
        </button>
        <?php if (!empty($buscar)): ?>
            <a href="<?= base_url('lista_entrega') ?>" class="w3-button w3-red">✕ Limpiar</a>
        <?php endif; ?>
    </div>
</form>

<div class="tabla-wrapper">
    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Pedido</th>
                <th>Intento</th>
                <th>Cliente</th>
                <th>Repartidor</th>
                <th>Estado</th>
                <th>Asignada</th>
                <th>Salida</th>
                <th>Entrega</th>
                <th>Cobro</th>
                <th>Incidencia</th>
                <th>Cancelar</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($entregas as $e): ?>
                <tr>
                    <td><?= esc($e['id']) ?></td>
                    <td><?= esc($e['id_pedido']) ?></td>
                    <td><?= esc($e['intento']) ?></td>
                    <td><?= esc($e['cliente']) ?></td>
                    <td><?= esc($e['repartidor']) ?></td>
                    <td><?= esc(str_replace('_', ' ', $e['estado'])) ?></td>
                    <td><?= esc($e['fecha_asignacion']) ?></td>
                    <td><?= esc($e['fecha_salida'] ?? '—') ?></td>
                    <td><?= esc($e['fecha_entrega'] ?? '—') ?></td>
                    <td>
                        <?php if ($e['monto_cobrado'] !== null): ?>
                            $<?= esc(number_format((float) $e['monto_cobrado'], 2)) ?> (<?= esc($e['metodo_cobro']) ?>)
                        <?php else: ?>
                            —
                        <?php endif; ?>
                    </td>
                    <td><?= esc($e['incidencia'] ?? '—') ?></td>
                    <td>
                        <?php if (in_array($e['estado'], ['asignada', 'en_ruta'], true)): ?>
                            <form method="post" action="<?= base_url('cancela_entrega/' . (int) $e['id']) ?>" style="display:inline; margin:0;" onsubmit="return confirm('¿Cancelar esta entrega?');">
                                <?= csrf_field() ?>
                                <button type="submit" class="btn-icono"><i class="fa-solid fa-ban"></i></button>
                            </form>
                        <?php else: ?>
                            —
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?= $pager->links('default', 'w3_pager') ?>
</div>

    <!-- MODAL ASIGNAR REPARTIDOR -->
    <div id="modalAsignarEntrega" class="w3-modal" style="display:none;">
        <div class="modal-contenido w3-animate-zoom">

            <header class="modal-header">
                <span onclick="document.getElementById('modalAsignarEntrega').style.display='none'"
                      class="modal-cerrar">&times;</span>
                <h3>Asignar repartidor</h3>
            </header>

            <form action="<?= base_url('asigna_entrega') ?>" method="post" class="modal-form">
                <?= csrf_field() ?>

                <label for="id_pedido">Pedido (confirmado o en tránsito)</label>
                <select name="id_pedido" id="id_pedido" class="w3-select w3-border" required>
                    <option value="" disabled selected>Elige un pedido</option>
                    <?php foreach ($pedidos as $p): ?>
                        <option value="<?= (int) $p['id'] ?>">
                            #<?= esc($p['id']) ?> · <?= esc($p['cliente']) ?> · <?= esc($p['fecha']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <label for="id_repartidor">Repartidor</label>
                <select name="id_repartidor" id="id_repartidor" class="w3-select w3-border" required>
                    <option value="" disabled selected>Elige un repartidor</option>
                    <?php foreach ($repartidores as $r): ?>
                        <option value="<?= (int) $r['id'] ?>">
                            <?= esc(trim($r['nombre'] . ' ' . $r['ape_pat'])) ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <?php if (empty($pedidos)): ?>
                    <p class="w3-small">No hay pedidos confirmados sin entrega asignada.</p>
                <?php endif; ?>

                <button type="submit" class="w3-button w3-green w3-margin-top">Asignar</button>
            </form>
        </div>
    </div>

</div>
</body>
</html>
