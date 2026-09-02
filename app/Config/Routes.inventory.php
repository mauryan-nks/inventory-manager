<?php

/*
 * INVENTORY MANAGER ROUTES
 *
 * Add these lines to app/Config/Routes.php after the standard
 * CodeIgniter route setup.
 */

$routes->get('/', 'Auth::login');
$routes->get('login', 'Auth::login');
$routes->post('login', 'Auth::authenticate');
$routes->get('logout', 'Auth::logout');

$routes->get('dashboard', 'Dashboard::index', ['filter' => 'auth']);

$routes->get('users', 'Users::index', ['filter' => 'auth:users.view']);
$routes->get('users/create', 'Users::create', ['filter' => 'auth:users.create']);
$routes->post('users/store', 'Users::store', ['filter' => 'auth:users.create']);

/*
 * Placeholders for the next phases:
 *
 * $routes->get('products', 'Products::index', ['filter' => 'auth:products.view']);
 * $routes->get('inventory/in', 'Inventory::in', ['filter' => 'auth:inventory.in']);
 * $routes->get('inventory/out', 'Inventory::out', ['filter' => 'auth:inventory.out']);
 * $routes->get('security', 'Security::index', ['filter' => 'auth:security.scan']);
 */
