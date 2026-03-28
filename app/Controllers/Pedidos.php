<?php 
namespace App\Controllers;

use App\Models\Modelo_cliente;
use App\Models\Modelo_pedido;
use App\Models\Modelo_repartidor;
use CodeIgniter\Controller;

class Pedidos extends Controller{

public function crea_pedido(){
$m_cliente = new Modelo_cliente();
    $m_repartidor = new Modelo_repartidor();
    $datos=[
        'clientes'=>$m_cliente->findAll(),
        'repartidores'=>$m_repartidor->findAll(),
    ];
    return view('crea_pedido',$datos);
}
public function guarda_pedido(){
    $m_pedido = new Modelo_pedido();
    $datos=[
        'fecha'=>$this->request->getPost('fecha'),
        'id_cliente'=>$this->request->getPost('id_cliente'),
        'id_repartidor'=>$this->request->getPost('id_repartidor'),
    ];
    if (
        empty($datos['fecha'])||
        empty($datos['id_cliente'])||
        empty($datos['id_repartidor'])
        ){
        $m_cliente = new Modelo_cliente();
        $m_repartidor = new Modelo_repartidor();
        $datos_recuperados=[
            'clientes'=>$m_cliente->findAll(),
            'repartidores'=>$m_repartidor->findAll()
        ];
        return view ('crea_pedido',$datos_recuperados);
    }else{
        $m_pedido->insert($datos);
        return redirect()->to('lista_pedido');
    }
}
public function lista_pedido(){
    $m_pedido= new Modelo_pedido();
    $datos['pedidos']= $m_pedido->findAll();
    return view('lista_pedido',$datos);
}
public function recupera($id=null){
    $m_pedido = new Modelo_pedido();
    $m_cliente = new Modelo_cliente();
    $m_repartidor = new Modelo_repartidor();
    $datos=[
        'pedidos'=>$m_pedido->find($id),
        'clientes'=>$m_cliente->findAll(),
        'repartidores'=>$m_repartidor->findAll(), 
    ];
    return view('modifica_pedido',$datos);
}
public function eliminar_datos($id=null){
    if ($id === null || $this->request->getMethod() !== 'post') {
        return redirect()->to('lista_pedido');
    }
    $m_pedido = new Modelo_pedido();
    $m_pedido->delete($id);
    return redirect()->to('lista_pedido');
}

public function modifica(){
    $m_pedido = new Modelo_pedido();
    $id = $this->request->getPost('id');
    if (empty($id)) {
        return redirect()->to('lista_pedido')->with('error', 'ID inválido');
}
    $datos=[
        'fecha'=>$this->request->getPost('fecha'),
        'id_cliente'=>$this->request->getPost('id_cliente'),
        'id_repartidor'=>$this->request->getPost('id_repartidor'),

    ];
    $m_pedido->update($id, $datos);
    return redirect()->to('lista_pedido');
}
}