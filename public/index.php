<?php
// header for API
header("Content-Type: application/json");

// include database connection
require_once "config/db.php";

// check route from URL
$route = isset($_GET['route']) ? $_GET['route'] : 'products';
// get request method
$method = $_SERVER['REQUEST_METHOD'];

if ($route === 'sales' && $method === 'POST') {
    
    $data = json_decode(file_get_contents('php://input'), true);

    if (!isset($data['payment_method']) || !isset($data['items']) || !is_array($data['items']) || empty($data['items'])) {
        http_response_code(400);
        echo json_encode(["status" => "error", "message" => "Data transaksi tidak lengkap atau item kosong."]);
        $conn->close();
        exit();
    }

    $payment_method = $data['payment_method'];
    $items = $data['items'];
    $total_amount = 0;
    $success = true;

    $conn->begin_transaction();

    try {
        foreach ($items as $item) {
            $total_amount += $item['quantity'] * $item['price_at_sale'];
        }

        $sql_sale = "INSERT INTO sales (total_amount, payment_method) VALUES (?, ?)";
        $stmt_sale = $conn->prepare($sql_sale);
        if ($stmt_sale === false) throw new Exception("Prepare statement sales gagal: " . $conn->error);

        $stmt_sale->bind_param('ds', $total_amount, $payment_method);

        $stmt_sale->execute();
        $sale_id = $conn->insert_id;
        $stmt_sale->close();

        $sql_item = "INSERT INTO sale_items (sale_id, product_id, quantity, price_at_sale) VALUES (?, ?, ?, ?)";
        $stmt_item = $conn->prepare($sql_item);
        if ($stmt_item === false) throw new Exception("Prepare item gagal: " . $conn->error);

        $sql_stock = "UPDATE products SET stock = stock - ? WHERE id = ?";
        $stmt_stock = $conn->prepare($sql_stock);
        if ($stmt_stock === false) throw new Exception("Prepare stock gagal: " . $conn->error);

        foreach ($items as $item) {
            $product_id = (int)$item['product_id'];
            $quantity = (int)$item['quantity'];
            $price_at_sale = $item['price_at_sale'];

            $stmt_item->bind_param('iiid', $sale_id, $product_id, $quantity, $price_at_sale);
            $stmt_item->execute();
            if ($stmt_item->affected_rows === 0) throw new Exception("Gagal Memasukkan item: ID $product_id");

            $stmt_stock->bind_param('ii', $quantity, $product_id);
            $stmt_stock->execute();
            if ($stmt_stock->affected_rows === 0) throw new Exception("Gagal update stock: ID $product_id"); 
        }

        $stmt_item->close();
        $stmt_stock->close();

        $conn->commit();

        http_response_code(201);
        echo json_encode([
            "status" => "success",
            "message" => "Transaksi berhasil dicatat,",
            "sale_id" => $sale_id,
            "total" => $total_amount
        ]);
    } catch (Exception $e) {
        $conn->rollback();

        http_response_code(500);
        echo json_encode([
            "status" => "error",
            "message" => "Transaksi dibatalkan. Detail: " . $e->getMessage()
        ]);
        $success = false;
    }
}

if ($route === 'product') {

    // handle GET request to fetch products
    if ($method === 'GET') {
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

    if ($method ==='POST') {
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
}

if (isset($conn)) {
    $conn->close();
}
