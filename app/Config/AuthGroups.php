<?php

declare(strict_types=1);

/**
 * This file is part of CodeIgniter Shield.
 *
 * (c) CodeIgniter Foundation <admin@codeigniter.com>
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Config;

use CodeIgniter\Shield\Config\AuthGroups as ShieldAuthGroups;

class AuthGroups extends ShieldAuthGroups
{
    /**
     * --------------------------------------------------------------------
     * Default Group
     * --------------------------------------------------------------------
     * The group that a newly registered user is added to.
     */
    public string $defaultGroup = 'user';

    /**
     * --------------------------------------------------------------------
     * Groups
     * --------------------------------------------------------------------
     * An associative array of the available groups in the system, where the keys
     * are the group names and the values are arrays of the group info.
     *
     * Whatever value you assign as the key will be used to refer to the group
     * when using functions such as:
     *      $user->addGroup('superadmin');
     *
     * @var array<string, array<string, string>>
     *
     * FRUVER: la columna auth_groups_users.group es un ENUM con exactamente estas claves
     * (migración 2026-11-12-110000_RolesComoEnum). Si agregas o quitas un rol aquí, corre
     * `php spark roles:sincronizar` para actualizar el ENUM; la pestaña Roles avisa si difieren.
     *
     * @see https://codeigniter4.github.io/shield/quick_start_guide/using_authorization/#change-available-groups for more info
     */
    public array $groups = [
        'superadmin' => [
            'title'       => 'Superadministración',
            'description' => 'Control total del sistema, incluida la gestión de roles.',
        ],
        'admin' => [
            'title'       => 'Administración',
            'description' => 'Operación diaria: pedidos, inventario, clientes y reparto.',
        ],
        'developer' => [
            'title'       => 'Desarrollo',
            'description' => 'Programadores del sistema: ven los mensajes técnicos.',
        ],
        'user' => [
            'title'       => 'Cliente',
            'description' => 'Compra en la tienda. Rol de toda cuenta nueva.',
        ],
        'repartidor' => [
            'title'       => 'Repartidor',
            'description' => 'Reparte pedidos: solo ve y actualiza sus propias entregas.',
        ],
    ];

    /**
     * --------------------------------------------------------------------
     * Permissions
     * --------------------------------------------------------------------
     * The available permissions in the system.
     *
     * If a permission is not listed here it cannot be used.
     */
    public array $permissions = [
        'admin.access'        => 'Can access the sites admin area',
        'admin.settings'      => 'Can access the main site settings',
        'users.manage-admins' => 'Can manage other admins',
        'users.create'        => 'Can create new non-admin users',
        'users.edit'          => 'Can edit existing non-admin users',
        'users.delete'        => 'Can delete existing non-admin users',
        'entregas.asignar'    => 'Puede asignar repartidor y cancelar entregas',
        'entregas.propias'    => 'Puede ver y actualizar sus propias entregas',
    ];

    /**
     * --------------------------------------------------------------------
     * Permissions Matrix
     * --------------------------------------------------------------------
     * Maps permissions to groups.
     *
     * This defines group-level permissions.
     */
    public array $matrix = [
        'superadmin' => [
            'admin.*',
            'users.*',
            'entregas.*',
        ],
        'admin' => [
            'admin.access',
            'users.create',
            'users.edit',
            'users.delete',
            'entregas.asignar',
        ],
        'developer' => [
            'admin.access',
            'admin.settings',
            'users.create',
            'users.edit',
        ],
        'user' => [],
        'repartidor' => [
            'entregas.propias',
        ],
    ];
}
