<?php 
namespace App\Controllers;

use App\Models\Modelo_cliente;
use App\Models\Modelo_direccion;
use CodeIgniter\Controller;

class Direcciones extends Controller{
public function crea_direccion(){
    $m_cliente = new Modelo_cliente();
    $datos_clientes['clientes']= $m_cliente->findAll();
    return view('crea_direccion',$datos_clientes);
}
public function guarda_direccion(){
    $m_direccion = new Modelo_direccion();
    $datos=[
        'colonia'=>$this->request->getPost('col'),
        'calle'=>$this->request->getPost('calle'),
        'numero'=>$this->request->getPost('num'),
        'municipio'=>$this->request->getPost('mun'),
        'estado'=>$this->request->getPost('edo'),
        'id_cliente'=>$this->request->getPost('id_cliente'),
        ];
        if (
        empty($datos['colonia'])||
        empty($datos['calle'])||
        empty($datos['numero'])||
        empty($datos['municipio'])||
        empty($datos['estado'])||
        empty($datos['id_cliente'])
        ){
            $m_cliente = new Modelo_cliente();
            $datos_clientes['clientes']= $m_cliente->findAll();
            return view('crea_direccion',$datos_clientes);
        } else {
            $m_direccion->insert($datos);
            return redirect()->to('lista_direccion');
        }
}

public function lista_direccion(){
    $m_direccion = new Modelo_direccion();
    $m_cliente = new Modelo_cliente();
    $clientes = array_column($m_cliente->findAll(), null, 'id');
    $datos=[
        'direcciones'=>$m_direccion->findAll(),
        'clientes'=>$clientes,
    ];
    return view('lista_direccion',$datos);
}

public function recupera($id=null){
    $m_direccion = new Modelo_direccion();
    $m_cliente = new Modelo_cliente();
    $datos=[
        'direcciones'=>$m_direccion->find($id),
        'clientes'=>$m_cliente->findAll(),
    ];
    if (!$datos['direcciones']){
        $datos_cliente['clientes']=$m_cliente->findAll();
        return view('lista_direccion',$datos_cliente);
    } else {
        return view('modifica_direccion',$datos);
    }
}
public function eliminar_datos($id=null){
    $m_direccion = new Modelo_direccion();
    if (!$m_direccion->find($id)){
        return redirect()->to('lista_direccion');
    } else{
        $m_direccion->delete($id);
        return redirect()->to('lista_direccion');
    }
}
public function modifica(){
    $id = $this->request->getPost('id');
    $datos=[
        'colonia'=>$this->request->getPost('col'),
        'calle'=>$this->request->getPost('calle'),
        'numero'=>$this->request->getPost('num'),
        'municipio'=>$this->request->getPost('mun'),
        'estado'=>$this->request->getPost('edo'),
        'id_cliente'=>$this->request->getPost('id_cliente'),
        ];
        $m_direccion = new Modelo_direccion();
        if ($m_direccion->update($id,$datos)){
            return redirect()->to('lista_direccion');
        }
}
}