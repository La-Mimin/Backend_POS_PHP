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

    // Menangani GET /sales/{id}
    public function handleGetDetailRequest($id) {
        $sale_id = (int)$id;

        if (!$sale_id) {
            http_response_code(400);
            return ["status" => "error", "message" => "ID transaksi harus disediakan."];
        }

        try {
            $details = $this->saleModel->getSaleDetails($sale_id);

            if ($details) {
                http_response_code(200);
                // KOREKSI 1: Hapus "message" yang tidak valid
                
                return ["status" => "success", "data" => $details]; 
            } else {
                // KOREKSI 2: Gunakan kode 404 yang benar
                http_response_code(404); 
                return ["status" => "error", "message" => "Transaksi ID $sale_id tidak ditemukan."];
            }
        } catch (Exception $e) {
            http_response_code(500);
            return ["status" => "error", "message" => "Gagal mengambil detail transaksi: " . $e->getMessage()];
        }
    }

    public function handleGetAllRequest() {

        $startDate = $_GET['start_date'] ?? null;
        $endDate = $_GET['end_date'] ?? null;

        try {
            if ($startDate && $endDate) {
                $sales = $this->saleModel->getSalesByDateRange($startDate, $endDate);
                $message = "Menampilkan laporan dari $startDate sampai $endDate.";
            } else {
                $sales = $this->saleModel->getAllSales();
                $message = "Menampilkan semua data penjualan.";
            }

            if ($sales) {
                http_response_code(200);

                return [
                    "status" => "success",
                    "message" => $message,
                    "count" => count($sales),
                    "data" => $sales
                ];
            } else {
                http_response_code(200);

                return [
                    "status" => "success",
                    "message" => "belum ada data transaksi",
                    "data" => []
                ];
            }
        } catch (Exception $e) {
            http_response_code(500);

            return [
                "status" => "error",
                "message" => "Gagal mengambil data transaksi: " . $e->getMessage()
            ];
        }
    }

    public function handleSummaryRequest() {
        try {
            $summary = $this->saleModel->getDailySummary();

            return [
                "status" => "success",
                "message" => "Ringkasan dashboard berhasil dimuat.",
                "date" => date('Y-m-d'),
                "summary" => $summary
            ];
        } catch (Exception $e) {
            http_response_code(500);
            return ["status" => "error", "message" => $e->getMessage()];
        }
    }

    public function handleVoidRequest($sale_id) {
        if(!$sale_id) {
            http_response_code(400);
            return [
                "status" => "error",
                "message" => "ID Transaksi diperlukan."
            ];
        }

        try {
            $this->saleModel->voidSale($sale_id);
        return [
                "status" => "success",
                "message" => "Transaksi ID $sale_id berhasil dibatalkan dan stok telah dikembalikan."
            ];
        } catch (\Throwable $th) {
            http_response_code(400);
            return ["status" => "error", "message" => $e->getMessage()];
        }
    }
}
?>