<?php 
namespace App\Controllers;

use App\Models\Modelo_existencia;
use App\Models\Modelo_producto;
use CodeIgniter\Controller;

class Existencias extends Controller{
public function crea_existencia(){
    $m_producto = new Modelo_producto();
    $datos=[
        'productos'=>$m_producto->findAll(),
        'timestamp'=>date('Y-m-d H:i:s')
    ];
    return view('crea_existencia',$datos);
}
public function guarda_existencia(){
    $m_existencia = new Modelo_existencia();
    $datos=[
        'e_total'=>$this->request->getPost('e_total'),
        'e_bloqueado'=>$this->request->getPost('e_bloqueado'),
        'e_venta'=>$this->request->getPost('e_venta'),
        'fecha'=>$this->request->getPost('fecha'),
        'id_producto'=>$this->request->getPost('id_producto')
    ];
    if (
        empty($datos['e_total'])||
        empty($datos['e_bloqueado'])||
        empty($datos['e_venta'])||
        empty($datos['fecha'])||
        empty($datos['id_producto'])
    ){
        $m_producto = new Modelo_producto();
        $datos['productos']=$m_producto->findAll();
        return view('crea_existencia',$datos); 
    }
    $m_existencia->insert($datos);
    if ($this->request->getPost('origen') === 'main_page') {
        return redirect()->to('main_page3')->with('mensaje', 'Existencia registrada correctamente');
    }
    return redirect()->to('lista_existencia');
}

public function lista_existencia(){
    $m_existencia = new Modelo_existencia();
    $m_producto = new Modelo_producto();
    $datos=[
        'existencias' => $m_existencia->findAll(),
        'productos'   => array_column($m_producto->findAll(), null, 'id') // ← fix
    ];
    return view('lista_existencia',$datos);
}

public function eliminar_datos($id=null){
    $m_existencia = new Modelo_existencia();
    $m_existencia->delete($id);
    return redirect()->to('lista_existencia');
}
public function recupera($id=null){
    $m_existencia = new Modelo_existencia();
    $m_producto = new Modelo_producto();
    $datos=[
        'existencias' => $m_existencia->find($id),
        'productos'   => array_column($m_producto->findAll(), null, 'id') // ← fix
    ];
    return view('modifica_existencia',$datos);
}
public function modifica(){
    $m_existencia = new Modelo_existencia();
    $id=$this->request->getPost('id');
    $datos=[
        'id'=>$id,
        'e_total'=>$this->request->getPost('e_total'),
        'e_bloqueado'=>$this->request->getPost('e_bloqueado'),
        'e_venta'=>$this->request->getPost('e_venta'),
        'fecha'=>$this->request->getPost('fecha'),
        'id_producto'=>$this->request->getPost('id_producto')
    ];
    if (
        empty($datos['e_total'])||
        empty($datos['e_bloqueado'])||
        empty($datos['e_venta'])||
        empty($datos['fecha'])||
        empty($datos['id_producto'])
    ){
        $m_producto = new Modelo_producto();
        $datos['productos']=$m_producto->findAll();
        return view('modifica_existencia',$datos); 
    }
    $m_existencia->update($id,$datos);
    return redirect()->to('lista_existencia');

}
}