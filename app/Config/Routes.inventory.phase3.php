<?php

/*
 * PHASE 3 ROUTES
 * Merge these into app/Config/Routes.php.
 */

$routes->get('products', 'Products::index', ['filter' => 'auth:products.view']);
$routes->get('products/create', 'Products::create', ['filter' => 'auth:products.create']);
$routes->post('products/store', 'Products::store', ['filter' => 'auth:products.create']);

$routes->get('products/categories', 'Products::categories', ['filter' => 'auth:products.view']);
$routes->post('products/categories/store', 'Products::categoryStore', ['filter' => 'auth:products.create']);

$routes->get('inventory', 'Inventory::index', ['filter' => 'auth:inventory.view']);
$routes->get('inventory/in', 'Inventory::in', ['filter' => 'auth:inventory.in']);
$routes->get('inventory/out', 'Inventory::out', ['filter' => 'auth:inventory.out']);
$routes->post('inventory/store', 'Inventory::store', ['filter' => 'auth:inventory.view']);
$routes->get('inventory/transactions', 'Inventory::transactions', ['filter' => 'auth:inventory.view']);
