<?php
// header for API
header("Content-Type: application/json");

// include database connection
require_once "config/db.php";

// get request method
$method = $_SERVER['REQUEST_METHOD'];

// handle GET request to fetch products
if ($method == 'GET') {
    // fetch products from database
    $sql = "SELECT id, name, price, stock FROM products";
    // execute query
    $result = $conn->query($sql);

    $products = [];

    // check if there are results and fetch
    if ($result->num_rows > 0) {
        while($row = $result->fetch_assoc()) {
            // append each products to product array
            $products[] = $row;
        }
    }

    // return product as json response
    echo json_encode([
        "status" => "success",
        "data" => $products
    ]);
}

if ($method == 'POST') {
    // get posted data
    $data = json_decode(file_get_contents('php://input'), true);

    // validate data
    if (!isset($data['name']) || !isset($data['price']) || !isset($data['stock'])) {
        http_response_code(400);
        echo json_encode([
            "status" => "error",
            "message" => "Data produk tidak lengkap: name, price, dan stock harus disediakan."
        ]);
        // close database connection
        $conn->close();
        exit();
    }

    // sanitize and assign data
    $name = $data['name'];
    $price = $data['price'];
    $stock = (int)$data['stock'];

    // prepare and bind statement
    // set query
    $sql = "INSERT INTO products (name, price, stock) values (?, ?, ?)";

    // prapare statement
    $stmt = $conn->prepare($sql);

    if ($stmt === false) {
        // handle error for prepare statement
        http_response_code(500);
        echo json_encode([
            "status" => "error",
            "message" => "Gagal menyiapkan query: " . $conn->error
        ]);

        $conn->close();
        exit();
    }

    // bind parameters
    $stmt->bind_param("sdi", $name, $price, $stock);

    // execute statment
    if ($stmt->execute()) {
        // insert successful
        $new_id = $stmt->insert_id;
        http_response_code(201);
        echo json_encode([
            "status" > "success",
            "message" => "Produk Berhasil ditambahkan.",
            "product_id" => $new_id,
            "product_name" => $name
        ]);
    } else {  // insert failed
        http_response_code(500);
        echo json_encode([
            "status" => "error",
            "message" => "Gagal menambahkan produk: " . $stmt->error
        ]);
    }

    // close statement
    $stmt->close();
}

// ===================================
// Logika untuk MEMPERBARUI PRODUK (PUT)
// ===================================
if ($method === 'PUT') {
    // parsing data from input and put into $_PUT array
    parse_str(file_get_contents("php://input"), $_PUT);

    // get id product from query string
    $product_id = isset($_GET['id']) ? (int)$_GET['id'] : null;

    // get data from request body(already pars into $_PUT)
    $data = $_PUT;

    // validate data
    if (!$product_id) {
        http_response_code(400);
        echo json_encode([
            "status" => "error",
            "message" => "ID produk yang akan diupdate harus disediakan."
        ]);
        $conn->close();
        exit();
    }

    if (!isset($data['name']) && !isset($data['price']) && !isset($data['stock'])) {
        http_response_code(400);
        echo json_encode([
            "status" => "error",
            "message" => "minimal satu field (name, price, stock) harus disertakan untuk update."
        ]);
        $conn->close();
        exit();
    }

    // build dynamis update query
    $sets = [];
    $params = [];
    $types = '';

    if (isset($data['name'])) {
        $sets[] = "name = ?";
        $params[] = $data['name'];
        $types .= 's';
    }

    if (isset($data['price'])) {
        $sets[] = "price = ?";
        $params[] = $ata['price'];
        $types .= 'd';
    }

    if (isset($data['stock'])) {
        $sets[] = "stock = ?";
        $params[] = (int)$data['stock'];
        $types .= 'i';
    }

    // add product id to last parameter and types for where clause
    $params[] = $product_id;
    $types .= 'i';

    // prepare statement
    $sql = "UPDATE products SET " . implode(', ', $sets) . " WHERE id = ?";

    $stmt = $conn->prepare($sql);

    // error handling for prepare statement
    if ($stmt === false) {
        http_response_code(500);
        echo json_encode([
            "status" => "error",
            "message" => "Gagal menyiapkan query update: " . $conn->error
        ]);
        $conn->close();
        exit();
    }

    // bind parameters dynamically
    $bind_names = [$types];

    for($i = 0; $i < count($params); $i++) {
        $bind_names[] = &$params[$i];
    }

    call_user_func_array([$stmt, 'bind_param'], $bind_names);

    // execute statement
    if ($stmt->execute()) {
        if ($stmt->affected_rows > 0) {
            echo json_encode([
                "status" => "success",
                "message" => "Produk ID $product_id berhasil diperbarui."
            ]);
        } else {
            http_response_code(404);
            echo json_encode([
                "status" => "error",
                "message" => "Produk ID $product_id tidak ditemukan atau tidak ada perubahan data."
            ]);
        }
    } else {
        http_response_code(500);
        echo json_encode([
            "status" => "error",
            "message" => "Gagal eksekusi query update: " . $stmt->error
        ]);
    }

    // close statement
    $stmt->close();
}

if ($method === 'DELETE') {
    $product_id = isset($_GET['id']) ? (int)$_GET['id'] : null;

    if (!$product_id) {
        http_response_code(400);
        echo json_encode([
            "status" => "error",
            "message" => "ID produk yang ingin dihapus harus disertakan."
        ]);
        $conn->close();
        exit();
    }

    $sql = "DELETE FROM products WHERE id = ?";

    $stmt = $conn->prepare($sql);

    if ($stmt === false) {
        http_response_code(500);
        echo json_encode([
            "status" => "error",
            "message" => "Gagal menyiapakan query delete: " . $conn->error
        ]);
        $conn->close();
        exit();
    }

    $stmt->bind_param("i", $product_id);

    if ($stmt->execute()) {
        if ($stmt->affected_rows > 0) {
            echo json_encode([
                "status" => "success",
                "message" => "Product ID $product_id berhasil dihapus."
            ]);
        } else {
            http_response_code(404);
            echo json_encode([
                "status" => "error",
                "message" => "Product ID $product_id tidak dutemukan atau tidak ada data yang dihapus."
            ]);
        }
    } else {
        http_response_code(500);
        echo json_encode([
            "status" => "error",
            "message" => "Gagal eksekusi delete: " . $stmt->error
        ]);
    }

    $stmt->close();
}

// close database connection
$conn->close();