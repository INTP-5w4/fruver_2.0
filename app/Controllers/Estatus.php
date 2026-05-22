<?php 
namespace App\Controllers;

use App\Models\Modelo_estatus;
use App\Models\Modelo_pedido;
use CodeIgniter\Controller;

class Estatus extends Controller{

public function crea_estatus(){
    $fecha = date('Y-m-d H:i:s');
    $m_pedido = new Modelo_pedido();
    $datos=[
        'pedidos'=>$m_pedido->findAll(),
        'timestamp'=>$fecha
    ];
    return view('crea_estatus', $datos);
}
public function guarda_estatus(){
    $m_estatus = new Modelo_estatus();
    
    $nuevo_estado = $this->request->getPost('edo'); // Tu select en la vista debe enviar estos valores
    $id_pedido = $this->request->getPost('id_pedido');
    $fecha_post = $this->request->getPost('fecha');

    // 1. Buscamos el último estatus de este pedido
    $ultimo_registro = $m_estatus->where('id_pedido', $id_pedido)
                                 ->orderBy('id', 'DESC')
                                 ->first();

    // 2. Definición de lógica de pasos (Mapa de Transiciones)
    // Aquí defines qué sigue después de qué para evitar saltos
    $flujo = [
        'pedido_pendiente'   => ['pedido_realizado', 'pedido_cancelado'],
        'pedido_realizado'   => ['pedido_confirmado', 'pedido_cancelado'],
        'pedido_confirmado'  => ['pedido_en_transito', 'pedido_cancelado'],
        'pedido_en_transito' => ['pedido_entregado', 'pedido_cancelado'],
        'pedido_entregado'   => ['pedido_credito', 'pedido_pagado'],
        'pedido_credito'     => ['pedido_pagado'],
        'pedido_pagado'      => [], // Estado final
        'pedido_cancelado'   => []  // Estado final
    ];

    if ($ultimo_registro) {
        $estado_actual = $ultimo_registro['estado'];

        // Validación: ¿El nuevo estado es un salto prohibido?
        if (!isset($flujo[$estado_actual]) || !in_array($nuevo_estado, $flujo[$estado_actual])) {
            return redirect()->back()->with('mensaje', "Error: No puedes pasar de '" . str_replace('_', ' ', $estado_actual) . "' a '" . str_replace('_', ' ', $nuevo_estado) . "'.");
        }
    }

    // 3. Preparación de datos para insertar
    $datos = [
        'estado'    => $nuevo_estado,
        'fecha'     => $fecha_post,
        'id_pedido' => $id_pedido,
    ];

    if (empty($nuevo_estado) || empty($fecha_post) || empty($id_pedido)) {
        return redirect()->to('crea_estatus')->with('mensaje', 'Todos los campos son obligatorios.');
    } else {
        $m_estatus->insert($datos);
        
        $ruta = ($this->request->getPost('origen') == 'main_page') ? '/' : 'lista_estatus';
        return redirect()->to($ruta)->with('mensaje', 'Estatus actualizado correctamente.');
    }
}
public function lista_estatus(){
    $m_estatus = new Modelo_estatus();
    $m_pedido = new Modelo_pedido();
    $datos['estatus']=$m_estatus->findAll();
    $datos['pedidos']=$m_pedido->findAll();
    return view('lista_estatus', $datos);
}

public function recupera($id=null){
    $m_estatus = new Modelo_estatus();
    $m_pedido = new Modelo_pedido();
    $datos=[
        'estatus'=>$m_estatus->find($id),
        'pedidos'=>$m_pedido->findAll(),
    ];
    return view('modifica_estatus', $datos);
}
public function eliminar_datos($id = null){
    $m_estatus = new Modelo_estatus();
    if(!$m_estatus->find($id)){
        return redirect()->to('lista_estatus');
    }
    $m_estatus->delete($id);
    return redirect()->to('lista_estatus')->with('mensaje', 'Estatus eliminado correctamente');
}

public function modifica() {
    $m_estatus = new Modelo_estatus();
    $id = $this->request->getPost('id');
    $id_pedido = $this->request->getPost('id_pedido');
    $nuevo_estado = $this->request->getPost('edo');
    $fecha = $this->request->getPost('fecha');

    // 1. Definición del flujo permitido (basado en tu ENUM) [cite: 46]
    $flujo = [
        'pedido_pendiente'   => ['pedido_realizado', 'pedido_cancelado'],
        'pedido_realizado'   => ['pedido_confirmado', 'pedido_cancelado'],
        'pedido_confirmado'  => ['pedido_en_transito', 'pedido_cancelado'],
        'pedido_en_transito' => ['pedido_entregado', 'pedido_cancelado'],
        'pedido_entregado'   => ['pedido_credito', 'pedido_pagado'],
        'pedido_credito'     => ['pedido_pagado'],
        'pedido_pagado'      => [],
        'pedido_cancelado'   => []
    ];

    // 2. Validar contra el registro anterior
    // Buscamos el registro con ID menor al actual para el mismo pedido
    $registro_anterior = $m_estatus->where('id_pedido', $id_pedido)
                                   ->where('id <', $id)
                                   ->orderBy('id', 'DESC')
                                   ->first();

    if ($registro_anterior) {
        $estado_ant = $registro_anterior['estado'];
        if (!in_array($nuevo_estado, $flujo[$estado_ant])) {
            return redirect()->back()->with('mensaje', "Error: Esta modificación rompe la secuencia lógica desde '" . str_replace('_', ' ', $estado_ant) . "'.");
        }
    }

    // 3. Validar contra el registro posterior (por si ya existen más estados después de este)
    $registro_posterior = $m_estatus->where('id_pedido', $id_pedido)
                                    ->where('id >', $id)
                                    ->orderBy('id', 'ASC')
                                    ->first();

    if ($registro_posterior) {
        $estado_post = $registro_posterior['estado'];
        // El nuevo estado que pongas debe ser capaz de llevar al estado que ya estaba después
        if (!in_array($estado_post, $flujo[$nuevo_estado])) {
            return redirect()->back()->with('mensaje', "Error: El nuevo estado no es compatible con el siguiente paso ya registrado ('" . str_replace('_', ' ', $estado_post) . "').");
        }
    }

    // 4. Ejecución de la actualización
    if (empty($nuevo_estado) || empty($fecha) || empty($id_pedido)) {
        return redirect()->to('pasa_id_estatus/' . $id)->with('mensaje', 'No se permiten campos vacíos');
    } else {
        $m_estatus->update($id, [
            'estado'    => $nuevo_estado,
            'fecha'     => $fecha,
            'id_pedido' => $id_pedido
        ]);
        return redirect()->to('lista_estatus')->with('mensaje', 'Registro modificado exitosamente.');
    }
}
}