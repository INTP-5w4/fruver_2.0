<?php 
namespace App\Controllers;

use App\Models\Modelo_repartidor;
use CodeIgniter\Controller;

class Repartidores extends Controller{
    private $configuracion= [
        'reglas' => [
            'nom'     => 'required',
            'ape_pat' => 'required',
            'ape_mat' => 'required',
            'tel'     => 'required|numeric|max_length[12]',
            'dir'=> 'required'
        ],
        'errores' => [
            'nom' => [
                'required' => 'El campo Nombre es obligatorio.'
            ],
            'ape_pat' => [
                'required' => 'El Apellido Paterno es obligatorio.'
            ],
            'ape_mat' => [
                'required' => 'El Apellido Materno es obligatorio.'
            ],
            'tel' => [
                'required'    => 'El Teléfono es obligatorio.',
                'numeric'     => 'El Teléfono debe contener solo números.',
                'max_length'  => 'El Teléfono debe tener máximo 12 dígitos.' // Nota: sin los corchetes
            ],
            'dir' => [
                'required' => 'El campo Nombre es obligatorio.'
            ],
        ]
    ];
public function crea_repartidor(){
    return view('crea_repartidor');
}
public function guarda_repartidor(){
    $m_repartidor = new Modelo_repartidor();
    
    if (!$this->validate($this->configuracion['reglas'], $this->configuracion['errores'])){
    $l_error = $this->validator->getErrors();
    // El withInput() es lo que "guarda" los datos en la sesión para la siguiente petición
    return redirect()->back()->withInput()->with('error', reset($l_error));
    }
    $datos=[
        'nombre'=>$this->request->getPost('nom'),
        'ape_pat'=>$this->request->getPost('ape_pat'),
        'ape_mat'=>$this->request->getPost('ape_mat'),
        'telefono'=>$this->request->getPost('tel'),
        'direccion'=>$this->request->getPost('dir'),
        'notas'=>$this->request->getPost('not'),
    ];

    try {
        // a veces lanza DatabaseException. Capturarla aquí evita la pantalla de error.
        if (!$m_repartidor->insert($datos)) {
            // Si el modelo falla por reglas de validación internas o DB
            return redirect()->back()->withInput()->with('error', 'Error al guardar en base de datos');
        }

        $destino = ($this->request->getPost('origen') === 'main_page') ? '/' : 'lista_repartidor';
        return redirect()->to($destino)->with('mensaje', 'Repartidor registrado correctamente');

    } catch (\Exception $r) {
        // 4. Captura de errores inesperados (ej. problemas de conexión, campos duplicados)
        return redirect()->to('lista_repartidor')->with('error', 'Ocurrió un error inesperado al procesar la solicitud.');
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
        return redirect()->to('lista_repartidor')->with('mensaje', 'Repartidor actualizado correctamente');
    }
}   
public function lista_repartidor()
{
    $buscar = $this->request->getGet('buscar') ?? '';

    $m_repartidor = new Modelo_repartidor();

    $datos = [
        'repartidores' => $m_repartidor->filtrar($buscar)->orderBy('id', 'DESC')->paginate(20),
        'pager'        => $m_repartidor->pager,
        'buscar'       => $buscar,
    ];

    return view('lista_repartidor', $datos);
}
public function recupera($id=null){
    $m_repartidor = new Modelo_repartidor();
    $datos['repartidores']=$m_repartidor->find($id);
    if(!$datos['repartidores']){
        return redirect()->to('lista_repartidor');
    }
    return view('modifica_repartidor', $datos);
}
public function eliminar_datos($id = null){
    $m_repartidor = new Modelo_repartidor();
    if(!$m_repartidor->find($id)){
        return redirect()->to('lista_repartidor');
    }
    try {
        $m_repartidor->delete($id);
        return redirect()->to('lista_repartidor')->with('mensaje', 'Repartidor eliminado correctamente.');
    } catch (\CodeIgniter\Database\Exceptions\DatabaseException $e) {
        return redirect()->to('lista_repartidor')->with('error', 'No se puede eliminar el repartidor porque tiene pedidos');
    }
}
}