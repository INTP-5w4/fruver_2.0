<?php

namespace App\Controllers;

use App\Models\Modelo_estatus;
use App\Models\Modelo_pedido;
use App\Models\PdoModelo;
use Throwable;

class Estatus extends BaseController
{
    private const ESTADOS = [
        'pedido_realizado', 'pedido_confirmado', 'pedido_en_transito', 'pedido_entregado',
        'pedido_a_credito', 'pedido_pagado', 'pedido_cancelado', 'pedido_pendiente',
    ];

    /** Mapa de transiciones (se conserva tal cual estaba en el proyecto). */
    private const FLUJO = [
        'pedido_pendiente'   => ['pedido_realizado', 'pedido_cancelado'],
        'pedido_realizado'   => ['pedido_confirmado', 'pedido_cancelado'],
        'pedido_confirmado'  => ['pedido_en_transito', 'pedido_cancelado'],
        'pedido_en_transito' => ['pedido_entregado', 'pedido_cancelado'],
        'pedido_entregado'   => ['pedido_credito', 'pedido_pagado'],
        'pedido_credito'     => ['pedido_pagado'],
        'pedido_pagado'      => [],
        'pedido_cancelado'   => [],
    ];

    private function texto(string $estado): string
    {
        return str_replace('_', ' ', $estado);
    }

    private function reglas(): array
    {
        return [
            'edo'       => 'required|in_list[' . implode(',', self::ESTADOS) . ']',
            'id_pedido' => 'required|is_natural_no_zero',
        ];
    }

    public function crea_estatus()
    {
        $datos = [
            'pedidos'   => (new Modelo_pedido())->findAll(),
            'timestamp' => date('Y-m-d H:i:s'),
        ];

        return view('crea_estatus', $datos);
    }

    public function guarda_estatus()
    {
        if (! $this->validate($this->reglas())) {
            return redirect()->to('crea_estatus')->with('mensaje', 'Todos los campos son obligatorios.');
        }

        $nuevo_estado = $this->post('edo');
        $id_pedido    = (int) $this->request->getPost('id_pedido');
        $fecha        = $this->fechaHoraSql($this->post('fecha'));

        if ($fecha === null) {
            return redirect()->to('crea_estatus')->with('mensaje', 'Todos los campos son obligatorios.');
        }

        $m_estatus       = new Modelo_estatus();
        $ultimo_registro = $m_estatus->ultimoPorId($id_pedido);

        if ($ultimo_registro) {
            $estado_actual = $ultimo_registro['estado'];

            if (! isset(self::FLUJO[$estado_actual]) || ! in_array($nuevo_estado, self::FLUJO[$estado_actual], true)) {
                return redirect()->back()->with(
                    'mensaje',
                    "Error: No puedes pasar de '" . $this->texto($estado_actual) . "' a '" . $this->texto($nuevo_estado) . "'."
                );
            }
        }

        try {
            $m_estatus->insert(['estado' => $nuevo_estado, 'fecha' => $fecha, 'id_pedido' => $id_pedido]);

            return redirect()->to($this->destino('lista_estatus'))->with('mensaje', 'Estatus actualizado correctamente.');
        } catch (Throwable $e) {
            return redirect()->to('lista_estatus')->with('mensaje', PdoModelo::mensajeSeguro($e, 'No se pudo registrar el estatus.'));
        }
    }

    public function lista_estatus()
    {
        $datos['estatus'] = (new Modelo_estatus())->findAll();
        $datos['pedidos'] = (new Modelo_pedido())->findAll();

        return view('lista_estatus', $datos);
    }

    public function recupera($id = null)
    {
        $estatus = (new Modelo_estatus())->find($id);

        if (! $estatus) {
            return redirect()->to('lista_estatus');
        }

        return view('modifica_estatus', [
            'estatus' => $estatus,
            'pedidos' => (new Modelo_pedido())->findAll(),
        ]);
    }

    public function eliminar_datos($id = null)
    {
        $m_estatus = new Modelo_estatus();

        if (! $m_estatus->find($id)) {
            return redirect()->to('lista_estatus');
        }

        try {
            $m_estatus->delete($id);

            return redirect()->to('lista_estatus')->with('mensaje', 'Estatus eliminado correctamente');
        } catch (Throwable $e) {
            return redirect()->to('lista_estatus')->with('mensaje', PdoModelo::mensajeSeguro($e, 'No se pudo eliminar el estatus.'));
        }
    }

    public function modifica()
    {
        $id = $this->request->getPost('id');

        if (! ctype_digit((string) $id) || ! $this->validate($this->reglas())) {
            return redirect()->to('lista_estatus')->with('mensaje', 'No se permiten campos vacíos');
        }

        $id_pedido    = (int) $this->request->getPost('id_pedido');
        $nuevo_estado = $this->post('edo');
        $fecha        = $this->fechaHoraSql($this->post('fecha'));

        if ($fecha === null) {
            return redirect()->to('pasa_id_estatus/' . (int) $id)->with('mensaje', 'No se permiten campos vacíos');
        }

        $m_estatus = new Modelo_estatus();

        $registro_anterior = $m_estatus->anterior($id_pedido, $id);
        if ($registro_anterior) {
            $estado_ant = $registro_anterior['estado'];

            if (! in_array($nuevo_estado, self::FLUJO[$estado_ant] ?? [], true)) {
                return redirect()->back()->with('mensaje', "Error: Esta modificación rompe la secuencia lógica desde '" . $this->texto($estado_ant) . "'.");
            }
        }

        $registro_posterior = $m_estatus->posterior($id_pedido, $id);
        if ($registro_posterior) {
            $estado_post = $registro_posterior['estado'];

            if (! in_array($estado_post, self::FLUJO[$nuevo_estado] ?? [], true)) {
                return redirect()->back()->with('mensaje', "Error: El nuevo estado no es compatible con el siguiente paso ya registrado ('" . $this->texto($estado_post) . "').");
            }
        }

        try {
            $m_estatus->update($id, ['estado' => $nuevo_estado, 'fecha' => $fecha, 'id_pedido' => $id_pedido]);

            return redirect()->to('lista_estatus')->with('mensaje', 'Registro modificado exitosamente.');
        } catch (Throwable $e) {
            return redirect()->to('lista_estatus')->with('mensaje', PdoModelo::mensajeSeguro($e, 'No se pudo modificar el estatus.'));
        }
    }
}
