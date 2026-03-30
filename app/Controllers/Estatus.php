<?php 
namespace App\Controllers;

use App\Models\Modelo_estatus;
use CodeIgniter\Controller;

class Estatus extends Controller{

public function crea_estatus(){
    return view('crea_estatus');
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
            return redirect()->to('lista_estatus');
        }
}

}