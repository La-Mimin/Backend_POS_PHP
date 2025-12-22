<?php

class PurchaseModel {
    private $conn;

    public function __construct($db_conn) {
        $this->conn = $db_conn;
    }

    public function recordPurchase($supplier, $purchase_date, $items) {
        if (empty($items)) {
            throw new Exception("Tidak ada item untuk dicatat.");
        }

        $this->conn->begin_transaction();

        try {
            $total_cost = 0;
            foreach ($items as $item) {
                $total_cost += $item['cost_price'] * $item['quantity'];
            }

            // 1. Simpan Header Pembelian
            $sql = "INSERT INTO purchases (supplier_name, total_cost, purchase_date) VALUES (?, ?, ?)";
            $stmt = $this->conn->prepare($sql);
            $stmt->bind_param("sds", $supplier, $total_cost, $purchase_date);
            $stmt->execute();
            $purchase_id = $this->conn->insert_id;

            // 2. Siapkan Statement
            $sqlItem = "INSERT INTO purchase_items (purchase_id, product_id, quantity, cost_price) VALUES (?, ?, ?, ?)";
            $stmtItem = $this->conn->prepare($sqlItem);
            
            $sqlGetProd = "SELECT stock, cost_price FROM products WHERE id = ?";
            $stmtGetProd = $this->conn->prepare($sqlGetProd);

            $sqlUpdateProd = "UPDATE products SET stock = ?, cost_price = ? WHERE id = ?";
            $stmtUpdateProd = $this->conn->prepare($sqlUpdateProd);

            foreach ($items as $item) {
                $product_id = $item['product_id'];
                $q_new = $item['quantity'];
                $c_new = $item['cost_price'];

                // --- LOGIKA MOVING AVERAGE ---
                
                // A. Ambil stok dan harga modal saat ini
                $stmtGetProd->bind_param("i", $product_id);
                $stmtGetProd->execute();
                $res = $stmtGetProd->get_result()->fetch_assoc();
                
                $q_old = $res['stock'] ?? 0;
                $c_old = $res['cost_price'] ?? 0;

                // B. Hitung rata-rata tertimbang
                $total_qty = $q_old + $q_new;
                
                // Jika stok lama kosong atau minus, gunakan harga baru sepenuhnya
                if ($total_qty > 0 && $q_old > 0) {
                    $average_cost = (($q_old * $c_old) + ($q_new * $c_new)) / $total_qty;
                } else {
                    $average_cost = $c_new;
                }

                // C. Simpan rincian pembelian (tetap pakai harga asli dari supplier untuk audit)
                $stmtItem->bind_param("iiid", $purchase_id, $product_id, $q_new, $c_new);
                $stmtItem->execute();

                // D. Update produk dengan stok baru dan HARGA RATA-RATA
                $stmtUpdateProd->bind_param("idi", $total_qty, $average_cost, $product_id);
                $stmtUpdateProd->execute();                
            }

            $this->conn->commit();
            return [
                'purchase_id' => $purchase_id,
                'total_cost' => $total_cost
            ];
        } catch (Exception $e) {
            $this->conn->rollback();
            throw $e;
        }
    }
}