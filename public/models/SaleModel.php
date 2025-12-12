<?php
class SaleModel {
    private $conn;

    public function __construct($db_conn) {
        $this->conn = $db_conn;
    }

    // Fungsi CREATE: Mencatat transaksi penjualan (menggunakan Transaction)
    public function recordSale($payment_method, $items) {
        
        $this->conn->begin_transaction(); // Mulai Transaksi

        try {
            $total_amount = 0;
            foreach ($items as $item) {
                $total_amount += $item['quantity'] * $item['price_at_sale'];
            }

            // A. INSERT INTO sales (Header)
            $sql_sale = "INSERT INTO sales (total_amount, payment_method) VALUES (?, ?)";
            $stmt_sale = $this->conn->prepare($sql_sale);
            $stmt_sale->bind_param("ds", $total_amount, $payment_method);
            $stmt_sale->execute();
            $sale_id = $this->conn->insert_id;
            $stmt_sale->close();

            // B. INSERT INTO sale_items & UPDATE products (Item Detail dan Stok)
            $sql_item = "INSERT INTO sale_items (sale_id, product_id, quantity, price_at_sale) VALUES (?, ?, ?, ?)";
            $stmt_item = $this->conn->prepare($sql_item);
            
            $sql_stock = "UPDATE products SET stock = stock - ? WHERE id = ?";
            $stmt_stock = $this->conn->prepare($sql_stock);

            foreach ($items as $item) {
                $product_id = (int)$item['product_id'];
                $quantity = (int)$item['quantity'];
                $price_at_sale = $item['price_at_sale'];

                // Insert Item Detail
                $stmt_item->bind_param("iiid", $sale_id, $product_id, $quantity, $price_at_sale);
                $stmt_item->execute();

                // Update Stok Produk
                $stmt_stock->bind_param("ii", $quantity, $product_id);
                $stmt_stock->execute();
            }
            
            $stmt_item->close();
            $stmt_stock->close();
            
            $this->conn->commit(); // Selesai: Semua berhasil
            
            return ['sale_id' => $sale_id, 'total_amount' => $total_amount];

        } catch (Exception $e) {
            $this->conn->rollback(); // Gagal: Batalkan semua
            throw new Exception("Transaksi dibatalkan. Detail: " . $e->getMessage());
        }
    }
}
?>