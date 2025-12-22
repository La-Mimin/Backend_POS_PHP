<?php

require_once __DIR__ . '/../models/PurchaseModel.php';

class PurchaseController {
    private $purchaseModel;

    public function __construct($conn)
    {
        $this->purchaseModel = new PurchaseModel($conn);
    }

    public function handlePostRequest(){
        $data = json_decode(file_get_contents('php://input'), true);

        if (!isset($data['supplier']) || !isset($data['items']) || empty($data['items']) || !isset($data['purchase_date'])) {
            http_response_code(400);
            return [
                "status" => "error",
                "message" => "Data transaksi tidak lengkap."
            ];
        }

        try {
            $result = $this->purchaseModel->recordPurchase($data['supplier'], $data['purchase_date'], $data['items']);

            http_response_code(201);
            return [
                "status" => "success",
                "message" => "transaksi berhasil dicatat. Stock berhasil di update.",
                "purchase_id" => $result['purchase_id'],
                "total_cost" => $result['total_cost']
            ];
        } catch (Exception $e) {
            http_response_code(500);
            return [
                "status" => "error",
                "message" => "Transaksi gagal: " . $e->getMessage()
            ];
        }
    }
}