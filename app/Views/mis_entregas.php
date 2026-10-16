<!DOCTYPE html>
<html lang="es">
<head>
    <link rel="stylesheet" href="<?= base_url('estilos/estilosPaginas.css') ?>">
    <link rel="stylesheet" href="https://www.w3schools.com/w3css/5/w3.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/7.0.1/css/all.min.css" integrity="sha512-2SwdPD6INVrV/lHTZbO2nodKhrnDdJK9/kg2XD1r9uGqPo1cUbujc+IYdlYdEErWNu69gVcYgdxlmVmzTWnetw==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="<?= base_url('estilos/Header.css') ?>">
    <title>Mis entregas</title>
</head>
<body>
    <?php include 'Header.php'; ?>

<div class="lista-wrapper" style="max-width:900px; margin:0 auto; padding:16px;">

    <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:8px;">
        <h2 style="margin:0;">Mis entregas</h2>
        <a href="<?= base_url('logout') ?>" class="w3-button w3-border">
            <i class="fa-solid fa-right-from-bracket"></i> Cerrar sesión
        </a>
    </div>

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

    <div class="w3-bar w3-margin-bottom">
        <?php
        $filtros = [
            ''          => 'Todas',
            'asignada'  => 'Asignadas',
            'en_ruta'   => 'En ruta',
            'entregada' => 'Entregadas',
            'fallida'   => 'Fallidas',
        ];
        foreach ($filtros as $valor => $texto):
            $activo = ($estado ?? '') === $valor;
        ?>
            <a href="<?= base_url('repartidor/entregas' . ($valor === '' ? '' : '?estado=' . $valor)) ?>"
               class="w3-bar-item w3-button <?= $activo ? 'w3-green' : 'w3-light-grey' ?>">
                <?= esc($texto) ?>
            </a>
        <?php endforeach; ?>
    </div>

    <?php if (empty($entregas)): ?>
        <div class="w3-panel w3-pale-yellow w3-border">
            <p>No tienes entregas<?= ($estado ?? '') !== '' ? ' con este estado' : ' asignadas por ahora' ?>.</p>
        </div>
    <?php endif; ?>

    <?php foreach ($entregas as $e): ?>
        <div class="w3-card w3-white w3-margin-bottom w3-padding">
            <div style="display:flex; justify-content:space-between; flex-wrap:wrap; gap:8px;">
                <strong>Pedido #<?= esc($e['id_pedido']) ?> · intento <?= esc($e['intento']) ?></strong>
                <span class="w3-tag w3-round <?= match ($e['estado']) {
                    'asignada'  => 'w3-blue',
                    'en_ruta'   => 'w3-orange',
                    'entregada' => 'w3-green',
                    'fallida'   => 'w3-red',
                    default     => 'w3-grey',
                } ?>"><?= esc(str_replace('_', ' ', $e['estado'])) ?></span>
            </div>

            <p style="margin:8px 0;">
                <i class="fa-solid fa-user"></i> <?= esc($e['cliente']) ?><br>
                <i class="fa-solid fa-phone"></i>
                <a href="tel:<?= esc($e['telefono_cliente'], 'attr') ?>"><?= esc($e['telefono_cliente']) ?></a>
            </p>

            <?php if ($e['incidencia']): ?>
                <p class="w3-text-red"><i class="fa-solid fa-triangle-exclamation"></i> <?= esc($e['incidencia']) ?></p>
            <?php endif; ?>

            <?php if ($e['estado'] === 'entregada'): ?>
                <p>
                    Entregada el <?= esc($e['fecha_entrega']) ?>
                    <?php if ($e['monto_cobrado'] !== null): ?>
                        · Cobrado $<?= esc(number_format((float) $e['monto_cobrado'], 2)) ?> (<?= esc($e['metodo_cobro']) ?>)
                    <?php endif; ?>
                </p>
            <?php endif; ?>

            <?php if ($e['estado'] === 'asignada'): ?>
                <form method="post" action="<?= base_url('repartidor/entregas/' . (int) $e['id'] . '/salir') ?>">
                    <?= csrf_field() ?>
                    <button type="submit" class="w3-button w3-blue">
                        <i class="fa-solid fa-truck"></i> Salir a entregar
                    </button>
                </form>
            <?php endif; ?>

            <?php if ($e['estado'] === 'en_ruta'): ?>
                <form method="post" action="<?= base_url('repartidor/entregas/' . (int) $e['id'] . '/entregar') ?>" class="w3-margin-bottom">
                    <?= csrf_field() ?>
                    <div style="display:flex; gap:8px; flex-wrap:wrap;">
                        <input type="number" name="monto" min="0" step="0.01" placeholder="Monto cobrado (opcional)" class="w3-input w3-border" style="max-width:220px;">
                        <select name="metodo" class="w3-select w3-border" style="max-width:180px;">
                            <option value="">Método de cobro</option>
                            <option value="efectivo">Efectivo</option>
                            <option value="transferencia">Transferencia</option>
                            <option value="tarjeta">Tarjeta</option>
                        </select>
                        <input type="text" name="notas" maxlength="500" placeholder="Notas (opcional)" class="w3-input w3-border" style="flex:1; min-width:180px;">
                    </div>
                    <button type="submit" class="w3-button w3-green w3-margin-top">
                        <i class="fa-solid fa-circle-check"></i> Marcar entregada
                    </button>
                </form>

                <form method="post" action="<?= base_url('repartidor/entregas/' . (int) $e['id'] . '/fallar') ?>">
                    <?= csrf_field() ?>
                    <div style="display:flex; gap:8px; flex-wrap:wrap;">
                        <input type="text" name="incidencia" maxlength="500" required placeholder="¿Qué pasó? (cliente ausente, dirección incorrecta...)" class="w3-input w3-border" style="flex:1; min-width:220px;">
                        <button type="submit" class="w3-button w3-red">
                            <i class="fa-solid fa-circle-xmark"></i> No se pudo entregar
                        </button>
                    </div>
                </form>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>

    <?= $pager->links('default', 'w3_pager') ?>
</div>
</body>
</html>
