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
    $datos=[
        'estado'=>$this->request->getPost('edo'),
        'fecha'=>$this->request->getPost('fecha'),
        'id_pedido'=>$this->request->getPost('id_pedido'),
    ];
    if (
        empty($datos['estado'])||
        empty($datos['fecha'])||
        empty($datos['id_pedido'])
        ){
            return redirect()->to('crea_estatus');
        } else {
            $m_estatus->insert($datos);
            if ($this->request->getPost('origen') == 'main_page') {
                return redirect()->to('/')->with('mensaje', 'Estatus registrado exitosamente.');
            }
            return redirect()->to('lista_estatus');
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
    return redirect()->to('lista_estatus');
}

public function modifica(){
    $m_estatus = new Modelo_estatus();
    $id = $this->request->getPost('id');
    $datos=[
        'id'=>$id,
        'estado'=>$this->request->getPost('edo'),
        'fecha'=>$this->request->getPost('fecha'),
        'id_pedido'=>$this->request->getPost('id_pedido'),
    ];
    if (
        empty($datos['estado'])||
        empty($datos['fecha'])||
        empty($datos['id_pedido'])
        ){
            return redirect()->to('pasa_id_estatus/'.$id)->with('mensaje', 'No se permiten campos vacíos');
        } else {
            $m_estatus->update($id, $datos);
            return redirect()->to('lista_estatus');
        }
    
}
}