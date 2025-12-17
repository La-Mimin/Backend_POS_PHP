<?php
header('Content-Type: application/json');

require_once 'config/db.php';
require_once 'controllers/ProductController.php';
require_once 'controllers/SaleController.php';
require_once 'controllers/UserController.php';

// 1. Definisikan Method
$method = strtoupper(trim($_SERVER['REQUEST_METHOD']));

// 2. Definisikan Route yang dilindungi (HARUS SEBELUM MIDDLEWARE)
$protected_routes = [
    'products' => ['POST', 'PUT', 'DELETE'], 
    'sales'    => ['GET', 'POST'],
];

// 3. Parsing URI untuk mendapatkan $route
$uri_path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$clean_path = trim($uri_path, '/');
$uri_segments = array_values(array_filter(explode('/', $clean_path)));
$route = isset($uri_segments[0]) && $uri_segments[0] !== '' ? strtolower($uri_segments[0]) : 'products';

// 4. --- MIDDLEWARE OTENTIKASI JWT ---
if (isset($protected_routes[$route]) && in_array($method, $protected_routes[$route])) {
    $headers = apache_request_headers();
    // Perbaikan typo $isset menjadi isset
    $auth_header = isset($headers['Authorization']) ? $headers['Authorization'] : ($_SERVER['HTTP_AUTHORIZATION'] ?? '');

    $token = null;
    if (preg_match('/Bearer\s(\S+)/', $auth_header, $matches)) {
        $token = $matches[1];
    }

    $user_controller = new UserController($conn);
    $user_data = $user_controller->verifyJWT($token);

    if (!$user_data) {
        http_response_code(401);
        echo json_encode([
            "status" => "error",
            "message" => "Akses ditolak: Token tidak valid atau hilang."
        ]);
        $conn->close();
        exit; // Menghentikan aplikasi di sini jika gagal
    }

    $GLOBALS['user_data'] = $user_data;
}

$response = [];

// 5. --- ROUTING LOGIC ---
if ($route === 'products') {
    $controller = new ProductController($conn);

    if ($method === 'GET') {
        $response = $controller->handleGetRequest();
    } elseif ($method === 'POST') {
        $response = $controller->handlePostRequest();
    } elseif ($method === 'PUT') { 
        $response = $controller->handlePutRequest();
    } elseif ($method === 'DELETE') { 
        $response = $controller->handleDeleteRequest();
    }

} elseif ($route === 'sales') {
    $controller = new SaleController($conn);
    $sale_id = isset($uri_segments[1]) ? $uri_segments[1] : null;

    if ($method === 'POST') {
        $response = $controller->handlePostRequest();
    } elseif ($method === 'GET') {
        if ($sale_id) {
            $response = $controller->handleGetDetailRequest($sale_id);
        } else {
            $response = $controller->handleGetAllRequest();
        }
    }

} elseif ($route === 'login') {
    $controller = new UserController($conn);
    if ($method === 'POST') {
        $response = $controller->handleLoginRequest();
    } else {
        http_response_code(405);
        $response = ["status" => "error", "message" => "Metode tidak diizinkan."];
    }
} else {
    http_response_code(404);
    $response = ["status" => "error", "message" => "Endpoint tidak ditemukan."];
}

echo json_encode($response);
$conn->close();