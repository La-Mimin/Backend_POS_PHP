<?php
require_once __DIR__ . '/../models/SaleModel.php';

class SaleController {
    private $saleModel;

    public function __construct($conn) {
        $this->saleModel = new SaleModel($conn);
    }

    // Menangani POST /sales
    public function handlePostRequest() {
        $data = json_decode(file_get_contents('php://input'), true);

        // Validasi
        if (!isset($data['payment_method']) || !isset($data['items']) || empty($data['items'])) {
            http_response_code(400);
            return ["status" => "error", "message" => "Data transaksi tidak lengkap."];
        }

        try {
            $result = $this->saleModel->recordSale($data['payment_method'], $data['items']);
            
            http_response_code(201);
            return [
                "status" => "success", 
                "message" => "Transaksi berhasil dicatat.",
                "sale_id" => $result['sale_id'],
                "total" => $result['total_amount']
            ];
            
        } catch (Exception $e) {
            http_response_code(500);
            return ["status" => "error", "message" => "Transaksi gagal: " . $e->getMessage()];
        }
    }
}
?>