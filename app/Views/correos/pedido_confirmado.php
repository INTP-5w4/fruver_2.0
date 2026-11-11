<?php
/**
 * Correo: comprobante del pedido hecho en la tienda (finalidad necesaria, Aviso 3.2:
 * "Enviar confirmaciones o comprobantes del pedido"). Estilos en línea: los clientes de
 * correo no cargan hojas de estilo.
 *
 * @var array{id:int,fecha:?string,cliente:string,estado:?string,total:float,items:list<array<string,mixed>>} $ticket
 */
$dinero = static fn ($v): string => '$' . number_format((float) $v, 2);
$cant   = static fn ($v): string => rtrim(rtrim(number_format((float) $v, 2, '.', ''), '0'), '.');
?>
<div style="font-family:Arial,Helvetica,sans-serif;color:#2B3674;max-width:560px;margin:0 auto;">
    <h1 style="color:#007542;font-size:22px;margin:0 0 8px;">FRUVER</h1>
    <p>Hola <?= esc($ticket['cliente']) ?>, recibimos tu pedido <strong>#<?= (int) $ticket['id'] ?></strong>.
        El personal lo revisará para confirmarlo y coordinar la entrega.</p>

    <table style="width:100%;border-collapse:collapse;font-size:14px;" cellpadding="6">
        <thead>
            <tr style="background:#e6f2e2;text-align:left;">
                <th>Producto</th><th>Cantidad</th><th style="text-align:right;">Total</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($ticket['items'] as $item): ?>
                <tr style="border-bottom:1px solid #c9d6c9;">
                    <td><?= esc($item['nombre_producto']) ?></td>
                    <td><?= esc($cant($item['cant']) . ' ' . $item['unidad_venta']) ?></td>
                    <td style="text-align:right;"><?= esc($dinero($item['total'] ?? ((float) $item['cant'] * (float) $item['precio_venta']))) ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
        <tfoot>
            <tr><td colspan="2"><strong>Total</strong></td><td style="text-align:right;"><strong><?= esc($dinero($ticket['total'])) ?></strong></td></tr>
        </tfoot>
    </table>

    <p style="font-size:12px;color:#6b7a99;margin-top:24px;">
        Recibes este correo porque hiciste un pedido en FRUVER; es parte del servicio y no es publicidad.
        Consulta nuestro <a href="<?= esc(site_url('aviso-de-privacidad'), 'attr') ?>">Aviso de Privacidad</a>.
    </p>
</div>
