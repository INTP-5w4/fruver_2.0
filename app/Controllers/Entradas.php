<?php 
namespace App\Controllers;

use App\Models\Modelo_entrada;
use App\Models\Modelo_producto;
use CodeIgniter\Controller;

class Entradas extends Controller{
public function crea_entrada(){
    $m_producto = new Modelo_producto();
    $datos['frutas']= $m_producto->findAll();
    return view('crea_entrada',$datos);
}
public function guarda_entrada(){
    $m_entrada = new Modelo_entrada();
    $datos = [
        'fecha'         => $this->request->getPost('f_ent'),
        'fecha_cad'     => $this->request->getPost('f_cad'),    // ← corregido
        'cantidad'      => $this->request->getPost('cant'),
        'u_compra'      => $this->request->getPost('u_com'),
        'u_venta'       => $this->request->getPost('u_ven'),
        'precio_compra' => $this->request->getPost('p_compra'),
        'id_producto'   => $this->request->getPost('id_producto'),
    ];

    if (
        empty($datos['fecha'])        ||
        empty($datos['cantidad'])     ||
        empty($datos['u_compra'])     ||
        empty($datos['u_venta'])      ||
        empty($datos['precio_compra'])||
        empty($datos['id_producto'])
    ){
        $m_producto = new Modelo_producto();
        return view('crea_entrada', [
            'productos' => $m_producto->findAll()
        ]);
    } else {
        $m_entrada->insert($datos);
        return redirect()->to('lista_entrada');
    }
}
public function lista_entrada(){
    $m_entrada = new Modelo_entrada();
    $m_producto = new Modelo_producto();
    $producto= array_column($m_producto->findAll(), null, 'id');
    $datos=[
        'entradas'=>$m_entrada->findAll(),
        'productos'=>$producto,  
    ];
    return view('lista_entrada', $datos);
}

public function recupera($id=null){
    $m_entrada = new Modelo_entrada();
    $m_producto = new Modelo_producto();
    $datos=[
        'entradas'=>$m_entrada->find($id),
        'productos'=>$m_producto->findAll(),
    ];
    if (!$datos['entradas']){
        return redirect()->to('lista_entrada');
    }
    return view('modifica_entrada',$datos);
}
public function eliminar_datos($id=null){
    $m_entrada = new Modelo_entrada();
    if(!$m_entrada->find($id)){
        return redirect()->to('/lista_entrada');
    }
    $m_entrada->delete($id);
    return redirect()->to('/lista_entrada');
}
public function modifica(){
    $m_entrada = new Modelo_entrada();
    $id = $this->request->getPost('id');
    $datos = [
        'fecha'         => $this->request->getPost('f_ent'),
        'fecha_cad'     => $this->request->getPost('fecha_cad'), // ← agregado
        'cantidad'      => $this->request->getPost('cant'),
        'u_compra'      => $this->request->getPost('u_com'),
        'u_venta'       => $this->request->getPost('u_ven'),
        'equivalente'   => $this->request->getPost('equi'),
        'conversion'    => $this->request->getPost('conv'),     // ← faltaba
        'precio_compra' => $this->request->getPost('p_compra'),
        'id_producto'   => $this->request->getPost('id_producto'),
    ];

    if (
        empty($datos['fecha'])      ||
        empty($datos['fecha_cad'])  ||
        empty($datos['cantidad'])   ||
        empty($datos['u_compra'])   ||
        empty($datos['u_venta'])    ||
        empty($datos['equivalente'])||
        empty($datos['conversion']) ||
        empty($datos['precio_compra'])||
        empty($datos['id_producto'])
    ){
        $m_producto = new Modelo_producto();
        return view('modifica_entrada', [       // ← corregido
            'productos' => $m_producto->findAll(),
            'entradas'  => $m_entrada->find($id),
        ]);
    } else {
        $m_entrada->update($id, $datos);
        return redirect()->to('lista_entrada');
    }
}
}