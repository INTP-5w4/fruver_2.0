<?php 
namespace App\Controllers;

use App\Models\Modelo_repartidor;
use CodeIgniter\Controller;

class Repartidores extends Controller{
public function crea_repartidor(){
    return view('crea_repartidor');
}
public function guarda_repartidor(){
    $m_repartidor = new Modelo_repartidor();
    $datos=[
        'nombre'=>$this->request->getPost('nom'),
        'ape_pat'=>$this->request->getPost('ape_pat'),
        'ape_mat'=>$this->request->getPost('ape_mat'),
        'telefono'=>$this->request->getPost('tel'),
        'direccion'=>$this->request->getPost('dir'),
        'notas'=>$this->request->getPost('not'),
    ];
    if (
        empty($datos['nombre'])||
        empty($datos['ape_pat'])||
        empty($datos['ape_mat'])||
        empty($datos['telefono'])||
        empty($datos['direccion'])||
        empty($datos['notas'])
    )return view('crea_repartidor');
    else{
        $m_repartidor->insert($datos);
        return redirect()->to('lista_repartidor');
    }
}

public function modifica(){
    $id=$this->request->getPost('id');
    $datos=[
        'nombre'=>$this->request->getPost('nom'),
        'ape_pat'=>$this->request->getPost('ape_pat'),
        'ape_mat'=>$this->request->getPost('ape_mat'),
        'telefono'=>$this->request->getPost('tel'),
        'direccion'=>$this->request->getPost('dir'),
        'notas'=>$this->request->getPost('not'),
    ];
    $m_repartidor = new Modelo_repartidor();
    if ($m_repartidor->update($id,$datos)){
        return redirect()->to('lista_repartidor');
    }
}
public function lista_repartidor(){
    $m_repartidor = new Modelo_repartidor();
    $datos['repartidores']=$m_repartidor->findAll();
    return view('lista_repartidor',$datos);
}
public function recupera($id=null){
    $m_repartidor = new Modelo_repartidor();
    $datos['repartidores']=$m_repartidor->find($id);
    if(!$datos['repartidores']){
        return redirect()->to('lista_repartidor');
    }
    return view('modifica_repartidor', $datos);
}
public function eliminar_datos($id=null){
    $m_repartidor = new Modelo_repartidor();
    if (!$m_repartidor->find($id)){
       return redirect()->to('lista_repartidor'); 
    }
    $m_repartidor->delete($id);
    return redirect()->to('lista_repartidor');
}
}