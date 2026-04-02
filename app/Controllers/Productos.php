<?php

namespace App\Controllers;

use App\Models\Modelo_producto;

class Productos extends BaseController
{
public function main_page(){
    $db = \Config\Database::connect();

    $productos = $db->query("
        SELECT p.nombre, SUM(e.cantidad) AS total
        FROM producto p
        LEFT JOIN entrada e ON e.id_producto = p.id
        GROUP BY p.id, p.nombre
        ORDER BY total ASC
        LIMIT 5
    ")->getResultArray();

    $m_cliente = new \App\Models\Modelo_cliente();
    $m_producto = new Modelo_producto();
    $m_repartidor = new \App\Models\Modelo_repartidor();
    $m_pedido = new \App\Models\Modelo_pedido();
    $m_entrada = new \App\Models\Modelo_entrada();

return view('main_page', [
    'productosLowStock' => $productos,
    'clientes' => $m_cliente->findAll(),
    'productos' => $m_producto->findAll(),
    'repartidores' => $m_repartidor->findAll(),
    'pedidos' => $m_pedido->findAll(),
    'entradas' => $m_entrada->findAll(),
]);
}

public function crea_producto(){
    return view('crea_producto');
}
public function guarda_producto(){
    $m_producto= new Modelo_producto();
    if ($this->request->getMethod()==='POST'){
        $file = $this->request->getFile('img');
        $reglas_validacion=[
            'img'=>[
                'label'=>'Archivo de Imagen',
                'rules'=>'uploaded[img]'
                .'|is_image[img]'
                .'|mime_in[img,image/jpg,image/png,image/jpeg]'
                .'|max_size[img,1024]',
            ]
        ];
        if(!$this->validate($reglas_validacion)){
            return redirect()->back()->with('errors',$this->validator->getErrors());
        }
        $nombre_img=$file->getRandomName();;
        $file->move(ROOTPATH.'public/uploads',$nombre_img);
    }
    $datos=[
        'nombre'=>$this->request->getPost('nom'),
        'descripcion'=>$this->request->getPost('desc'),
        'categoria'=>$this->request->getPost('cat'),
        'img'=>$nombre_img,
    ];
    if(
        empty($datos['nombre'])||
        empty($datos['descripcion'])||
        empty($datos['categoria'])||
        empty($datos['img'])
    ){
        return view('crea_producto');
    }else{
    $m_producto->insert($datos);
    return redirect()->to('lista_producto');
    }
}
public function lista_producto($dato=null){
    $m_producto = new Modelo_producto();
    if (!empty($dato)){
        $datos=[
            'productos'=>$m_producto->busqueda_compleja($dato)-> paginate(2,'default'),
            'pager'=>$m_producto->pager
        ];
    }else{
        
        $datos=[
            'productos'=>$m_producto->orderBy('nombre','ASC')-> paginate(5,'default'),
            'pager'=>$m_producto->pager
        ];
        //$datos['productos']=$m_producto->findAll();
        return view('lista_producto', $datos);
    }
}

public function modifica()
{
    $m_producto = new Modelo_producto();

    if ($this->request->getMethod() === 'POST') {

        $id = $this->request->getPost('id');
        $producto = $m_producto->find($id); // Obtener datos actuales

        // Imagen por defecto: mantener la actual
        $nombre_img = $producto['img'];

        // Revisar si subieron un nuevo archivo
        $file = $this->request->getFile('img');
        if ($file && $file->isValid() && !$file->hasMoved()) {

            // Validar imagen subida
            $reglas_validacion = [
                'img' => [
                    'label' => 'Archivo de Imagen',
                    'rules' => 'is_image[img]|mime_in[img,image/jpg,image/png,image/jpeg]|max_size[img,1024]',
                ]
            ];

            if (!$this->validate($reglas_validacion)) {
                return redirect()->back()->with('errors', $this->validator->getErrors());
            }

            // Eliminar imagen anterior si existe
            if (!empty($producto['img']) && file_exists(ROOTPATH.'public/uploads/'.$producto['img'])) {
                unlink(ROOTPATH.'public/uploads/'.$producto['img']);
            }

            // Guardar nueva imagen
            $nombre_img = $file->getRandomName();
            $file->move(ROOTPATH.'public/uploads', $nombre_img);
        }

        // Datos a actualizar
        $datos_de_producto = [
            'nombre' => $this->request->getPost('nom'),
            'descripcion' => $this->request->getPost('desc'),
            'categoria' => $this->request->getPost('cat'),
            'img' => $nombre_img,
        ];
        // Actualizar producto
        if ($m_producto->update($id, $datos_de_producto)) {
            return redirect()->to('lista_producto')->with('mensaje', 'Producto actualizado correctamente');
        } else {
            return redirect()->back()->with('error', 'No se pudo actualizar el producto');
        }
    }

    // Si no es POST, redirigir
    return redirect()->to('lista_producto');
}
public function recupera($id = null){
    $m_producto = new Modelo_producto();
    $datos['productos'] = $m_producto->find($id);
    if(!$datos['productos']){
        return redirect()->to('/lista_producto');
    }
    return view('modifica_producto', $datos);
}

public function eliminar_datos($id = null){
    $m_producto = new Modelo_producto();
    if(!$m_producto->find($id)){
        return redirect()->to('/lista_producto');
    }
    $m_producto->delete($id);
    return redirect()->to('/lista_producto');
}
}
