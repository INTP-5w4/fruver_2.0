<?php
/**
 * Precios del combustible y rendimiento de cada repartidor (Rutas::combustible).
 *
 * @var array<string,float>       $precios      vigentes
 * @var string|null               $actualizados Y-m-d de la última actualización (null = de fábrica)
 * @var array<string,float>       $fabrica      Config\Rutas::$preciosCombustible
 * @var list<array<string,mixed>> $repartidores
 * @var bool                      $conColumnas  ¿ya está la migración de combustible?
 */
use App\Libraries\Rutas\Combustible;
use App\Models\Modelo_repartidor;
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <?= view('partials/estilos_panel') ?>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Combustible</title>
    <style>
        .comb-precios { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 12px; margin: 8px 0 16px; }
        .comb-precio { border: 1px solid var(--linea); border-radius: var(--r-tarjeta); padding: 12px; background: var(--superficie); }
        .comb-precio label { display: block; font-weight: 700; margin-bottom: 6px; }
        .comb-precio .entrada { display: flex; align-items: center; gap: 6px; }
        .comb-precio input { max-width: 120px; }
        .comb-punto { display: inline-block; width: 12px; height: 12px; border-radius: 50%; margin-right: 6px; vertical-align: -1px; }
    </style>
</head>
<body>
<?php include 'Header.php'; ?>
<?php include 'sidebar.php'; ?>
<div class="lista-wrapper lista-angosta">
    <?php foreach (['error' => 'w3-red', 'mensaje' => 'w3-green'] as $clave => $clase): ?>
        <?php if (session()->getFlashdata($clave)): ?>
            <div class="w3-panel <?= $clase ?>"><p><?= esc(session()->getFlashdata($clave)) ?></p></div>
        <?php endif; ?>
    <?php endforeach; ?>

    <p><a href="<?= base_url('rutas') ?>">&larr; Volver a rutas</a></p>
    <h2><i class="fa-solid fa-gas-pump" aria-hidden="true"></i> Combustible y costo de traslado</h2>
    <p>
        Cada ruta calcula cuántos litros gasta (kilómetros ÷ rendimiento del vehículo) y cuánto cuestan con estos precios.
        <?php if ($actualizados === null): ?>
            <b>Son precios aproximados de fábrica</b>: actualízalos con lo que pagas en tu gasolinera.
        <?php else: ?>
            Actualizados el <b><?= esc(date('d/m/Y', strtotime($actualizados))) ?></b>.
        <?php endif; ?>
    </p>

    <form method="post" action="<?= base_url('rutas/combustible') ?>" class="rutas-panel">
        <?= csrf_field() ?>
        <h3 style="margin-top:0;">Precio por litro</h3>
        <div class="comb-precios">
            <?php foreach (Combustible::TIPOS as $tipo => $nombre): ?>
                <?php $color = ['magna' => '#2e7d32', 'premium' => '#c62828', 'diesel' => '#37474f'][$tipo]; ?>
                <div class="comb-precio">
                    <label for="precio_<?= esc($tipo, 'attr') ?>"><span class="comb-punto" style="background:<?= $color ?>" aria-hidden="true"></span><?= esc($nombre) ?></label>
                    <div class="entrada">
                        <span>$</span>
                        <input type="number" step="0.01" min="0.01" max="100" required inputmode="decimal"
                               class="w3-input w3-border" id="precio_<?= esc($tipo, 'attr') ?>" name="precio_<?= esc($tipo, 'attr') ?>"
                               value="<?= esc((string) old('precio_' . $tipo, number_format($precios[$tipo], 2, '.', '')), 'attr') ?>">
                        <span>/ L</span>
                    </div>
                    <small class="w3-text-grey">De fábrica: $<?= esc(number_format((float) ($fabrica[$tipo] ?? 0), 2)) ?></small>
                </div>
            <?php endforeach; ?>
        </div>
        <button type="submit" class="btn-guardar"><i class="fa-solid fa-check" aria-hidden="true"></i> Guardar precios</button>
    </form>

    <div class="rutas-panel w3-margin-top">
        <h3 style="margin-top:0;">Vehículos de los repartidores</h3>
        <?php if (! $conColumnas): ?>
            <p class="w3-text-grey">
                <?php if (es_tecnico()): ?>Falta correr <code>php spark migrate</code> (migración AddCombustible) para guardar el rendimiento de cada vehículo.
                <?php else: ?>El rendimiento por vehículo aún no está activado. Avisa al administrador del sistema.<?php endif; ?>
            </p>
        <?php endif; ?>
        <p class="w3-small w3-text-grey">El rendimiento y el combustible se capturan en <a href="<?= base_url('lista_repartidor') ?>">Repartidores</a> (o el propio repartidor en "Mis datos"). Si no los tiene, se usa uno típico del vehículo.</p>
        <div class="tabla-wrapper">
            <table>
                <thead><tr><th>Repartidor</th><th>Vehículo</th><th>Combustible</th><th>Rendimiento</th><th>Costo por km</th></tr></thead>
                <tbody>
                <?php foreach ($repartidores as $r): ?>
                    <?php $c = Combustible::calcular(1000, $r); ?>
                    <tr>
                        <td><?= esc(trim($r['nombre'] . ' ' . $r['ape_pat'])) ?></td>
                        <td><?= esc(Modelo_repartidor::VEHICULOS[$r['vehiculo'] ?? 'moto'] ?? '—') ?></td>
                        <?php if ($c === null): ?>
                            <td colspan="3" class="w3-text-grey">No usa combustible</td>
                        <?php else: ?>
                            <td><?= esc(Combustible::TIPOS[$c['combustible']]) ?><?= empty($r['combustible']) ? ' <small class="w3-text-grey">(típico)</small>' : '' ?></td>
                            <td><?= esc(number_format($c['rendimiento'], 1)) ?> km/L<?= $c['estimado'] ? ' <small class="w3-text-grey">(típico)</small>' : '' ?></td>
                            <td>$<?= esc(number_format($c['costo'], 2)) ?></td>
                        <?php endif; ?>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <?php include 'Footer.php'; ?>
</div>
</body>
</html>
