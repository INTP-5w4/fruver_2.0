<?php
/**
 * Gestión de roles (solo superadmin). Ver App\Controllers\Roles.
 *
 * @var list<array<string,mixed>>                          $cuentas
 * @var \CodeIgniter\Pager\Pager                           $pager
 * @var array<string,int>                                  $conteo
 * @var array<string,array{title:string,description:string}> $catalogo
 * @var string                                             $buscar
 * @var string                                             $rol      filtro activo ('' = todos)
 * @var int                                                $yo       id de la cuenta en sesión
 * @var bool                                               $enumAlDia
 * @var array<int,array<string,int>>                       $pendientes id de cuenta => trabajo abierto (Modelo_repartidor::pendientes)
 * @var list<array<string,mixed>>                          $bitacora  últimos cambios de rol
 */
use App\Models\Modelo_repartidor;

$pendientes ??= [];
$bitacora   ??= [];
$pagina   = (int) ($pager->getCurrentPage() ?? 1);
$claseRol = ['superadmin' => 'est-cancelado', 'admin' => 'est-confirmado', 'developer' => 'est-en_transito', 'repartidor' => 'est-realizado', 'user' => 'est-entregado'];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <?= view('partials/estilos_panel') ?>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Roles</title>
    <style>
        .roles-filtros { display: flex; flex-wrap: wrap; gap: 8px; margin: 0 0 16px; }
        .roles-chip { display: inline-flex; align-items: center; gap: 6px; padding: 6px 12px; border: 1px solid var(--linea); border-radius: 999px;
                      background: var(--superficie); color: var(--tinta); text-decoration: none; font-size: 14px; }
        .roles-chip strong { font-variant-numeric: tabular-nums; }
        .roles-chip--activo { background: var(--hoja); border-color: var(--hoja); color: #fff; }
        .roles-form { display: flex; gap: 6px; align-items: center; flex-wrap: wrap; }
        .roles-form select { min-width: 170px; padding: 6px 8px; border: 1px solid var(--borde-control); border-radius: var(--r-control); background: var(--superficie); }
        .roles-ayuda { color: var(--tinta-suave, #5f6f62); font-size: 13px; }
        .roles-leyenda { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 8px 16px; margin: 0 0 16px; padding: 0; list-style: none; }
        .roles-leyenda li { font-size: 13px; }
        .roles-baja { flex-basis: 100%; display: grid; gap: 6px; margin-top: 4px; padding: 8px; border: 1px dashed var(--linea); border-radius: var(--r-control); font-size: 13px; }
        .roles-baja[hidden] { display: none; }
        .roles-baja input[type="text"] { padding: 5px 8px; border: 1px solid var(--borde-control); border-radius: var(--r-control); }
        .roles-baja label { display: flex; gap: 6px; align-items: flex-start; }
        .roles-alerta { color: #a4262c; }
        .roles-fichas { display: grid; gap: 4px; }
        .roles-bitacora { margin-top: 24px; }
        .roles-bitacora h2 { font-size: 18px; margin: 0 0 8px; }
    </style>
</head>
<body>
    <?php include 'Header.php'; ?>
    <?php include 'sidebar.php'; ?>

<div class="lista-wrapper">
    <div class="flash-container">
        <?php if (session()->getFlashdata('error')): ?>
            <div class="w3-panel w3-red w3-animate-opacity"><p><?= esc(session()->getFlashdata('error')) ?></p></div>
        <?php endif; ?>
        <?php if (session()->getFlashdata('mensaje')): ?>
            <div class="w3-panel w3-green w3-animate-opacity"><p><?= esc(session()->getFlashdata('mensaje')) ?></p></div>
        <?php endif; ?>
    </div>

    <div class="contenedor-boton">
        <h1>Roles</h1>
    </div>

    <?php if (! $enumAlDia): ?>
        <div class="aviso aviso-error aviso-lista" role="alert">
            <i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i>
            <span>Los roles de la base de datos no coinciden con los configurados.
                <?php if (es_tecnico()): ?>Corre <code>php spark roles:sincronizar</code> (o <code>php spark migrate</code> si es la primera vez).<?php else: ?>Avisa al administrador del sistema.<?php endif; ?>
            </span>
        </div>
    <?php endif; ?>

    <ul class="roles-leyenda">
        <?php foreach ($catalogo as $clave => $g): ?>
            <li><span class="est <?= esc($claseRol[$clave] ?? 'est-pendiente', 'attr') ?>"><?= esc($g['title']) ?></span> <?= esc($g['description'] ?? '') ?></li>
        <?php endforeach; ?>
    </ul>

    <nav class="roles-filtros" aria-label="Filtrar por rol">
        <a href="<?= base_url('roles') . ($buscar !== '' ? '?buscar=' . rawurlencode($buscar) : '') ?>" class="roles-chip<?= $rol === '' ? ' roles-chip--activo' : '' ?>">
            Todas <strong><?= (int) array_sum($conteo) ?></strong>
        </a>
        <?php foreach ($catalogo as $clave => $g): ?>
            <a href="<?= base_url('roles') . '?' . http_build_query(array_filter(['rol' => $clave, 'buscar' => $buscar])) ?>"
               class="roles-chip<?= $rol === $clave ? ' roles-chip--activo' : '' ?>"<?= $rol === $clave ? ' aria-current="true"' : '' ?>>
                <?= esc($g['title']) ?> <strong><?= (int) ($conteo[$clave] ?? 0) ?></strong>
            </a>
        <?php endforeach; ?>
    </nav>

    <form method="get" action="<?= base_url('roles') ?>">
        <div>
            <?php if ($rol !== ''): ?><input type="hidden" name="rol" value="<?= esc($rol, 'attr') ?>"><?php endif; ?>
            <input type="search" name="buscar" value="<?= esc($buscar, 'attr') ?>" maxlength="100"
                   placeholder="Buscar por id, usuario o correo…" class="w3-input w3-border" aria-label="Buscar cuenta">
            <button type="submit" class="w3-button w3-green"><i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i> Buscar</button>
            <?php if ($buscar !== ''): ?>
                <a href="<?= base_url('roles') . ($rol !== '' ? '?rol=' . rawurlencode($rol) : '') ?>" class="w3-button w3-border">✕ Limpiar</a>
            <?php endif; ?>
        </div>
    </form>

    <div class="tabla-wrapper">
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Cuenta</th>
                    <th>Correo</th>
                    <th>Rol actual</th>
                    <th>Cambiar rol</th>
                    <th>Historial (fichas)</th>
                    <th>Último acceso</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($cuentas === []): ?>
                    <tr><td colspan="7" class="w3-center w3-text-grey">No hay cuentas con ese criterio.</td></tr>
                <?php endif; ?>
                <?php foreach ($cuentas as $c): ?>
                    <?php
                    $roles  = array_values(array_filter(explode(',', (string) $c['roles'])));
                    $actual = $roles[0] ?? '';
                    $esYo   = (int) $c['id'] === $yo;
                    $pend   = $pendientes[(int) $c['id']] ?? null; // solo si hoy es repartidor activo
                    $enCamino = $pend !== null ? $pend['entregas_en_ruta'] + $pend['rutas_en_curso'] : 0;
                    $sinSalir = $pend !== null ? $pend['entregas_asignadas'] : 0;
                    $repBaja  = $c['id_repartidor'] !== null && (int) ($c['rep_activo'] ?? 1) === 0;
                    ?>
                    <tr>
                        <td><?= (int) $c['id'] ?></td>
                        <td>
                            <?= esc((string) ($c['username'] ?? '—')) ?>
                            <?php if ($esYo): ?><small class="roles-ayuda">(tú)</small><?php endif; ?>
                            <?php if ((int) $c['active'] !== 1): ?><span class="est est-pendiente" title="La cuenta no ha activado su acceso">Inactiva</span><?php endif; ?>
                        </td>
                        <td><?= esc((string) ($c['correo'] ?? '—')) ?></td>
                        <td>
                            <?php if ($roles === []): ?>
                                <span class="est est-pendiente">Sin rol</span>
                            <?php endif; ?>
                            <?php foreach ($roles as $r): ?>
                                <span class="est <?= esc($claseRol[$r] ?? 'est-pendiente', 'attr') ?>"><?= esc($catalogo[$r]['title'] ?? $r) ?></span>
                            <?php endforeach; ?>
                        </td>
                        <td>
                            <?php if ($esYo): ?>
                                <span class="roles-ayuda">No puedes cambiar tu propio rol</span>
                            <?php else: ?>
                                <form method="post" action="<?= base_url('roles/' . (int) $c['id']) ?>" class="roles-form"<?= $pend !== null ? ' data-repartidor="1"' : '' ?>
                                      onsubmit="return this.rol.value === <?= esc(json_encode($actual), 'attr') ?> || confirm('¿Cambiar el rol de <?= esc(addslashes((string) ($c['username'] ?? $c['id'])), 'attr') ?> a ' + this.rol.options[this.rol.selectedIndex].text + '?<?= $pend !== null ? '\n\nSu ficha de repartidor quedará dada de baja (conserva su historial).' : '' ?>');">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="buscar" value="<?= esc($buscar, 'attr') ?>">
                                    <input type="hidden" name="filtro_rol" value="<?= esc($rol, 'attr') ?>">
                                    <input type="hidden" name="page" value="<?= $pagina ?>">
                                    <label class="w3-hide" for="rol-<?= (int) $c['id'] ?>">Rol de la cuenta <?= (int) $c['id'] ?></label>
                                    <select name="rol" id="rol-<?= (int) $c['id'] ?>">
                                        <?php foreach ($catalogo as $clave => $g): ?>
                                            <option value="<?= esc($clave, 'attr') ?>"<?= $clave === $actual ? ' selected' : '' ?>><?= esc($g['title']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <button type="submit" class="w3-button w3-small w3-green">Guardar</button>
                                    <?php if ($pend !== null): ?>
                                        <?php // Se muestra al elegir un rol distinto de repartidor: su ficha se dará de baja. ?>
                                        <div class="roles-baja" hidden>
                                            <?php if ($enCamino > 0): ?>
                                                <span class="roles-alerta"><i class="fa-solid fa-truck-fast" aria-hidden="true"></i>
                                                    Tiene <?= (int) $pend['entregas_en_ruta'] ?> entrega(s) en camino. Ciérralas o cancélalas en
                                                    <a href="<?= base_url('lista_entrega') ?>">Entregas</a> antes de quitarle el rol.</span>
                                            <?php endif; ?>
                                            <label for="motivo-<?= (int) $c['id'] ?>">Motivo de la baja (opcional)</label>
                                            <input type="text" name="motivo" id="motivo-<?= (int) $c['id'] ?>" maxlength="255" placeholder="Ej. terminó su contrato"
                                                   aria-describedby="motivo-ayuda-<?= (int) $c['id'] ?>">
                                            <small id="motivo-ayuda-<?= (int) $c['id'] ?>" class="roles-ayuda">Queda en el historial: no escribas datos sensibles (salud, conflictos personales, etc.).</small>
                                            <?php if ($sinSalir > 0): ?>
                                                <label><input type="checkbox" name="cancelar_pendientes" value="1">
                                                    <span>Cancelar sus <?= (int) $sinSalir ?> entrega(s) pendientes que aún no salen (los pedidos quedarán listos para otro repartidor)</span></label>
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>
                                </form>
                            <?php endif; ?>
                        </td>
                        <td><div class="roles-fichas">
                            <?php $esCliente = ($c['id_cliente'] ?? null) !== null; ?>
                            <?php if ($esCliente): ?>
                                <span title="Sus pedidos se conservan con cualquier rol">Cliente: <?= (int) ($c['pedidos_cliente'] ?? 0) ?> pedido(s)</span>
                            <?php endif; ?>
                            <?php if ($c['id_repartidor'] === null): ?>
                                <?php if (! $esCliente): ?><span class="roles-ayuda">—</span><?php endif; ?>
                            <?php elseif ($repBaja): ?>
                                <span><span class="est est-cancelada" title="Conserva su historial; ya no recibe entregas">Repartidor dado de baja</span>
                                    <?php if (! empty($c['rep_fecha_baja'])): ?><small class="roles-ayuda"><?= esc(date('d/m/Y', strtotime((string) $c['rep_fecha_baja']))) ?></small><?php endif; ?></span>
                            <?php elseif (Modelo_repartidor::faltanDatos(['nombre' => $c['rep_nombre'], 'ape_pat' => $c['rep_ape_pat'], 'telefono' => $c['rep_telefono'], 'direccion' => $c['rep_direccion']])): ?>
                                <span>Repartidor: <span class="est est-pendiente" title="Se le pedirán al iniciar sesión">Faltan datos</span></span>
                            <?php else: ?>
                                <span>Repartidor: <span class="est est-entregado">Completa</span></span>
                            <?php endif; ?>
                            <?php if ($enCamino + $sinSalir > 0): ?>
                                <small class="roles-ayuda"><?= (int) $sinSalir ?> por salir · <?= (int) $pend['entregas_en_ruta'] ?> en camino</small>
                            <?php endif; ?>
                        </div></td>
                        <td><?= $c['last_active'] ? esc(date('d/m/Y H:i', strtotime((string) $c['last_active']))) : '<span class="roles-ayuda">Nunca</span>' ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?= $pager->links('default', 'w3_pager') ?>
    </div>

    <?php if ($bitacora !== []): ?>
        <section class="roles-bitacora" aria-labelledby="titulo-bitacora-roles">
            <h2 id="titulo-bitacora-roles">Últimos cambios de rol</h2>
            <div class="tabla-wrapper">
                <table>
                    <thead>
                        <tr><th>Fecha</th><th>Cuenta</th><th>Cambio</th><th>Motivo</th><th>Qué pasó con sus fichas</th><th>Lo hizo</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($bitacora as $b): ?>
                            <tr>
                                <td><?= esc(date('d/m/Y H:i', strtotime((string) $b['fecha']))) ?></td>
                                <td><?= esc((string) $b['cuenta']) ?> <small class="roles-ayuda">#<?= (int) $b['id_user'] ?></small></td>
                                <td><?= esc(implode(', ', array_map(static fn (string $r): string => $catalogo[$r]['title'] ?? $r, array_filter(explode(',', (string) $b['rol_antes'])))) ?: 'Sin rol') ?>
                                    → <?= esc($catalogo[$b['rol_despues']]['title'] ?? (string) $b['rol_despues']) ?></td>
                                <td><?= esc((string) ($b['motivo'] ?? '—')) ?></td>
                                <td><?= esc((string) ($b['detalle'] ?? '—')) ?></td>
                                <td><?= esc((string) $b['por_usuario']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>
    <?php endif; ?>

    <?php include 'Footer.php'; ?>
</div>
<script>
// Al quitarle el rol a un repartidor se muestran el motivo y la cancelación de sus pendientes.
document.querySelectorAll('form[data-repartidor] select[name="rol"]').forEach(function (sel) {
    var caja = sel.form.querySelector('.roles-baja');
    var cambiar = function () { caja.hidden = sel.value === 'repartidor'; };
    sel.addEventListener('change', cambiar);
    cambiar();
});
</script>
</body>
</html>
