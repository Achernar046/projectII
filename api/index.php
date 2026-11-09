<?php
/**
 * api/index.php
 * Main RESTful API Entry Point & Routing Gateway
 */

// Security headers for API responses.
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: no-referrer');
header('Cache-Control: no-store');

// Same-origin calls need no CORS exception; cross-origin clients must be listed explicitly.
$origin = trim((string)($_SERVER['HTTP_ORIGIN'] ?? ''));
$configuredOrigins = array_values(array_filter(array_map('trim', explode(',', getenv('CORS_ALLOWED_ORIGINS') ?: ''))));
$originAllowed = $origin !== '' && in_array($origin, $configuredOrigins, true);

if ($originAllowed) {
    header('Access-Control-Allow-Origin: ' . $origin);
    header('Vary: Origin');
}
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, X-HTTP-Method-Override');
header('Access-Control-Max-Age: 600');

// Handle Preflight Request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    if ($origin !== '' && !$originAllowed) {
        http_response_code(403);
        exit;
    }
    http_response_code(204);
    exit;
}

// Global Exception Handler
set_exception_handler(function (Throwable $e) {
    error_log(sprintf('[API] %s: %s in %s:%d', get_class($e), $e->getMessage(), $e->getFile(), $e->getLine()));
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    $isLocal = in_array(strtolower((string)($_SERVER['HTTP_HOST'] ?? '')), ['localhost', 'localhost:8888', '127.0.0.1', '127.0.0.1:8888'], true);
    $debug = $isLocal && getenv('APP_DEBUG') === 'true';
    echo json_encode([
        'success' => false,
        'message' => $debug ? 'เกิดข้อผิดพลาดภายในเซิร์ฟเวอร์: ' . $e->getMessage() : 'เกิดข้อผิดพลาดภายในเซิร์ฟเวอร์'
    ], JSON_UNESCAPED_UNICODE);
    exit;
});

// Load Core Infrastructure
require_once __DIR__ . '/core/Database.php';
require_once __DIR__ . '/core/Response.php';
require_once __DIR__ . '/core/JWT.php';
require_once __DIR__ . '/core/AuthMiddleware.php';
require_once __DIR__ . '/core/LoginRateLimiter.php';
require_once __DIR__ . '/core/TokenRevocationStore.php';
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

// ─── Authentication Routes ──────────────────────────────
$router->post('/auth/login',  [$authCtrl, 'login']);
$router->get('/auth/me',      [$authCtrl, 'me']);
$router->put('/auth/password', [$authCtrl, 'changePassword']);
$router->post('/auth/logout', [$authCtrl, 'logout']);

// ─── Dashboard Routes ───────────────────────────────────
$router->get('/dashboard/kpis',   [$dashboardCtrl, 'kpis']);
$router->get('/dashboard/charts', [$dashboardCtrl, 'charts']);

// ─── Products Routes (CRUD) ─────────────────────────────
$router->get('/products',          [$productCtrl, 'index']);
$router->get('/products/{id}',     [$productCtrl, 'show']);
$router->post('/products',         [$productCtrl, 'store']);
$router->put('/products/{id}',      [$productCtrl, 'update']);
$router->delete('/products/{id}',   [$productCtrl, 'delete']);

// ─── Categories Routes (CRUD) ───────────────────────────
$router->get('/categories',        [$categoryCtrl, 'index']);
$router->get('/categories/{id}',   [$categoryCtrl, 'show']);
$router->post('/categories',       [$categoryCtrl, 'store']);
$router->put('/categories/{id}',    [$categoryCtrl, 'update']);
$router->delete('/categories/{id}', [$categoryCtrl, 'delete']);

// ─── Orders Routes (CRUD) ───────────────────────────────
$router->get('/orders',                       [$orderCtrl, 'index']);
$router->get('/orders/{id}',                  [$orderCtrl, 'show']);
$router->post('/orders',                      [$orderCtrl, 'store']);
$router->put('/orders/{id}',                   [$orderCtrl, 'update']);
$router->delete('/orders/{id}',                [$orderCtrl, 'delete']);
$router->post('/orders/{id}/items',           [$orderCtrl, 'addItem']);
$router->delete('/orders/{id}/items/{detailId}', [$orderCtrl, 'deleteItem']);

// ─── Customers Routes (CRUD) ────────────────────────────
$router->get('/customers',        [$customerCtrl, 'index']);
$router->get('/customers/{id}',   [$customerCtrl, 'show']);
$router->post('/customers',       [$customerCtrl, 'store']);
$router->put('/customers/{id}',    [$customerCtrl, 'update']);
$router->delete('/customers/{id}', [$customerCtrl, 'delete']);

// ─── Suppliers Routes (CRUD) ────────────────────────────
$router->get('/suppliers',        [$supplierCtrl, 'index']);
$router->get('/suppliers/{id}',   [$supplierCtrl, 'show']);
$router->post('/suppliers',       [$supplierCtrl, 'store']);
$router->put('/suppliers/{id}',    [$supplierCtrl, 'update']);
$router->delete('/suppliers/{id}', [$supplierCtrl, 'delete']);

// ─── Employees Routes (CRUD) ────────────────────────────
$router->get('/employees',        [$employeeCtrl, 'index']);
$router->get('/employees/{id}',   [$employeeCtrl, 'show']);
$router->post('/employees',       [$employeeCtrl, 'store']);
$router->put('/employees/{id}',    [$employeeCtrl, 'update']);
$router->delete('/employees/{id}', [$employeeCtrl, 'delete']);

// ─── Shippers Routes (CRUD) ─────────────────────────────
$router->get('/shippers',        [$shipperCtrl, 'index']);
$router->get('/shippers/{id}',   [$shipperCtrl, 'show']);
$router->post('/shippers',       [$shipperCtrl, 'store']);
$router->put('/shippers/{id}',    [$shipperCtrl, 'update']);
$router->delete('/shippers/{id}', [$shipperCtrl, 'delete']);

// ─── Reports Routes ─────────────────────────────────────
$router->get('/reports/sales',  [$reportCtrl, 'salesSummary']);
$router->get('/reports/export', [$reportCtrl, 'export']);

// ─── Determine Route Path ───────────────────────────────
// Priority 1: explicit __route param (set by .htaccess RewriteRule)
$requestPath = $_GET['__route'] ?? $_GET['route'] ?? '';

// Priority 2: extract from REQUEST_URI when __route not available
if ($requestPath === '') {
    $uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
    $uri = $uri ?? '/';
    // Match /api/, /projectII/api/, /api/index.php/ etc.
    if (preg_match('#/api(?:/index\.php)?(/.*)?$#i', $uri, $m)) {
        $requestPath = $m[1] ?? '/';
    } else {
        $requestPath = '/';
    }
}

// Normalize: ensure leading slash, remove trailing slash (except root)
$requestPath = '/' . trim($requestPath, '/');

$requestMethod = $_SERVER['REQUEST_METHOD'];

// Support HTTP Method Spoofing via _method field or header
if ($requestMethod === 'POST') {
    $spoofed = $_POST['_method'] ?? $_SERVER['HTTP_X_HTTP_METHOD_OVERRIDE'] ?? $_GET['_method'] ?? null;
    if ($spoofed && in_array(strtoupper($spoofed), ['PUT', 'DELETE', 'PATCH'])) {
        $requestMethod = strtoupper($spoofed);
    }
}

// Require authentication for every route except the credential-exchange endpoint.
if (!($requestMethod === 'POST' && $requestPath === '/auth/login')) {
    AuthMiddleware::authenticate();
}

// Dispatch Request
$router->dispatch($requestMethod, $requestPath);
