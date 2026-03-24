<?php 
namespace App\Controllers;

use App\Models\Modelo_cliente;
use CodeIgniter\Controller;

class Clientes extends Controller{
public function crea_cliente(){
    return view('crea_cliente');
}
public function guarda_cliente(){
    $m_cliente = new Modelo_cliente();
    $datos=[
        'nombre'=>$this->request->getPost('nom'),
        'ape_pat'=>$this->request->getPost('ape_pat'),
        'ape_mat'=>$this->request->getPost('ape_mat'),
        'telefono'=>$this->request->getPost('tel'),
    ];
    if(
        empty($datos['nombre'])||
        empty($datos['ape_pat'])||
        empty($datos['ape_mat'])||
        empty($datos['telefono'])
    )return view('crea_cliente');
    else {
        $m_cliente->insert($datos);
        return redirect()-> to('lista_cliente');
    } 
}
public function lista_cliente(){
    $m_cliente = new Modelo_cliente();
    $datos['clientes']= $m_cliente->findAll();
    return view('lista_cliente', $datos);
}
public function modifica(){
        $id=$this->request->getPost('id');
         $datos=[
            'nombre'=>$this->request->getPost('nom'),
            'ape_pat'=>$this->request->getPost('ape_pat'),
            'ape_mat'=>$this->request->getPost('ape_mat'),
            'telefono'=>$this->request->getPost('tel'),
         ];
         $m_cliente= new Modelo_cliente();
         if($m_cliente->update($id,$datos)){
            //echo "Datos almacenados exitosamente";
            return view('lista_cliente');
         }
            
    }
public function recupera($id = null){
    $m_cliente = new Modelo_cliente();
    $datos['clientes'] = $m_cliente->find($id);
    if(!$datos['clientes']){
        return redirect()->to('/lista_cliente');
    }
    return view('modifica_cliente', $datos);
}
public function eliminar_datos($id = null){
    $m_cliente = new Modelo_cliente();
    if(!$m_cliente->find($id)){
        return redirect()->to('/lista_cliente');
    }
    $m_cliente->delete($id);
    return redirect()->to('/lista_cliente');
}
}