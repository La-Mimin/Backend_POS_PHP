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

    public function getSaleDetails($id) {
        $sale_id = (int)$id;

        $sql_header = "SELECT id, total_amount, payment_method, sales_date FROM sales WHERE id = ?";
        $stmt_header = $this->conn->prepare($sql_header);
        if ($stmt_header === false) {
            throw new Exception("Prepare header GAGAL: " . $this->conn->error);
        }

        $stmt_header->bind_param("i", $sale_id);
        $stmt_header->execute();
        $result_header = $stmt_header->get_result();
        $header = $result_header->fetch_assoc();
        $stmt_header->close();

        if (!$header) {
            return null;
        }

        $sql_items = "SELECT si.product_id, si.quantity, si.price_at_sale, p.name AS product_name FROM sale_items si join products p ON si.product_id = p.id WHERE si.sale_id = ?";

        $stmt_items = $this->conn->prepare($sql_items);
        if ($stmt_items === false) {
            throw new Exception("Prepare items GAGAL: " . $this->conn->error);
        }
        
        $stmt_items->bind_param("i", $sale_id);
        $stmt_items->execute();
        $result_items = $stmt_items->get_result();
        $items = [];
        while($row = $result_items->fetch_assoc()) {
            $items[] = $row;
        }
        $stmt_items->close();

        if (!$header) {
            return null;
        }

        //die("Debug 1: Header OK. ID: " . $header['id']);
        $header['items'] = $items;

        return $header;
    }

    public function getAllSales() {
        $sql = "SELECT id, total_amount, payment_method, sales_date FROM sales ORDER BY sales_date DESC";

        $result = $this->conn->query($sql);

        $sales = [];

        if ($result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $sales[] = $row;
            }
        }

        return $sales;
    }
}
?>