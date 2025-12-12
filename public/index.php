<?php
// First config Request
header('Content-Type: application/json');

// Load all needed files
require_once 'config/db.php'; // Ambil koneksi $conn
require_once 'controllers/ProductController.php';
require_once 'controllers/SaleController.php';

// Define the route
$method = $_SERVER['REQUEST_METHOD'];

$uri = trim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/');

$uri_segments = explode('/', $uri);

$route = isset($uri_segments[0]) && $uri_segments[0] !== '' ? strtolower($uri_segments[0]) : 'products';

$response = [];

// ===================================
// ROUTING LOGIC
// ===================================
if ($route === 'products') {
    $controller = new ProductController($conn);

    if ($method === 'GET') {
        $response = $controller->handleGetRequest();
    } elseif ($method === 'POST') {
        $response = $controller->handlePostRequest();
    } elseif ($method === 'PUT') { // BARU
        $response = $controller->handlePutRequest();
    } elseif ($method === 'DELETE') { // BARU
        $response = $controller->handleDeleteRequest();
    }

} elseif ($route === 'sales') {
    $controller = new SaleController($conn);

    if ($method === 'POST') {
        $response = $controller->handlePostRequest();
    }
} else {
    // Route errors handling
    http_response_code(404);
    $response = ["status" => "error", "message" => "Endpoint tidak ditemukan."];
}

// OUTPUT: return value from Controller
echo json_encode($response);

$conn->close();
?>