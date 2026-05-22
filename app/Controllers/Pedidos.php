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
            'repartidores'=>$m_repartidor->findAll(),
        ];
        return view ('crea_pedido',$datos_recuperados);
    }else{
        $m_pedido->insert($datos);
        if ($this->request->getPost('origen') == 'main_page') {
            return redirect()->to('/')->with('mensaje', 'Pedido creado exitosamente');
        }
        return redirect()->to('lista_pedido')->with('mensaje', 'Pedido creado exitosamente');

    }
}
public function lista_pedido()
{
    $buscar = $this->request->getGet('buscar') ?? '';

    $m_pedido     = new Modelo_pedido();
    $m_cliente    = new Modelo_cliente();
    $m_repartidor = new Modelo_repartidor();

    $clientes     = array_column($m_cliente->findAll(), null, 'id');
    $repartidores = array_column($m_repartidor->findAll(), null, 'id');

    $datos = [
        'pedidos'      => $m_pedido->buscarPedidos($buscar),
        'clientes'     => $clientes,
        'repartidores' => $repartidores,
        'buscar'       => $buscar,
    ];

    return view('lista_pedido', $datos);
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
public function eliminar_datos($id = null)
{
    $m_pedido = new Modelo_pedido();

    try {
        $m_pedido->delete($id);
        return redirect()->to('lista_pedido')
                        ->with('mensaje', 'Pedido eliminado correctamente.');

    } catch (\Exception $e) {
        $codigo = $e->getPrevious() ? $e->getPrevious()->getCode() : $e->getCode();

        if ($codigo == 1451) {
            return redirect()->to('lista_pedido')
                            ->with('error', 'No se puede eliminar este pedido porque tiene registros relacionados. Elimina primero su estatus o productos asociados.');
        }

        return redirect()->to('lista_pedido')
        ->with('error', 'Error inesperado al eliminar.');
    }
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
    return redirect()->to('lista_pedido')->with('mensaje', 'Pedido actualizado exitosamente');
}
}