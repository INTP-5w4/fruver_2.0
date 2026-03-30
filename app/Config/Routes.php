<?php

use CodeIgniter\Router\RouteCollection;

/**
 * @var RouteCollection $routes
 */
$routes->get('/', 'Productos::main_page');

//==========================PRODUCTO=================================
$routes->get('crea_producto', 'Productos::crea_producto');
$routes->get('lista_producto', 'Productos::lista_producto');
$routes->get('pasa_id_producto/(:num)', 'Productos::recupera/$1');
$routes->get('borra_id_producto/(:num)', 'Productos::eliminar_datos/$1');
$routes->post('guarda_producto', 'Productos::guarda_producto');
$routes->post('modifica_producto', 'Productos::modifica');
//===========================CLIENTE=================================
$routes->get('crea_cliente', 'Clientes::crea_cliente');
$routes->get('lista_cliente', 'Clientes::lista_cliente');
$routes->get('pasa_id_cliente/(:num)', 'Clientes::recupera/$1');
$routes->get('borra_id_cliente/(:num)', 'Clientes::eliminar_datos/$1');
$routes->post('guarda_cliente', 'Clientes::guarda_cliente');
$routes->post('modifica_cliente', 'Clientes::modifica');
//===========================REPARTIDOR==============================
$routes->get('crea_repartidor', 'Repartidores::crea_repartidor');
$routes->get('lista_repartidor', 'Repartidores::lista_repartidor');
$routes->get('pasa_id_repartidor/(:num)', 'Repartidores::recupera/$1');
$routes->get('borra_id_repartidor/(:num)', 'Repartidores::eliminar_datos/$1');
$routes->post('guarda_repartidor', 'Repartidores::guarda_repartidor');
$routes->post('modifica_repartidor', 'Repartidores::modifica');
//===========================DIRECCION================================
$routes->get('crea_direccion', 'Direcciones::crea_direccion');
$routes->get('lista_direccion', 'Direcciones::lista_direccion');
$routes->post('guarda_direccion', 'Direcciones::guarda_direccion');
$routes->post('modifica_direccion', 'Direcciones::modifica');
$routes->get('pasa_id_direccion/(:num)', 'Direcciones::recupera/$1');
$routes->get('borra_id_direccion/(:num)', 'Direcciones::eliminar_datos/$1');
//============================ENTRADA=================================
$routes->get('crea_entrada', 'Entradas::crea_entrada');
$routes->get('lista_entrada', 'Entradas::lista_entrada');
$routes->post('guarda_entrada', 'Entradas::guarda_entrada');
$routes->post('modifica_entrada', 'Entradas::modifica_entrada');
$routes->get('pasa_id_entrada/(:num)', 'Entradas::recupera/$1');
$routes->get('borra_id_entrada/(:num)', 'Entradas::eliminar_datos/$1');
//=============================PEDIDOS=================================
$routes->get('crea_pedido', 'Pedidos::crea_pedido');
$routes->get('lista_pedido', 'Pedidos::lista_pedido');
$routes->post('guarda_pedido', 'Pedidos::guarda_pedido');
$routes->post('modifica_pedido', 'Pedidos::modifica');
$routes->get('borra_id_pedido/(:num)', 'Pedidos::eliminar_datos/$1');
$routes->get('pasa_id_pedido/(:num)', 'Pedidos::recupera/$1');