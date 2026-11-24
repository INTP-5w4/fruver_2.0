<?php
/**
 * Ticket imprimible por navegador (alternativa universal a ESC/POS directo).
 * Funciona con cualquier impresora térmica que tenga driver instalado.
 *
 * Variables: $pedido, $servicio (ServicioTicket), $cfg (Config\Impresora), $papel (58|80), $auto (bool)
 */
// Ancho imprimible típico: 58 mm → 48 mm, 80 mm → 72 mm.
$anchoMm = $papel === 58 ? 48 : 72;
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ticket #<?= esc($pedido['id']) ?></title>
    <style>
        * { box-sizing: border-box; }

        body {
            margin: 0;
            background: #eef1ee;
            font-family: "Courier New", Courier, monospace;
            color: #000;
        }

        .barra {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
            justify-content: center;
            align-items: center;
            padding: 12px;
            font-family: system-ui, sans-serif;
            font-size: 14px;
        }

        .barra button, .barra a {
            border: 0;
            border-radius: 6px;
            padding: 8px 14px;
            font-size: 14px;
            cursor: pointer;
            text-decoration: none;
            background: #2e7d32;
            color: #fff;
        }

        .barra a.sec { background: #fff; color: #333; border: 1px solid #bbb; }

        .ticket {
            width: <?= $anchoMm ?>mm;
            margin: 0 auto 24px;
            padding: 4mm 2mm;
            background: #fff;
            font-size: 12px;
            line-height: 1.35;
            box-shadow: 0 2px 10px rgba(0, 0, 0, .15);
        }

        .centro { text-align: center; }
        .negocio { font-size: 18px; font-weight: bold; }
        .sep { border-top: 1px dashed #000; margin: 6px 0; }
        .fila { display: flex; justify-content: space-between; gap: 6px; }
        .fila > span:last-child { white-space: nowrap; }
        .item { margin-bottom: 4px; }
        .item .nombre { font-weight: bold; word-break: break-word; }
        .total { font-size: 16px; font-weight: bold; }
        .cancelado { text-align: center; font-weight: bold; margin: 4px 0; }

        @page { margin: 0; }

        @media print {
            body { background: #fff; }
            .barra { display: none; }
            .ticket { width: <?= $anchoMm ?>mm; margin: 0; box-shadow: none; padding: 2mm 1mm; }
        }
    </style>
</head>
<body>

<div class="barra">
    <button type="button" onclick="window.print()">🖨 Imprimir</button>
    <a class="sec" href="?papel=58<?= $auto ? '&auto=1' : '' ?>">58 mm</a>
    <a class="sec" href="?papel=80<?= $auto ? '&auto=1' : '' ?>">80 mm</a>
    <a class="sec" href="<?= base_url('lista_p_pedido') ?>">Volver a pedidos</a>
</div>

<div class="ticket">
    <div class="centro">
        <div class="negocio"><?= esc($cfg->negocioNombre) ?></div>
        <?php if ($cfg->negocioSlogan !== ''): ?><div><?= esc($cfg->negocioSlogan) ?></div><?php endif; ?>
        <?php if ($cfg->negocioDireccion !== ''): ?><div><?= esc($cfg->negocioDireccion) ?></div><?php endif; ?>
        <?php if ($cfg->negocioTelefono !== ''): ?><div>Tel: <?= esc($cfg->negocioTelefono) ?></div><?php endif; ?>
    </div>

    <div class="sep"></div>

    <div class="fila"><b>Pedido #<?= esc($pedido['id']) ?></b><span><?= esc($servicio->fecha($pedido['fecha'])) ?></span></div>
    <div>Cliente: <?= esc($pedido['cliente'] !== '' ? $pedido['cliente'] : '-') ?></div>
    <?php if ($pedido['telefono'] !== ''): ?><div>Tel: <?= esc($pedido['telefono']) ?></div><?php endif; ?>
    <div>Estado: <?= esc($pedido['estado_texto']) ?></div>
    <?php if ($pedido['cancelado']): ?><div class="cancelado">*** PEDIDO CANCELADO ***</div><?php endif; ?>

    <div class="sep"></div>

    <?php foreach ($pedido['items'] as $it): ?>
        <div class="item">
            <div class="nombre"><?= esc($it['nombre']) ?></div>
            <div class="fila">
                <span>&nbsp;&nbsp;<?= esc($servicio->cantidad($it['cant'])) ?> <?= esc($servicio->unidad($it['unidad'])) ?> x <?= esc($servicio->dinero($it['precio'])) ?></span>
                <span><?= esc($servicio->dinero($it['total'])) ?></span>
            </div>
        </div>
    <?php endforeach; ?>

    <div class="sep"></div>

    <div>Artículos: <?= count($pedido['items']) ?></div>
    <div class="fila total"><span>TOTAL</span><span><?= esc($servicio->dinero($pedido['total'])) ?></span></div>

    <div class="sep"></div>

    <div class="centro">
        <?php if ($cfg->mensajePie !== ''): ?><div><?= esc($cfg->mensajePie) ?></div><?php endif; ?>
        <div>Impreso: <?= date('d/m/Y H:i') ?></div>
    </div>
</div>

<?php if ($auto): ?>
<script>
    // Impresión automática al abrir; si se abrió desde el sistema, cierra la pestaña al terminar.
    window.addEventListener('load', function () {
        setTimeout(function () { window.print(); }, 300);
    });
    window.addEventListener('afterprint', function () {
        if (window.opener) { window.close(); }
    });
</script>
<?php endif; ?>

</body>
</html>
