<?php
// First config Request
header('Content-Type: application/json');

// Load all needed files
require_once 'config/db.php'; // Ambil koneksi $conn
require_once 'controllers/ProductController.php';
require_once 'controllers/SaleController.php';

// Define the route
$method = $_SERVER['REQUEST_METHOD'];

// KOREKSI UTAMA PADA PARSING URI
// ----------------------------------------------------
$uri_path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
// Pastikan kita bekerja dengan path yang telah dibersihkan, bukan variabel yang tidak ada.
$clean_path = trim($uri_path, '/');

// Menggunakan $clean_path, bukan $uri yang tidak terdefinisi.
$uri_segments = array_values(array_filter(explode('/', $clean_path)));
// ----------------------------------------------------

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
    } elseif ($method === 'PUT') { 
        $response = $controller->handlePutRequest();
    } elseif ($method === 'DELETE') { 
        $response = $controller->handleDeleteRequest();
    }

} elseif ($route === 'sales') {
    $controller = new SaleController($conn);
    
    // KOREKSI TYPO: Menggunakan satu underscore (sale_id)
    $sale_id = isset($uri_segments[1]) ? $uri_segments[1] : null;

    if ($method === 'POST') {
        $response = $controller->handlePostRequest();
    } elseif ($method === 'GET') {
        if ($sale_id) {
            // Jika ada ID (GET /sales/1)
            $response = $controller->handleGetDetailRequest($sale_id);
        } else {
            // Jika tidak ada ID (GET /sales)
            $response = $controller->handleGetAllRequest();
        }
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