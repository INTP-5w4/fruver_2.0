<?php 
namespace App\Controllers;

use App\Models\Modelo_entrada;
use App\Models\Modelo_existencia;
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
        'fecha'           => $this->request->getPost('f_ent'),
        'fecha_cad'       => $this->request->getPost('f_cad'),
        'cantidad'        => $this->request->getPost('cant'),
        'u_compra'        => $this->request->getPost('u_com'),
        'u_venta'         => $this->request->getPost('u_ven'),
        'precio_compra_u' => $this->request->getPost('p_compra'),
        'precio_venta_u'  => $this->request->getPost('p_venta'),
        'equivalente'     => $this->request->getPost('equi'),
        'conversion'     => $this->request->getPost('conv'),
        'id_producto'     => $this->request->getPost('id_producto'),
    ];

    if (
        empty($datos['fecha'])       ||
        empty($datos['cantidad'])    ||
        empty($datos['u_compra'])    ||
        empty($datos['u_venta'])     ||
        empty($datos['id_producto'])
    ){
        $m_producto = new Modelo_producto();
        $datos=['productos' => $m_producto->findAll()];
        return view('crea_entrada', $datos);

    } else {
        $m_entrada->insert($datos);
        if($this->request->getPost('origen') === 'main_page'){
            return redirect()->to('/')->with('mensaje', 'Entrada registrada correctamente');
        }
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
public function modifica_entrada(){
    $m_entrada = new Modelo_entrada();
    $id = $this->request->getPost('id');

    $datos = [
        'fecha'           => $this->request->getPost('f_ent'),
        'fecha_cad'       => $this->request->getPost('fecha_cad'),
        'cantidad'        => $this->request->getPost('cant'),
        'u_compra'        => $this->request->getPost('u_com'),
        'u_venta'         => $this->request->getPost('u_ven'),
        'equivalente'     => $this->request->getPost('equi'),
        'conversion'      => $this->request->getPost('conv'),
        'precio_compra_u' => $this->request->getPost('p_compra'),
        'precio_venta_u'  => $this->request->getPost('p_venta'),
        'id_producto'     => $this->request->getPost('id_producto'),
    ];

    if (
        empty($datos['fecha'])          ||
        empty($datos['cantidad'])       ||
        empty($datos['u_compra'])       ||
        empty($datos['u_venta'])        ||
        empty($datos['equivalente'])    ||
        empty($datos['precio_compra_u'])||
        empty($datos['id_producto'])
    ){
        // ✅ Redirige en lugar de cargar la vista directamente
        return redirect()->to('lista_entrada')->with('error', 'Por favor, complete todos los campos obligatorios.');

    } else {
        $m_entrada->update($id, $datos);
        return redirect()->to('lista_entrada')->with('mensaje', 'Entrada actualizada correctamente');
    }
}
}