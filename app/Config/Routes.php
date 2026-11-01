<?php

use CodeIgniter\Router\RouteCollection;

/**
 * @var RouteCollection $routes
 *
 * Mapa de acceso
 *  - Públicas ........ cualquiera puede entrar (landing de la tienda, login/registro de Shield).
 *  - Tienda .......... exige sesión iniciada (filtro "session"): carrito y pedido.
 *  - Panel ........... solo personal (filtro "group"): superadmin, admin y developer.
 *  - Repartidor ...... solo el grupo "repartidor": ve y actualiza únicamente sus entregas.
 *
 * Toda acción que modifica datos (guardar, modificar, BORRAR) es POST: el filtro CSRF solo
 * protege los métodos que no son GET, y un enlace GET de borrado se podría disparar desde
 * cualquier otra página.
 */

// ───────────── PÚBLICAS ─────────────
// "/" muestra el dashboard al personal; a todos los demás los manda a la landing.
$routes->get('/', 'Productos::main_page');
$routes->get('main_page_4', 'Tienda::index');

service('auth')->routes($routes);

// ───────────── TIENDA (cliente con sesión) ─────────────
$routes->group('tienda', ['filter' => 'session'], static function (RouteCollection $routes) {
    $routes->get('carrito', 'Tienda::carrito');
    $routes->post('carrito/agregar', 'Tienda::agregar');
    $routes->post('carrito/cambiar', 'Tienda::cambiar');
    $routes->post('carrito/quitar', 'Tienda::quitar');
    $routes->post('carrito/vaciar', 'Tienda::vaciar');
    $routes->post('pedido', 'Tienda::confirmar');
    $routes->post('pedido/(:num)/recargar', 'Tienda::recargar/$1');
});

// ───────────── BUZÓN DE SUGERENCIAS (cualquier usuario con sesión, de cualquier rol) ─────────────
// El CSRF lo aplica el filtro global; el guardado es POST.
$routes->group('', ['filter' => 'session'], static function (RouteCollection $routes) {
    $routes->get('crea_sugerencia', 'Sugerencias::crea_sugerencia');
    $routes->post('guarda_sugerencia', 'Sugerencias::guarda_sugerencia');
});

// ───────────── REPARTIDOR (solo su grupo) ─────────────
$routes->group('repartidor', ['filter' => 'group:repartidor'], static function (RouteCollection $routes) {
    $routes->get('entregas', 'Entregas::mis_entregas');
    $routes->post('entregas/(:num)/salir', 'Entregas::salir/$1');
    $routes->post('entregas/(:num)/entregar', 'Entregas::entregar/$1');
    $routes->post('entregas/(:num)/fallar', 'Entregas::fallar/$1');
});

