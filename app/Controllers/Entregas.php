<?php

namespace App\Controllers;

use App\Models\Modelo_entrega;
use App\Models\Modelo_repartidor;
use App\Models\PdoModelo;
use DomainException;
use Throwable;

/**
 * Entregas de pedidos.
 *
 *  - Personal (admin): asigna repartidor a un pedido, ve y cancela entregas.
 *  - Repartidor: ve SOLO sus entregas y las avanza (salir, entregar, reportar falla).
 *
 * El repartidor se identifica siempre por auth()->id() (la sesión de Shield); nunca
 * por un id que llegue en el formulario o en la URL.
 */
class Entregas extends BaseController
{
    private const ESTADOS = ['asignada', 'en_ruta', 'entregada', 'fallida', 'cancelada'];

    // ─────────────────────────────────────────────────────────────
    // PERSONAL
    // ─────────────────────────────────────────────────────────────

    public function lista_entrega()
    {
        $buscar    = $this->textoBusqueda();
        $m_entrega = new Modelo_entrega();

        return view('lista_entrega', [
            'entregas'     => $m_entrega->listarTodas($buscar),
            'pager'        => $m_entrega->pager,
            'buscar'       => $buscar,
            'pedidos'      => $m_entrega->pedidosAsignables(),
            'repartidores' => (new Modelo_repartidor())->findAll(),
        ]);
    }

    public function asigna_entrega()
    {
        $reglas = [
            'id_pedido'     => 'required|is_natural_no_zero',
            'id_repartidor' => 'required|is_natural_no_zero',
        ];

        if (! $this->validate($reglas)) {
            return redirect()->to('lista_entrega')->with('error', 'Elige un pedido y un repartidor.');
        }

        try {
            (new Modelo_entrega())->asignar(
                (int) $this->request->getPost('id_pedido'),
                (int) $this->request->getPost('id_repartidor')
            );

            return redirect()->to('lista_entrega')->with('mensaje', 'Repartidor asignado correctamente.');
        } catch (DomainException $e) {
            return redirect()->to('lista_entrega')->with('error', $e->getMessage());
        } catch (Throwable $e) {
            return redirect()->to('lista_entrega')
                ->with('error', PdoModelo::mensajeSeguro($e, 'No se pudo asignar el repartidor.'));
        }
    }

    public function cancela_entrega($id = null)
    {
        if (! ctype_digit((string) $id)) {
            return redirect()->to('lista_entrega');
        }

        try {
            $ok = (new Modelo_entrega())->cancelar((int) $id, $this->post('motivo'));

            return redirect()->to('lista_entrega')->with(
                $ok ? 'mensaje' : 'error',
                $ok ? 'Entrega cancelada.' : 'Solo se pueden cancelar entregas asignadas o en ruta.'
            );
        } catch (Throwable $e) {
            return redirect()->to('lista_entrega')
                ->with('error', PdoModelo::mensajeSeguro($e, 'No se pudo cancelar la entrega.'));
        }
    }

    // ─────────────────────────────────────────────────────────────
    // REPARTIDOR
    // ─────────────────────────────────────────────────────────────

    public function mis_entregas()
    {
        $estado = (string) $this->request->getGet('estado');
        $estado = in_array($estado, self::ESTADOS, true) ? $estado : null;

        $m_entrega = new Modelo_entrega();

        return view('mis_entregas', [
            'entregas' => $m_entrega->listarDeRepartidor((int) auth()->id(), $estado),
            'pager'    => $m_entrega->pager,
            'estado'   => $estado,
        ]);
    }

    public function salir($id = null)
    {
        return $this->accionRepartidor($id, function (Modelo_entrega $m, int $idEntrega): array {
            return $m->iniciarRuta($idEntrega, (int) auth()->id())
                ? ['mensaje', 'Entrega en ruta.']
                : ['error', 'Esta entrega no está disponible para salir.'];
        });
    }

    public function entregar($id = null)
    {
        $reglas = [
            'monto'  => 'permit_empty|decimal|greater_than_equal_to[0]|less_than[100000000]',
            'metodo' => 'permit_empty|in_list[efectivo,transferencia,tarjeta]',
            'notas'  => 'permit_empty|max_length[500]',
        ];

        if (! $this->validate($reglas)) {
            return redirect()->to('repartidor/entregas')->with('error', $this->primerError());
        }

        $monto = $this->post('monto');

        if ($monto !== '' && $this->post('metodo') === '') {
            return redirect()->to('repartidor/entregas')->with('error', 'Indica cómo se cobró.');
        }

        return $this->accionRepartidor($id, function (Modelo_entrega $m, int $idEntrega) use ($monto): array {
            $ok = $m->marcarEntregada(
                $idEntrega,
                (int) auth()->id(),
                $monto === '' ? null : (float) $monto,
                $monto === '' ? null : $this->post('metodo'),
                null,
                $this->post('notas')
            );

            return $ok
                ? ['mensaje', 'Entrega registrada como entregada.']
                : ['error', 'Esta entrega no se puede marcar como entregada.'];
        });
    }

    public function fallar($id = null)
    {
        if (! $this->validate(['incidencia' => 'required|max_length[500]'])) {
            return redirect()->to('repartidor/entregas')
                ->with('error', 'Describe qué pasó (máximo 500 caracteres).');
        }

        return $this->accionRepartidor($id, function (Modelo_entrega $m, int $idEntrega): array {
            return $m->marcarFallida($idEntrega, (int) auth()->id(), $this->post('incidencia'))
                ? ['mensaje', 'Incidencia registrada. El administrador podrá reasignar la entrega.']
                : ['error', 'Esta entrega no se puede reportar como fallida.'];
        });
    }

    /**
     * Valida el id de la URL, ejecuta la acción y convierte el resultado (o la
     * excepción) en un mensaje flash. Mantiene los tres métodos de arriba cortos.
     *
     * @param callable(Modelo_entrega,int):array{0:string,1:string} $accion
     */
    private function accionRepartidor($id, callable $accion)
    {
        if (! ctype_digit((string) $id)) {
            return redirect()->to('repartidor/entregas');
        }

        try {
            [$clave, $texto] = $accion(new Modelo_entrega(), (int) $id);

            return redirect()->to('repartidor/entregas')->with($clave, $texto);
        } catch (DomainException $e) {
            return redirect()->to('repartidor/entregas')->with('error', $e->getMessage());
        } catch (Throwable $e) {
            return redirect()->to('repartidor/entregas')
                ->with('error', PdoModelo::mensajeSeguro($e, 'No se pudo completar la acción.'));
        }
    }
}
