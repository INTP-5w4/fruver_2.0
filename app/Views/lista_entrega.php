<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <?= view('partials/estilos_panel') ?>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Entregas · Fruver</title>
    <style>
        .por-asignar { margin: 0 0 22px; }
        .por-asignar-toggle {
            width: 100%; display: flex; align-items: center; gap: 14px; text-align: left; cursor: pointer;
            background: #fff; border: 1px solid #e2e6e0; border-left: 5px solid #1e7e34; border-radius: 12px;
            padding: 14px 18px; box-shadow: 0 1px 3px rgba(0,0,0,.06); transition: box-shadow .15s ease;
        }
        .por-asignar-toggle:hover { box-shadow: 0 4px 12px rgba(0,0,0,.09); }
        .por-asignar-campana {
            position: relative; flex: 0 0 auto; width: 36px; height: 36px; display: grid; place-items: center;
            background: #e9f5ec; color: #1e7e34; border-radius: 50%; font-size: 1.15rem;
        }
        .por-asignar-badge {
            position: absolute; top: -6px; right: -6px; min-width: 20px; height: 20px; padding: 0 5px;
            background: #e0352b; color: #fff; font-size: .72rem; font-weight: 700; border-radius: 10px; display: grid; place-items: center;
        }
        .por-asignar-resumen { flex: 1 1 auto; display: flex; flex-direction: column; line-height: 1.3; }
        .por-asignar-resumen strong { font-size: 1.05rem; color: #1f2a24; }
        .por-asignar-resumen small { color: #67726b; }
        .por-asignar-flecha { color: #8a938c; transition: transform .2s ease; }
        .por-asignar-toggle[aria-expanded="true"] .por-asignar-flecha { transform: rotate(180deg); }
        .por-asignar-cuerpo { margin-top: 14px; }
        .por-asignar-ayuda {
            display: flex; gap: 8px; align-items: flex-start; margin: 0 0 14px; padding: 10px 14px;
            background: #f4f8f4; border-radius: 10px; color: #5a6b5e; font-size: .9rem;
        }
        .grupos-estado { display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: 14px; }
        .grupo-estado {
            display: flex; flex-direction: column; background: #fff; border: 1px solid #e2e6e0;
            border-radius: 12px; padding: 14px; box-shadow: 0 1px 3px rgba(0,0,0,.05);
        }
        .grupo-estado.grupo-estado--vacio { opacity: .5; }
        .grupo-cabeza { display: flex; flex-wrap: wrap; gap: 10px; align-items: center; justify-content: space-between; margin-bottom: 10px; }
        .grupo-titulo { margin: 0; font-size: 1.05rem; color: #1f2a24; display: flex; align-items: center; gap: 8px; }
        .grupo-titulo i { color: #1e7e34; }
        .grupo-cuenta { background: #e9f5ec; color: #1e7e34; font-size: .78rem; font-weight: 700; border-radius: 10px; padding: 1px 9px; }
        .grupo-repartidor { display: flex; flex-direction: column; gap: 2px; font-size: .74rem; color: #67726b; }
        .grupo-repartidor select { font-size: .9rem; padding: 6px 8px; border: 1px solid #cdd4cd; border-radius: 8px; max-width: 210px; }
        .grupo-pedidos { list-style: none; margin: 0 0 10px; padding: 0; display: flex; flex-direction: column; gap: 6px; max-height: 280px; overflow-y: auto; }
        .grupo-pedido { display: flex; align-items: center; gap: 10px; background: #f7f9f7; border-radius: 8px; padding: 7px 10px; }
        .grupo-pedido-datos { flex: 1 1 auto; display: flex; flex-direction: column; line-height: 1.25; min-width: 0; }
        .grupo-pedido-datos strong { color: #1f2a24; font-size: .92rem; }
        .grupo-pedido-datos span { color: #67726b; font-size: .8rem; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .grupo-quitar { flex: 0 0 auto; width: 28px; height: 28px; border: 0; background: transparent; color: #b0453d; border-radius: 50%; cursor: pointer; display: grid; place-items: center; transition: background .15s ease; }
        .grupo-quitar:hover { background: #fbe9e7; }
        .grupo-pie { display: flex; align-items: center; justify-content: space-between; gap: 10px; margin-top: auto; }
        .grupo-vacio { color: #8a938c; font-size: .85rem; }
    </style>
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

<div class="contenedor-boton">
    <h1>Entregas</h1>
    <div class="pagina-acciones">
        <?php if (rutas_listas()): ?>
            <a href="<?= base_url('rutas') ?>" class="btn btn-secundario">
                <i class="fa-solid fa-route" aria-hidden="true"></i> Planear rutas con mapa
            </a>
        <?php endif; ?>
        <button type="button" onclick="document.getElementById('modalAsignarEntrega').style.display='block'" class="btn-agregar">
            <i class="fa-solid fa-plus" aria-hidden="true"></i> Asignar repartidor
        </button>
    </div>
</div>

<?php if (! empty($grupos)): ?>
<section class="por-asignar" aria-labelledby="por-asignar-titulo">
    <button type="button" class="por-asignar-toggle" data-toggle-asignar aria-expanded="false" aria-controls="por-asignar-cuerpo">
        <span class="por-asignar-campana">
            <i class="fa-solid fa-bell" aria-hidden="true"></i>
            <span class="por-asignar-badge"><?= count($pedidos) ?></span>
        </span>
        <span class="por-asignar-resumen">
            <strong id="por-asignar-titulo">Pedidos por asignar</strong>
            <small><?= count($pedidos) ?> pedido(s) en <?= count($grupos) ?> estado(s) · agrupados y pre-asignados a un repartidor</small>
        </span>
        <i class="fa-solid fa-chevron-down por-asignar-flecha" aria-hidden="true"></i>
    </button>

    <div class="por-asignar-cuerpo" id="por-asignar-cuerpo" hidden>
        <p class="por-asignar-ayuda">
            <i class="fa-solid fa-circle-info" aria-hidden="true"></i>
            Cada estado ya trae un repartidor sugerido. Puedes cambiarlo, quitar pedidos del grupo con la «✕», y asignar todo el grupo de un clic.
        </p>

        <div class="grupos-estado">
            <?php foreach ($grupos as $g): ?>
                <form method="post" action="<?= base_url('asigna_grupo') ?>" class="grupo-estado" data-grupo>
                    <?= csrf_field() ?>
                    <header class="grupo-cabeza">
                        <h3 class="grupo-titulo">
                            <i class="fa-solid fa-location-dot" aria-hidden="true"></i>
                            <?= esc($g['estado']) ?>
                            <span class="grupo-cuenta" data-grupo-cuenta><?= count($g['pedidos']) ?></span>
                        </h3>
                        <label class="grupo-repartidor">
                            <span class="grupo-repartidor-et">Repartidor</span>
                            <select name="id_repartidor" required>
                                <?php foreach ($repartidores as $r): ?>
                                    <?php $sinCuenta = array_key_exists('id_user', $r) && $r['id_user'] === null; ?>
                                    <option value="<?= (int) $r['id'] ?>"<?= (int) $r['id'] === (int) ($g['repartidor_sugerido'] ?? 0) ? ' selected' : '' ?>>
                                        <?= esc(trim($r['nombre'] . ' ' . $r['ape_pat'])) ?><?= $sinCuenta ? ' — sin cuenta' : '' ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                    </header>

                    <ul class="grupo-pedidos">
                        <?php foreach ($g['pedidos'] as $p): ?>
                            <li class="grupo-pedido" data-pedido>
                                <input type="hidden" name="id_pedidos[]" value="<?= (int) $p['id'] ?>">
                                <span class="grupo-pedido-datos">
                                    <strong>#<?= (int) $p['id'] ?></strong>
                                    <span><?= esc($p['cliente']) ?><?= ! empty($p['municipio']) ? ' · ' . esc($p['municipio']) : '' ?> · <?= esc($p['fecha']) ?></span>
                                </span>
                                <button type="button" class="grupo-quitar" data-quitar aria-label="Quitar el pedido #<?= (int) $p['id'] ?> de este grupo" title="Quitar del grupo">
                                    <i class="fa-solid fa-xmark" aria-hidden="true"></i>
                                </button>
                            </li>
                        <?php endforeach; ?>
                    </ul>

                    <footer class="grupo-pie">
                        <span class="grupo-vacio" hidden><i class="fa-solid fa-inbox" aria-hidden="true"></i> Sin pedidos en el grupo</span>
                        <button type="submit" class="btn btn-primario btn-chico grupo-asignar">
                            <i class="fa-solid fa-truck-fast" aria-hidden="true"></i>
                            Asignar grupo (<span data-grupo-cuenta><?= count($g['pedidos']) ?></span>)
                        </button>
                    </footer>
                </form>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<form method="get" action="<?= base_url('lista_entrega') ?>" class="w3-margin-bottom">
    <div>
        <input
            type="text"
            name="buscar"
            aria-label="Buscar entrega"
            value="<?= esc($buscar ?? '') ?>"
            placeholder="Buscar por pedido, estado, repartidor o cliente..."
            class="w3-input w3-border"

        >
        <button type="submit" class="w3-button w3-green">
            <i class="fa-solid fa-magnifying-glass"></i> Buscar
        </button>
        <?php if (!empty($buscar)): ?>
            <a href="<?= base_url('lista_entrega') ?>" class="w3-button w3-red"><i class="fa-solid fa-eraser" aria-hidden="true"></i> Limpiar</a>
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
                <th>Ruta</th>
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
                    <td><?= estado_entrega($e['estado']) ?></td>
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
                    <td><?= esc($e['incidencia'] ?? '—') ?>
                        <?php if (! empty($e['evidencia'])): ?>
                            <br><a href="<?= base_url('evidencia_entrega/' . (int) $e['id']) ?>" target="_blank" rel="noopener"><i class="fa-solid fa-camera"></i> evidencia</a>
                        <?php endif; ?>
                    </td>
                    <td><?php if (! empty($e['id_ruta'])): ?><a href="<?= base_url('rutas/' . (int) $e['id_ruta']) ?>">#<?= (int) $e['id_ruta'] ?> · parada <?= (int) $e['orden_ruta'] ?></a><?php else: ?>—<?php endif; ?></td>
                    <td>
                        <?php if (in_array($e['estado'], ['asignada', 'en_ruta'], true)): ?>
                            <form method="post" action="<?= base_url('cancela_entrega/' . (int) $e['id']) ?>" style="display:inline; margin:0;" onsubmit="return confirm('¿Cancelar esta entrega?');">
                                <?= csrf_field() ?>
                                <button type="submit" class="btn-icono" aria-label="Cancelar" title="Cancelar"><i class="fa-solid fa-ban" aria-hidden="true"></i></button>
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
    <div id="modalAsignarEntrega" class="w3-modal" style="display:none;" role="dialog" aria-modal="true" aria-labelledby="titulo-asignar-entrega">
        <div class="modal-contenido w3-animate-zoom">

            <header class="modal-header">
                <button type="button" class="modal-cerrar" data-cerrar-modal aria-label="Cerrar"><i class="fa-solid fa-xmark" aria-hidden="true"></i></button>
                <h2 id="titulo-asignar-entrega">Asignar repartidor</h2>
            </header>

            <form action="<?= base_url('asigna_entrega') ?>" method="post" class="modal-form">
                <?= csrf_field() ?>

                <?php if (empty($pedidos)): ?>
                    <div class="aviso aviso-info" role="status">
                        <i class="fa-solid fa-circle-info" aria-hidden="true"></i>
                        <span>No hay pedidos confirmados o en tránsito sin entrega asignada.</span>
                    </div>
                <?php endif; ?>

                <label for="id_pedido"><b>Pedido*</b> <span class="opcional">(confirmado o en tránsito)</span></label>
                <select name="id_pedido" id="id_pedido" class="modal-input" required<?= empty($pedidos) ? ' disabled' : '' ?>>
                    <option value="" disabled selected>Elige un pedido</option>
                    <?php foreach ($pedidos as $p): ?>
                        <option value="<?= (int) $p['id'] ?>">
                            #<?= (int) $p['id'] ?> · <?= esc($p['cliente']) ?> · <?= esc($p['fecha']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <label for="id_repartidor"><b>Repartidor*</b></label>
                <select name="id_repartidor" id="id_repartidor" class="modal-input" required>
                    <option value="" disabled selected>Elige un repartidor</option>
                    <?php foreach ($repartidores as $r): ?>
                        <?php $sinCuenta = array_key_exists('id_user', $r) && $r['id_user'] === null; ?>
                        <option value="<?= (int) $r['id'] ?>">
                            <?= esc(trim($r['nombre'] . ' ' . $r['ape_pat'])) ?><?= $sinCuenta ? ' — sin cuenta (no verá la entrega)' : '' ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <p class="campo-ayuda">Solo la verá en su celular si tiene cuenta (columna «Cuenta» en Repartidores).</p>

                <footer class="modal-footer">
                    <button type="submit" class="btn-guardar"<?= empty($pedidos) ? ' disabled' : '' ?>><i class="fa-solid fa-check" aria-hidden="true"></i> Asignar</button>
                    <button type="button" class="btn-cancelar" data-cerrar-modal><i class="fa-solid fa-xmark" aria-hidden="true"></i> Cancelar</button>
                </footer>
            </form>
        </div>
    </div>

<?php include 'Footer.php'; ?>
</div><!-- /.lista-wrapper -->

    <script>
        // Refleja cuántos pedidos quedan en un grupo y bloquea el botón si queda vacío.
        function actualizarGrupo(form) {
            var n = form.querySelectorAll('.grupo-pedido[data-pedido]').length;
            form.querySelectorAll('[data-grupo-cuenta]').forEach(function (el) { el.textContent = n; });
            form.classList.toggle('grupo-estado--vacio', n === 0);
            var boton = form.querySelector('.grupo-asignar');
            var vacio = form.querySelector('.grupo-vacio');
            if (boton) { boton.disabled = n === 0; boton.hidden = n === 0; }
            if (vacio) { vacio.hidden = n !== 0; }
        }

        document.addEventListener('click', function (ev) {
            // Cerrar cualquier modal.
            var cerrar = ev.target.closest('[data-cerrar-modal]');
            if (cerrar) { cerrar.closest('.w3-modal').style.display = 'none'; return; }

            // Colapsar / expandir el panel de "Pedidos por asignar".
            var toggle = ev.target.closest('[data-toggle-asignar]');
            if (toggle) {
                var cuerpo = document.getElementById('por-asignar-cuerpo');
                var abierto = toggle.getAttribute('aria-expanded') === 'true';
                toggle.setAttribute('aria-expanded', String(!abierto));
                if (cuerpo) { cuerpo.hidden = abierto; }
                return;
            }

            // Quitar un pedido de su grupo (no se asignará en este lote).
            var quitar = ev.target.closest('[data-quitar]');
            if (quitar) {
                var form = quitar.closest('[data-grupo]');
                var item = quitar.closest('[data-pedido]');
                if (item) { item.remove(); }
                if (form) { actualizarGrupo(form); }
                return;
            }
        });

        // Evita enviar un grupo vacío.
        document.querySelectorAll('[data-grupo]').forEach(function (form) {
            form.addEventListener('submit', function (ev) {
                if (form.querySelectorAll('.grupo-pedido[data-pedido]').length === 0) { ev.preventDefault(); }
            });
        });
    </script>
</body>
</html>