// ───────────── PANEL (solo personal) ─────────────
$routes->group('', ['filter' => 'group:superadmin,admin,developer'], static function (RouteCollection $routes) {
    // ===== PRODUCTOS =====
    $routes->get('crea_producto', 'Productos::crea_producto');
    $routes->get('lista_producto', 'Productos::lista_producto');
    $routes->get('pasa_id_producto/(:num)', 'Productos::recupera/$1');
    $routes->post('borra_id_producto/(:num)', 'Productos::eliminar_datos/$1');
    $routes->post('guarda_producto', 'Productos::guarda_producto');
    $routes->post('modifica_producto', 'Productos::modifica');

    // ===== CLIENTES =====
    $routes->get('crea_cliente', 'Clientes::crea_cliente');
    $routes->get('lista_cliente', 'Clientes::lista_cliente');
    $routes->get('pasa_id_cliente/(:num)', 'Clientes::recupera/$1');
    $routes->post('borra_id_cliente/(:num)', 'Clientes::eliminar_datos/$1');
    $routes->post('guarda_cliente', 'Clientes::guarda_cliente');
    $routes->post('modifica_cliente', 'Clientes::modifica');
    $routes->post('preferencial_cliente', 'Clientes::preferencial');

    // ===== REPARTIDORES =====
    $routes->get('crea_repartidor', 'Repartidores::crea_repartidor');
    $routes->get('lista_repartidor', 'Repartidores::lista_repartidor');
    $routes->get('pasa_id_repartidor/(:num)', 'Repartidores::recupera/$1');
    $routes->post('borra_id_repartidor/(:num)', 'Repartidores::eliminar_datos/$1');
    $routes->post('guarda_repartidor', 'Repartidores::guarda_repartidor');
    $routes->post('modifica_repartidor', 'Repartidores::modifica');

    // ===== DIRECCIONES =====
    $routes->get('crea_direccion', 'Direcciones::crea_direccion');
    $routes->get('lista_direccion', 'Direcciones::lista_direccion');
    $routes->post('guarda_direccion', 'Direcciones::guarda_direccion');
    $routes->post('modifica_direccion', 'Direcciones::modifica');
    $routes->get('pasa_id_direccion/(:num)', 'Direcciones::recupera/$1');
    $routes->post('borra_id_direccion/(:num)', 'Direcciones::eliminar_datos/$1');

    // ===== ENTRADAS =====
    $routes->get('crea_entrada', 'Entradas::crea_entrada');
    $routes->get('lista_entrada', 'Entradas::lista_entrada');
    $routes->post('guarda_entrada', 'Entradas::guarda_entrada');
    $routes->get('entrada_rapida', 'Entradas::entrada_rapida');
    $routes->post('guarda_entrada_rapida', 'Entradas::guarda_entrada_rapida');
    $routes->post('modifica_entrada', 'Entradas::modifica_entrada');
    $routes->get('pasa_id_entrada/(:num)', 'Entradas::recupera/$1');
    $routes->post('borra_id_entrada/(:num)', 'Entradas::eliminar_datos/$1');

    // ===== PEDIDOS =====
    $routes->get('crea_pedido', 'Pedidos::crea_pedido');
    $routes->get('lista_pedido', 'Pedidos::lista_pedido');
    $routes->post('guarda_pedido', 'Pedidos::guarda_pedido');
    $routes->post('modifica_pedido', 'Pedidos::modifica');
    $routes->post('borra_id_pedido/(:num)', 'Pedidos::eliminar_datos/$1');
    $routes->get('pasa_id_pedido/(:num)', 'Pedidos::recupera/$1');

    // ===== ESTATUS =====
    $routes->get('crea_estatus', 'Estatus::crea_estatus');
    $routes->get('lista_estatus', 'Estatus::lista_estatus');
    $routes->post('guarda_estatus', 'Estatus::guarda_estatus');
    $routes->post('modifica_estatus', 'Estatus::modifica');
    $routes->post('borra_id_estatus/(:num)', 'Estatus::eliminar_datos/$1');
    $routes->get('pasa_id_estatus/(:num)', 'Estatus::recupera/$1');

    // ===== EXISTENCIAS =====
    $routes->get('crea_existencia', 'Existencias::crea_existencia');
    $routes->get('lista_existencia', 'Existencias::lista_existencia');
    $routes->post('guarda_existencia', 'Existencias::guarda_existencia');
    $routes->post('modifica_existencia', 'Existencias::modifica');
    $routes->post('borra_id_existencia/(:num)', 'Existencias::eliminar_datos/$1');
    $routes->get('pasa_id_existencia/(:num)', 'Existencias::recupera/$1');

    // ===== MERMAS =====
    $routes->get('crea_merma', 'Mermas::crea_merma');
    $routes->get('lista_merma', 'Mermas::lista_merma');
    $routes->post('guarda_merma', 'Mermas::guarda_merma');
    $routes->post('modifica_merma', 'Mermas::modifica');
    $routes->post('borra_id_merma/(:num)', 'Mermas::eliminar_datos/$1');
    $routes->get('pasa_id_merma/(:num)', 'Mermas::recupera/$1');

    // ===== ENTREGAS (asignación del administrador) =====
    $routes->get('lista_entrega', 'Entregas::lista_entrega');
    $routes->post('asigna_entrega', 'Entregas::asigna_entrega');
    $routes->post('cancela_entrega/(:num)', 'Entregas::cancela_entrega/$1');

    // ===== TICKET DEL PEDIDO POR WHATSAPP =====
    $routes->get('ticket_pedido/(:num)', 'Tickets::ver/$1');

    // ===== TICKET IMPRESO (impresora térmica ESC/POS o vista del navegador) =====
    // POST: el filtro CSRF global exige el token (cabecera X-CSRF-TOKEN en public/js/impresion_ticket.js).
    $routes->get('ticket_impreso/(:num)', 'Tickets::vista/$1');
    $routes->post('imprime_ticket/(:num)', 'Tickets::imprimir/$1');
    $routes->get('impresora', 'Tickets::configuracion');
    $routes->post('impresora/prueba', 'Tickets::prueba');
    $routes->post('impresora/conexion', 'Tickets::conexion');

    // ===== SUGERENCIAS (lectura del personal; el formulario es para todos y va más arriba) =====
    $routes->get('lista_sugerencia', 'Sugerencias::lista_sugerencia');

    // ===== P_PEDIDOS =====
    $routes->get('crea_p_pedido', 'P_pedidos::crea_p_pedido');
    $routes->get('lista_p_pedido', 'P_pedidos::lista_p_pedido');
    $routes->post('guarda_p_pedido', 'P_pedidos::guarda_p_pedido');
    $routes->post('modifica_p_pedido', 'P_pedidos::modifica');
    $routes->post('borra_id_p_pedido/(:num)', 'P_pedidos::eliminar_datos/$1');
    $routes->get('pasa_id_p_pedido/(:num)', 'P_pedidos::recupera/$1');
    $routes->get('api_pedido/(:num)', 'P_pedidos::api_pedido/$1');
    $routes->get('api_recargar_pedido/(:num)', 'P_pedidos::api_recargar_pedido/$1');
    $routes->post('guarda_pedido_completo', 'P_pedidos::guarda_pedido_completo');
    $routes->post('modifica_pedido_completo', 'P_pedidos::modifica_pedido_completo');
    $routes->post('borra_pedido_completo/(:num)', 'P_pedidos::borra_pedido_completo/$1');
});
