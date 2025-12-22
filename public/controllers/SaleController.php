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

        // Validation
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

    public function handleReceiptRequest($sale_id) {
        $data = $this->saleModel->getSaleReceiptData($sale_id);
        if (!$data) {
            http_response_code(404);
            return [
                "status" => "error",
                "message" => "Transaksi tidak ditemukan."
            ];
        }

        $h = $data['header'];
        $items = $data['items'];
        $width = 32;

        $out = str_pad("TOKO KELONTONG MODERN", $width, " ", STR_PAD_BOTH) . "\n";
        $out .= str_pad("Jl. Digital No. 101", $width, " ", STR_PAD_BOTH) . "\n";
        $out .= str_repeat("=", $width) . "\n";

        $out .= "ID   : " . $h['id'] . "\n";
        $out .= "Tgl  : " . $h['sales_date'] . "\n";
        $out .= "By   : " . ($GLOBALS['user_data']['username'] ?? 'kasir') . "\n";
        $out .= str_repeat("-", $width) . "\n";

        foreach ($items as $item) {
            $out .= substr($item['name'], 0, $width) . "\n";
            $detail = "  " . $item['qty'] . " x " . number_format($item['price']);
            $subtotal = number_format($item['subtotal']);
            $out .= $detail . str_pad($subtotal, $width - strlen($detail), " ", STR_PAD_LEFT) . "\n";
        }

        $out .= str_repeat("-", $width) . "\n";
        $totalLabel = "TOTAL: ";
        $totalVal = "Rp " . number_format($h['total_amount']);
        $out .= $totalLabel . str_pad($totalVal, $width - strlen($totalLabel), " ", STR_PAD_LEFT) . "\n";
        $out .= str_repeat("-", $width) . "\n";
        $out .= str_pad("METODE: " . strtoupper($h['payment_method']), $width, " ", STR_PAD_BOTH) . "\n\n";
        $out .= str_pad("Terima kasih atas", $width, " ", STR_PAD_BOTH) . "\n";
        $out .= str_pad("kunjungan Anda", $width, " ", STR_PAD_BOTH). "\n";

        header('Content-Type: text/plain');
        echo $out;
        exit;
    }
}
?>