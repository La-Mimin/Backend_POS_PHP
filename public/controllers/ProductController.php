<?php
require_once __DIR__ . '/../models/ProductModel.php';

class ProductController {
    private $productModel;

    public function __construct($conn) {
        $this->productModel = new ProductModel($conn);
    }

    // Menangani GET /products
    public function handleGetRequest() {
        try {
            $products = $this->productModel->getAllProducts();
            http_response_code(200);
            return ["status" => "success", "data" => $products];
        } catch (Exception $e) {
            http_response_code(500);
            return ["status" => "error", "message" => "Gagal mengambil data produk."];
        }
    }

    // Menangani POST /products
    public function handlePostRequest() {
        $data = json_decode(file_get_contents('php://input'), true);

        // Validasi
        if (!isset($data['name']) || !isset($data['price']) || !isset($data['stock'])) {
            http_response_code(400); 
            return ["status" => "error", "message" => "Data produk tidak lengkap."];
        }

        try {
            $new_id = $this->productModel->createProduct(
                $data['name'], 
                $data['price'], 
                (int)$data['stock']
            );
            
            http_response_code(201);
            return ["status" => "success", "message" => "Produk berhasil ditambahkan.", "product_id" => $new_id];
        } catch (Exception $e) {
            http_response_code(500);
            return ["status" => "error", "message" => "Gagal menambahkan produk: " . $e->getMessage()];
        }
    }
    
    public function handlePutRequest() {
        // A. Ambil ID dari query string
        $product_id = isset($_GET['id']) ? (int)$_GET['id'] : null;

        // B. Ambil data dari body permintaan (untuk PUT)
        parse_str(file_get_contents("php://input"), $data); // Parsing data PUT
        
        if (!$product_id) {
            http_response_code(400); 
            return ["status" => "error", "message" => "ID produk harus disediakan untuk update."];
        }

        if (empty($data)) {
            http_response_code(400); 
            return ["status" => "error", "message" => "Tidak ada data yang dikirim untuk update."];
        }

        try {
            $affected_rows = $this->productModel->updateProduct($product_id, $data);
            
            if ($affected_rows > 0) {
                http_response_code(200);
                return ["status" => "success", "message" => "Produk ID $product_id berhasil diperbarui."];
            } else {
                http_response_code(404);
                return ["status" => "error", "message" => "Produk ID $product_id tidak ditemukan atau tidak ada perubahan data."];
            }
        } catch (Exception $e) {
            http_response_code(500);
            return ["status" => "error", "message" => "Gagal memperbarui produk: " . $e->getMessage()];
        }
    }

    // Menangani DELETE /products?id=X
    public function handleDeleteRequest() {
        // A. Ambil ID dari query string
        $product_id = isset($_GET['id']) ? (int)$_GET['id'] : null;

        if (!$product_id) {
            http_response_code(400); 
            return ["status" => "error", "message" => "ID produk harus disediakan untuk delete."];
        }

        try {
            $affected_rows = $this->productModel->deleteProduct($product_id);
            
            if ($affected_rows > 0) {
                http_response_code(200);
                return ["status" => "success", "message" => "Produk ID $product_id berhasil dihapus."];
            } else {
                http_response_code(404);
                return ["status" => "error", "message" => "Produk ID $product_id tidak ditemukan."];
            }
        } catch (Exception $e) {
            http_response_code(500);
            return ["status" => "error", "message" => "Gagal menghapus produk: " . $e->getMessage()];
        }
    }
}
?>