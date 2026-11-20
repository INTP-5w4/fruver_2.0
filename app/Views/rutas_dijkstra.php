<?php
/**
 * Camino de menor costo entre dos puntos con Dijkstra sobre calles de OpenStreetMap (Rutas::dijkstra).
 *
 * @var list<array<string,mixed>> $almacenes
 * @var list<array<string,mixed>> $direcciones  ubicadas (Modelo_direccion::ubicadas)
 * @var array<string,mixed>|null  $almacenMapa
 */
use App\Models\Modelo_ruta;

$opcionesPuntos = static function (array $almacenes, array $direcciones, string $elegido): string {
    $html = '<optgroup label="Almacenes">';
    foreach ($almacenes as $a) {
        if ($a['lat'] === null || $a['lng'] === null) {
            continue;
        }
        $v     = 'a' . (int) $a['id'];
        $html .= '<option value="' . $v . '"' . ($v === $elegido ? ' selected' : '') . '>' . esc($a['nombre']) . '</option>';
    }
    $html .= '</optgroup><optgroup label="Direcciones de clientes">';
    foreach ($direcciones as $d) {
        $v     = 'd' . (int) $d['id'];
        $html .= '<option value="' . $v . '"' . ($v === $elegido ? ' selected' : '') . '>'
            . esc($d['cliente'] . ' · ' . Modelo_ruta::textoDireccion($d)) . '</option>';
    }

    return $html . '</optgroup>';
};
$primerAlmacen = $almacenes !== [] ? 'a' . (int) $almacenes[0]['id'] : '';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <?= view('partials/estilos_panel') ?>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?= esc(csrf_hash(), 'attr') ?>">
    <?= view('partials/mapa_config', ['almacenMapa' => $almacenMapa]) ?>
    <script src="<?= base_url('js/rutas_dijkstra.js') ?>" defer></script>
    <title>Dijkstra entre dos puntos</title>
    <style>
        .dij-form { display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 12px; align-items: end; }
        .dij-cifras { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 10px; margin-top: 10px; }
        .dij-cifra { border: 1px solid var(--linea); border-radius: var(--r-tarjeta); padding: 10px 12px; background: var(--superficie); }
        .dij-cifra b { display: block; font-size: 1.2em; }
        .dij-leyenda span { display: inline-block; width: 22px; height: 4px; vertical-align: middle; margin: 0 4px 0 10px; }
    </style>
</head>
<body>
<?php include 'Header.php'; ?>
<?php include 'sidebar.php'; ?>
<div class="lista-wrapper">
    <p style="padding-top:70px;"><a href="<?= base_url('rutas') ?>">&larr; Volver a rutas</a></p>
    <h2><i class="fa-solid fa-route"></i> Camino de menor costo entre dos puntos (Dijkstra)</h2>
    <p>Fruver descarga las calles de la zona desde OpenStreetMap, arma un grafo dirigido (cada tramo de calle es una arista y
       se respetan los sentidos de circulación) y aplica el algoritmo de Dijkstra para encontrar el camino de menor tiempo o
       de menor distancia.</p>

    <?php if (count($almacenes) + count($direcciones) < 2): ?>
        <div class="w3-panel w3-pale-yellow w3-border"><p>Se necesitan al menos dos puntos ubicados en el mapa (almacén o direcciones de clientes).</p></div>
    <?php else: ?>
        <div class="rutas-panel">
            <div class="dij-form">
                <div>
                    <label for="d_origen"><b>Origen</b></label>
                    <select id="d_origen" class="w3-select w3-border"><?= $opcionesPuntos($almacenes, $direcciones, $primerAlmacen) ?></select>
                </div>
                <div>
                    <label for="d_destino"><b>Destino</b></label>
                    <select id="d_destino" class="w3-select w3-border"><?= $opcionesPuntos($almacenes, $direcciones, '') ?></select>
                </div>
                <div>
                    <label for="d_criterio"><b>Peso de las aristas</b></label>
                    <select id="d_criterio" class="w3-select w3-border">
                        <option value="tiempo">Tiempo (según el tipo de calle)</option>
                        <option value="distancia">Distancia (metros)</option>
                    </select>
                </div>
                <div>
                    <button type="button" class="w3-button w3-green" id="btnDijkstra"><i class="fa-solid fa-play"></i> Calcular camino</button>
                </div>
            </div>
            <p class="w3-small" id="estadoDijkstra" role="status"></p>
        </div>

        <div class="rutas-panel w3-margin-top">
            <div class="rutas-mapa" id="mapaDijkstra"></div>
            <p class="geo-estado" data-mapa-estado hidden></p>
            <p class="w3-small dij-leyenda" id="leyendaDijkstra" hidden>
                <span style="background:#c62828"></span>Camino de Dijkstra
                <span style="background:#1565c0; opacity:.6"></span><span id="leyendaServicio">Servicio de rutas</span>
            </p>
            <div class="dij-cifras" id="cifrasDijkstra"></div>
        </div>
    <?php endif; ?>
</div>
<script type="application/json" id="dijkstra-config"><?= json_encode([
    'url'        => base_url('rutas/dijkstra'),
    'csrfNombre' => csrf_token(),
], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>
</body>
</html>
