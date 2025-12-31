<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *'); // Sesuaikan jika sudah di hosting
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Handle Preflight Request (OPTIONS)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once 'config/db.php';
require_once 'controllers/ProductController.php';
require_once 'controllers/SaleController.php';
require_once 'controllers/UserController.php';
require_once 'controllers/PurchaseController.php';
require_once 'controllers/LogController.php';

// 1. Definisikan Method
$method = strtoupper(trim($_SERVER['REQUEST_METHOD']));

// 2. Definisikan Route yang dilindungi (HARUS SEBELUM MIDDLEWARE)
$protected_routes = [
    'products' => [
        'POST' => ['admin'],
        'PUT' => ['admin'],
        'DELETE' => ['admin'],
        'GET'  => ['admin', 'kasir']
    ], 
    'sales' => [
        'POST' => ['admin', 'kasir'],
        'GET' => ['admin', 'kasir'],
        'DELETE' => ['admin']
    ],
    'purchases' => [
        'POST' => ['admin'],
        'GET' => ['admin']
    ],
    'logs' => [
        'GET' => ['admin']
    ]
];

// 3. Parsing URI untuk mendapatkan $route
$uri_path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
if (strpos($uri_path, 'index.php') !== false) {
    $parts = explode('index.php/', $uri_path);
    $clean_path = end($parts); // Ambil bagian setelah index.php/
} else {
    $clean_path = trim($uri_path, '/');
}

$uri_segments = array_values(array_filter(explode('/', $clean_path)));
$route = isset($uri_segments[0]) ? strtolower($uri_segments[0]) : '';

// 4. --- MIDDLEWARE AUTHENTICATION JWT ---
if (isset($protected_routes[$route][$method])) {

    $headers = apache_request_headers();
    $auth_header = $headers['Authorization'] ?? $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    
    $token = null;

    if (preg_match('/Bearer\s(\S+)/', $auth_header, $matches)) {

    }
    
    if (isset($protected_routes[$route][$method])) {
        $headers = apache_request_headers();
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
                "message" => "Akses ditolak: Token tidak valid atau expired."
            ]);
            $conn->close();
            exit; // Menghentikan aplikasi di sini jika gagal
        }

        $allowed_roles = $protected_routes[$route][$method];
        $user_role = $user_data['role'];

        if (!in_array($user_role, $allowed_roles)) {
            http_response_code(403);
            echo json_encode([
                "status" => "error",
                "message" => "Akses ditolak. Peran ($user_role) tidak memiliki izin untuk akses ini."
            ]);

            $conn->close();
            exit;
        }

        $GLOBALS['user_data'] = $user_data;
    }
    
}

$response = [];

// 5. --- ROUTING LOGIC ---
switch ($route) {
    case 'products':
        $controller = new ProductController($conn);

        if ($method === 'GET') {
            $response = $controller->handleGetRequest();
        } elseif ($method === 'POST') {
            $response = $controller->handlePostRequest();
        } elseif ($method === 'PUT') { 
            $response = $controller->handlePutRequest();
        } elseif ($method === 'DELETE') { 
            $response = $controller->handleDeleteRequest();
        } else {
            http_response_code(405);

            $response = [
                "status" => "error",
                "message" => "Method not allowed"
            ];
        }
        break;
    
    case 'sales':
        $controller = new SaleController($conn);

        $sub_route = $uri_segments[1] ?? null;

        if ($method === 'POST') {
            $response = $controller->handlePostRequest();
        } elseif ($method === 'DELETE') {
            $response = $controller->handleVoidRequest($sub_route);
        } elseif ($method === 'GET') {
            if ($sub_route === 'receipt' && isset($uri_segments[2])) {
                // GET /sales/receipt/12
                $response = $controller->handleReceiptRequest($uri_segments[2]);
            } elseif ($sub_route === 'summary'){
                $response = $controller->handleSummaryRequest();
            } elseif ($sub_route) {
                $response = $controller->handleGetDetailRequest($sub_route);
            } else {
                $response = $controller->handleGetAllRequest();
            }
        } else {
            http_response_code(405); $response = ["status" => "error", "message" => "Method not allowed"];
        }
        break;
    
    case 'purchases':
        $controller = new PurchaseController($conn);
    
        if ($method === 'POST') {
            $response = $controller->handlePostRequest();
        } elseif ($method === 'GET') {
            $response = $controller->handleGetAllRequest();
        } else {
            http_response_code(405);
            $response = ["status" => "error", "message" => "Metode tidak diizinkan."];
        }
        break;
    
    case 'login':
        if ($method === 'POST') {
            $controller = new UserController($conn);
            $response = $controller->handleLoginRequest();
        } else {
            http_response_code(405);
            $response = ["status" => "error", "message" => "Metode tidak diizinkan."];
        }
        break;
    
    case 'logs':
        if ($method === 'GET') {
            $controller = new LogController($conn);
            $response = $controller->handleGetRequest();
        } else {
            http_response_code(405); $response = ["status" => "error", "message" => "Method not allowed"];
        }
        break;
    
    default:
        http_response_code(404);
        $response = ["status" => "error", "message" => "Endpoint tidak ditemukan."];
        break;
}

if ($route === 'products') {
    

} elseif ($route === 'sales') {
    

} elseif ($route === 'purchases') {
    
} elseif ($route === 'login') {
    
} elseif($route === 'logs') {
    
} else {
    
}

echo json_encode($response);
$conn->close();