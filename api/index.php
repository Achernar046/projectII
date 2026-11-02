<?php
/**
 * api/index.php
 * Main RESTful API Entry Point & Routing Gateway
 */

// Global CORS Configuration
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, X-HTTP-Method-Override');

// Handle Preflight Request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// Global Exception Handler
set_exception_handler(function (Throwable $e) {
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => false,
        'message' => 'เกิดข้อผิดพลาดภายในเซิร์ฟเวอร์: ' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
    exit;
});

// Load Core Infrastructure
require_once __DIR__ . '/core/Database.php';
require_once __DIR__ . '/core/Response.php';
require_once __DIR__ . '/core/JWT.php';
require_once __DIR__ . '/core/AuthMiddleware.php';
require_once __DIR__ . '/core/Router.php';

// Load Controllers
require_once __DIR__ . '/controllers/AuthController.php';
require_once __DIR__ . '/controllers/DashboardController.php';
require_once __DIR__ . '/controllers/ProductController.php';
require_once __DIR__ . '/controllers/CategoryController.php';
require_once __DIR__ . '/controllers/OrderController.php';
require_once __DIR__ . '/controllers/CustomerController.php';
require_once __DIR__ . '/controllers/SupplierController.php';
require_once __DIR__ . '/controllers/EmployeeController.php';
require_once __DIR__ . '/controllers/ShipperController.php';
require_once __DIR__ . '/controllers/ReportController.php';

// Initialize Database & Controllers
$db = Database::getConnection();

$authCtrl      = new AuthController($db);
$dashboardCtrl = new DashboardController($db);
$productCtrl   = new ProductController($db);
$categoryCtrl  = new CategoryController($db);
$orderCtrl     = new OrderController($db);
$customerCtrl  = new CustomerController($db);
$supplierCtrl  = new SupplierController($db);
$employeeCtrl  = new EmployeeController($db);
$shipperCtrl   = new ShipperController($db);
$reportCtrl    = new ReportController($db);

$router = new Router();

// Middleware helper
$authGuard = [AuthMiddleware::class, 'authenticate'];

// ─── Authentication Routes ──────────────────────────────
$router->post('/auth/login',  [$authCtrl, 'login']);
$router->get('/auth/me',      [$authCtrl, 'me'], [$authGuard]);
$router->post('/auth/logout', [$authCtrl, 'logout']);

// ─── Dashboard Routes ───────────────────────────────────
$router->get('/dashboard/kpis',   [$dashboardCtrl, 'kpis']);
$router->get('/dashboard/charts', [$dashboardCtrl, 'charts']);

// ─── Products Routes (CRUD) ─────────────────────────────
$router->get('/products',          [$productCtrl, 'index']);
$router->get('/products/{id}',     [$productCtrl, 'show']);
$router->post('/products',         [$productCtrl, 'store'],  [$authGuard]);
$router->put('/products/{id}',      [$productCtrl, 'update'], [$authGuard]);
$router->delete('/products/{id}',   [$productCtrl, 'delete'], [$authGuard]);

// ─── Categories Routes (CRUD) ───────────────────────────
$router->get('/categories',        [$categoryCtrl, 'index']);
$router->get('/categories/{id}',   [$categoryCtrl, 'show']);
$router->post('/categories',       [$categoryCtrl, 'store'],  [$authGuard]);
$router->put('/categories/{id}',    [$categoryCtrl, 'update'], [$authGuard]);
$router->delete('/categories/{id}', [$categoryCtrl, 'delete'], [$authGuard]);

// ─── Orders Routes (CRUD) ───────────────────────────────
$router->get('/orders',                       [$orderCtrl, 'index']);
$router->get('/orders/{id}',                  [$orderCtrl, 'show']);
$router->post('/orders',                      [$orderCtrl, 'store'],  [$authGuard]);
$router->put('/orders/{id}',                   [$orderCtrl, 'update'], [$authGuard]);
$router->delete('/orders/{id}',                [$orderCtrl, 'delete'], [$authGuard]);
$router->post('/orders/{id}/items',           [$orderCtrl, 'addItem'], [$authGuard]);
$router->delete('/orders/{id}/items/{detailId}', [$orderCtrl, 'deleteItem'], [$authGuard]);

// ─── Customers Routes (CRUD) ────────────────────────────
$router->get('/customers',        [$customerCtrl, 'index']);
$router->get('/customers/{id}',   [$customerCtrl, 'show']);
$router->post('/customers',       [$customerCtrl, 'store'],  [$authGuard]);
$router->put('/customers/{id}',    [$customerCtrl, 'update'], [$authGuard]);
$router->delete('/customers/{id}', [$customerCtrl, 'delete'], [$authGuard]);

// ─── Suppliers Routes (CRUD) ────────────────────────────
$router->get('/suppliers',        [$supplierCtrl, 'index']);
$router->get('/suppliers/{id}',   [$supplierCtrl, 'show']);
$router->post('/suppliers',       [$supplierCtrl, 'store'],  [$authGuard]);
$router->put('/suppliers/{id}',    [$supplierCtrl, 'update'], [$authGuard]);
$router->delete('/suppliers/{id}', [$supplierCtrl, 'delete'], [$authGuard]);

// ─── Employees Routes (CRUD) ────────────────────────────
$router->get('/employees',        [$employeeCtrl, 'index']);
$router->get('/employees/{id}',   [$employeeCtrl, 'show']);
$router->post('/employees',       [$employeeCtrl, 'store'],  [$authGuard]);
$router->put('/employees/{id}',    [$employeeCtrl, 'update'], [$authGuard]);
$router->delete('/employees/{id}', [$employeeCtrl, 'delete'], [$authGuard]);

// ─── Shippers Routes (CRUD) ─────────────────────────────
$router->get('/shippers',        [$shipperCtrl, 'index']);
$router->get('/shippers/{id}',   [$shipperCtrl, 'show']);
$router->post('/shippers',       [$shipperCtrl, 'store'],  [$authGuard]);
$router->put('/shippers/{id}',    [$shipperCtrl, 'update'], [$authGuard]);
$router->delete('/shippers/{id}', [$shipperCtrl, 'delete'], [$authGuard]);

// ─── Reports Routes ─────────────────────────────────────
$router->get('/reports/sales',  [$reportCtrl, 'salesSummary']);
$router->get('/reports/export', [$reportCtrl, 'export']);

// ─── Determine Route Path ───────────────────────────────
$requestPath = $_GET['__route'] ?? $_GET['route'] ?? '';

if ($requestPath === '') {
    $uri = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
    if (preg_match('#/api(?:/index\.php)?/(.*)$#i', $uri, $m)) {
        $requestPath = $m[1];
    }
}

$requestMethod = $_SERVER['REQUEST_METHOD'];

// Support HTTP Method Spoofing
if ($requestMethod === 'POST') {
    $spoofed = $_POST['_method'] ?? $_SERVER['HTTP_X_HTTP_METHOD_OVERRIDE'] ?? $_GET['_method'] ?? null;
    if ($spoofed && in_array(strtoupper($spoofed), ['PUT', 'DELETE', 'PATCH'])) {
        $requestMethod = strtoupper($spoofed);
    }
}

// Dispatch Request
$router->dispatch($requestMethod, $requestPath);
