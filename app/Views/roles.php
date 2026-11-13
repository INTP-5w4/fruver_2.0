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
 */
use App\Models\Modelo_repartidor;

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
                    <th>Ficha de repartidor</th>
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
                                <form method="post" action="<?= base_url('roles/' . (int) $c['id']) ?>" class="roles-form"
                                      onsubmit="return this.rol.value === <?= esc(json_encode($actual), 'attr') ?> || confirm('¿Cambiar el rol de <?= esc(addslashes((string) ($c['username'] ?? $c['id'])), 'attr') ?> a ' + this.rol.options[this.rol.selectedIndex].text + '?');">
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
                                </form>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($c['id_repartidor'] === null): ?>
                                <span class="roles-ayuda">—</span>
                            <?php elseif (Modelo_repartidor::faltanDatos(['nombre' => $c['rep_nombre'], 'ape_pat' => $c['rep_ape_pat'], 'telefono' => $c['rep_telefono'], 'direccion' => $c['rep_direccion']])): ?>
                                <span class="est est-pendiente" title="Se le pedirán al iniciar sesión">Faltan datos</span>
                            <?php else: ?>
                                <span class="est est-entregado">Completa</span>
                            <?php endif; ?>
                        </td>
                        <td><?= $c['last_active'] ? esc(date('d/m/Y H:i', strtotime((string) $c['last_active']))) : '<span class="roles-ayuda">Nunca</span>' ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?= $pager->links('default', 'w3_pager') ?>
    </div>

    <?php include 'Footer.php'; ?>
</div>
</body>
</html>
