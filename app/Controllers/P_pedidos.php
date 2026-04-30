<?php 
namespace App\Controllers;

use App\Models\Modelo_pedido;
use App\Models\Modelo_producto;
use App\Models\Modelo_productopedidos;
use CodeIgniter\Controller;

class P_pedidos extends Controller{
public function crea_p_pedido(){
    $m_pedido = new Modelo_pedido();
    $m_producto = new Modelo_producto();
    $datos = [
        'pedidos' => $m_pedido->findAll(),
        'productos' => $m_producto->findAll()
    ];
    return view('crea_p_pedido', $datos);
}
public function guarda_p_pedido(){
    $m_p_pedido = new Modelo_productopedidos();
    $items  = json_decode($this->request->getPost('items'), true);
    $origen = $this->request->getPost('origen');

    if (empty($items) || !is_array($items)) {
        return redirect()->to('crea_p_pedido')->with('mensaje', 'El carrito está vacío');
    }

    foreach ($items as $item) {
        $datos = [
            'cantidad'         => $item['cant'],
            'precio_venta' => $item['p_venta'],
            'unidad_venta' => $item['u_venta'],
            'total'        => $item['total'],
            'id_pedido'    => $item['id_pedido'],
            'id_producto'  => $item['id_producto'],
        ];
        
        if (
            empty($datos['cantidad'])         ||
            empty($datos['precio_venta']) ||
            empty($datos['unidad_venta']) ||
            empty($datos['id_pedido'])    ||
            empty($datos['id_producto'])
        ) {
            return redirect()->to('crea_p_pedido')->with('mensaje', 'Todos los campos son obligatorios');
        }
        var_dump($datos);die();
        $m_p_pedido->insert($datos);
    }

    if ($origen === 'main_page') {
        return redirect()->to('/')->with('mensaje', 'Registro guardado');
    }
    return redirect()->to('lista_p_pedido')->with('mensaje', 'Registro guardado');
}

public function lista_p_pedido(){
    $m_p_pedido = new Modelo_productopedidos();
    $m_producto = new Modelo_producto();
    $m_pedido   = new Modelo_pedido();      

    $datos = [
        'productos' => $m_producto->findAll(),
        'p_pedidos' => $m_p_pedido->findAll(),
        'pedidos'   => $m_pedido->findAll() 
    ];
    return view('lista_p_pedido', $datos);
}
public function recupera($id=null){
    $m_p_pedido = new Modelo_productopedidos();
    $m_pedido = new Modelo_pedido();
    $m_producto = new Modelo_producto();
    $datos = [
        'p_pedidos' => $m_p_pedido->find($id),
        'pedidos' => $m_pedido->findAll(),
        'productos' => $m_producto->findAll()
    ];
    return view('modifica_p_pedido', $datos);
}

public function eliminar_datos($id=null){
    $m_p_pedido = new Modelo_productopedidos();
    $m_p_pedido->delete($id);
    return redirect()->to('lista_p_pedido')->with('mensaje', 'Registro eliminado');
}
public function modifica(){
    $m_p_pedido = new Modelo_productopedidos();
    $datos = [
        'cantidad' => $this->request->getPost('cant'),
        'precio_venta' => $this->request->getPost('p_venta'),
        'unidad_venta' => $this->request->getPost('u_venta'),
        'total' => $this->request->getPost('tot'),
        'id_pedido' => $this->request->getPost('id_pedido'),
        'id_producto' => $this->request->getPost('id_producto')
    ];
    $id = $this->request->getPost('id');
    if (
        empty($datos['cantidad']) || 
        empty($datos['precio_venta']) ||
        empty($datos['unidad_venta']) ||
        empty($datos['id_pedido']) ||
        empty($datos['id_producto'])
    ){
        return redirect()->to('pasa_id_p_pedido/'.$id)->with('mensaje', 'Todos los campos son obligatorios');
    } else {
        $m_p_pedido->update($id, $datos);
        return redirect()->to('lista_p_pedido')->with('mensaje', 'Registro modificado');
    }
}
}