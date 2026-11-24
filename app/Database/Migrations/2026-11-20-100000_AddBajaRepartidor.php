<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;
use CodeIgniter\Database\RawSql;

/**
 * Baja lógica de repartidores y bitácora de cambios de rol.
 *
 * El ROL (grupo de Shield) dice qué puede hacer una cuenta; la FICHA (repartidor, cliente)
 * guarda la historia del negocio. Por eso, cuando un repartidor deja de serlo, su ficha no
 * se borra (entregas, rutas y pedidos la siguen apuntando): se marca como dada de baja y deja
 * de aparecer al asignar entregas, pedidos o rutas. Si vuelve, se reactiva la misma ficha y
 * conserva todo su historial.
 *
 *  repartidor.activo       1 = puede recibir entregas · 0 = dado de baja
 *  repartidor.fecha_baja   cuándo se dio de baja
 *  repartidor.motivo_baja  por qué (lo escribe quien la da de baja)
 *  repartidor.baja_por     users.id de quien la dio de baja
 *
 *  rol_bitacora            un renglón por cada cambio de rol: quién, a quién, de qué a qué,
 *                          por qué y qué pasó con su ficha. Sin llaves foráneas (igual que
 *                          pedido_bitacora): el registro se conserva aunque se borre la cuenta.
 *
 * Al instalarla, las fichas enlazadas a una cuenta que ya no tiene el rol de repartidor
 * quedan dadas de baja. Las fichas sin cuenta (capturadas a mano) siguen activas.
 *
 * Idempotente: no vuelve a crear lo que ya existe.
 */
class AddBajaRepartidor extends Migration
{
    public function up()
    {
        $campos = [];
        if (! $this->db->fieldExists('activo', 'repartidor')) {
            $campos['activo'] = ['type' => 'TINYINT', 'constraint' => 1, 'null' => false, 'default' => 1];
        }
        if (! $this->db->fieldExists('fecha_baja', 'repartidor')) {
            $campos['fecha_baja'] = ['type' => 'DATETIME', 'null' => true, 'default' => null];
        }
        if (! $this->db->fieldExists('motivo_baja', 'repartidor')) {
            $campos['motivo_baja'] = ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true, 'default' => null];
        }
        if (! $this->db->fieldExists('baja_por', 'repartidor')) {
            $campos['baja_por'] = ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true, 'default' => null];
        }
        if ($campos !== []) {
            $this->forge->addColumn('repartidor', $campos);
            $this->db->query('ALTER TABLE `repartidor` ADD INDEX `idx_repartidor_activo` (`activo`)');
        }

        if (! $this->db->tableExists('rol_bitacora')) {
            $this->forge->addField([
                'id'          => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'auto_increment' => true],
                'id_user'     => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
                'cuenta'      => ['type' => 'VARCHAR', 'constraint' => 255],
                'rol_antes'   => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
                'rol_despues' => ['type' => 'VARCHAR', 'constraint' => 30],
                'motivo'      => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
                'detalle'     => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
                'por_id_user' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
                'por_usuario' => ['type' => 'VARCHAR', 'constraint' => 255],
                'fecha'       => ['type' => 'DATETIME', 'null' => false, 'default' => new RawSql('CURRENT_TIMESTAMP')],
            ]);
            $this->forge->addPrimaryKey('id');
            $this->forge->addKey(['id_user', 'id'], false, false, 'idx_rol_bitacora_cuenta');
            $this->forge->addKey('fecha', false, false, 'idx_rol_bitacora_fecha');
            $this->forge->createTable('rol_bitacora', true, [
                'ENGINE'  => 'InnoDB',
                'CHARSET' => 'utf8mb4',
                'COLLATE' => 'utf8mb4_general_ci',
            ]);
        }

        // Fichas cuya cuenta ya no es repartidor (o fue borrada): quedan dadas de baja.
        $this->db->query(
            "UPDATE repartidor r
                SET r.activo = 0,
                    r.fecha_baja = COALESCE(r.fecha_baja, NOW()),
                    r.motivo_baja = COALESCE(r.motivo_baja, 'Su cuenta ya no tenía el rol de repartidor al instalar la baja de repartidores.')
              WHERE r.activo = 1
                AND r.id_user IS NOT NULL
                AND NOT EXISTS (SELECT 1 FROM auth_groups_users g
                                  JOIN users u ON u.id = g.user_id AND u.deleted_at IS NULL
                                 WHERE g.user_id = r.id_user AND g.`group` = 'repartidor')"
        );
    }

    public function down()
    {
        $this->forge->dropTable('rol_bitacora', true);

        if ($this->db->fieldExists('activo', 'repartidor')) {
            $this->db->query('ALTER TABLE `repartidor` DROP INDEX `idx_repartidor_activo`');
        }
        foreach (['activo', 'fecha_baja', 'motivo_baja', 'baja_por'] as $c) {
            if ($this->db->fieldExists($c, 'repartidor')) {
                $this->forge->dropColumn('repartidor', $c);
            }
        }
    }
}
