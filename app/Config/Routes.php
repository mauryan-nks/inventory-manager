<?php

use CodeIgniter\Router\RouteCollection;

/** @var RouteCollection $routes */

$routes->get('/', 'Auth::login');
$routes->get('login', 'Auth::login');
$routes->post('login', 'Auth::authenticate');
$routes->get('logout', 'Auth::logout');
$routes->get('language/(:segment)', 'Language::set/$1', ['filter' => 'auth']);
$routes->post('language', 'Language::set', ['filter' => 'auth']);

$routes->get('dashboard', 'Dashboard::index', ['filter' => 'auth']);

$routes->get('users', 'Users::index', ['filter' => 'auth:users.view']);
$routes->get('users/create', 'Users::create', ['filter' => 'auth:users.create']);
$routes->post('users/store', 'Users::store', ['filter' => 'auth:users.create']);
$routes->get('users/(:num)/edit', 'Users::edit/$1', ['filter' => 'auth:users.edit']);
$routes->post('users/(:num)/update', 'Users::update/$1', ['filter' => 'auth:users.edit']);
$routes->post('users/(:num)/delete', 'Users::delete/$1', ['filter' => 'auth:users.delete']);

$routes->get('products', 'Products::index', ['filter' => 'auth:products.view']);
$routes->get('products/create', 'Products::create', ['filter' => 'auth:products.create']);
$routes->post('products/store', 'Products::store', ['filter' => 'auth:products.create']);
$routes->get('products/(:num)/edit', 'Products::edit/$1', ['filter' => 'auth:products.edit']);
$routes->post('products/(:num)/update', 'Products::update/$1', ['filter' => 'auth:products.edit']);
$routes->post('products/(:num)/delete', 'Products::delete/$1', ['filter' => 'auth:products.delete']);
$routes->post('products/(:num)/activate', 'Products::activate/$1', ['filter' => 'auth:products.edit']);
$routes->post('products/(:num)/hard-delete', 'Products::hardDelete/$1', ['filter' => 'auth:products.delete']);
$routes->get('products/categories', 'Products::categories', ['filter' => 'auth:products.view']);
$routes->post('products/categories/store', 'Products::categoryStore', ['filter' => 'auth:products.create']);

$routes->get('inventory', 'Inventory::index', ['filter' => 'auth:inventory.view']);
$routes->get('inventory/in', 'Inventory::in', ['filter' => 'auth:inventory.in']);
$routes->get('inventory/out', 'Inventory::out', ['filter' => 'auth:inventory.out']);
$routes->post('inventory/store', 'Inventory::store', ['filter' => 'auth']);
$routes->get('inventory/transactions', 'Inventory::transactions', ['filter' => 'auth:inventory.view']);
$routes->get('inventory/transactions/(:num)', 'Inventory::detail/$1', ['filter' => 'auth:inventory.view']);
$routes->get('inventory/transactions/(:num)/challan', 'Inventory::challan/$1', ['filter' => 'auth:inventory.view']);


$routes->get('security', 'Security::index', ['filter' => 'auth']);
$routes->get('security/scan', 'Security::scan', ['filter' => 'auth:security.scan']);
$routes->post('security/upload', 'Security::upload', ['filter' => 'auth:security.scan']);
$routes->get('security/scan/(:num)', 'Security::review/$1', ['filter' => 'auth:security.scan']);
$routes->get('security/document/(:num)', 'Security::download/$1', ['filter' => 'auth']);
$routes->post('security/scan/(:num)/confirm', 'Security::confirm/$1', ['filter' => 'auth:security.scan']);
$routes->get('security/manual', 'Security::manual', ['filter' => 'auth:security.manual_entry']);
$routes->post('security/manual', 'Security::manualStore', ['filter' => 'auth:security.manual_entry']);
$routes->get('security/history', 'Security::history', ['filter' => 'auth']);

$routes->get('reports', 'Reports::index', ['filter' => 'auth']);
$routes->get('reports/stock', 'Reports::stock', ['filter' => 'auth:reports.stock']);
$routes->get('reports/in', 'Reports::movements/IN', ['filter' => 'auth:reports.in']);
$routes->get('reports/out', 'Reports::movements/OUT', ['filter' => 'auth:reports.out']);
$routes->get('reports/security', 'Reports::security', ['filter' => 'auth:reports.security']);
$routes->get('reports/compare', 'Reports::compare', ['filter' => 'auth:reports.compare']);
$routes->get('reports/export/(:segment)', 'Reports::export/$1', ['filter' => 'auth']);

$routes->post('transactions/(:num)/void', 'Transactions::void/$1', ['filter' => 'auth:inventory.void']);
$routes->get('audit', 'Audit::index', ['filter' => 'auth:audit.view']);

$routes->get('settings', 'Settings::index', ['filter' => 'auth:settings.view']);
$routes->post('settings/save', 'Settings::save', ['filter' => 'auth:settings.manage']);
