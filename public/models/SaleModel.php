<?php
class SaleModel {
    private $conn;

    public function __construct($db_conn) {
        $this->conn = $db_conn;
    }

    // Fungsi CREATE: Mencatat transaksi penjualan (menggunakan Transaction)
    public function recordSale($payment_method, $items) {

        if (empty($items)) {
            throw new Exception("Tidak ada item untuk dicatat.");
        }
        
        $this->conn->begin_transaction(); // Mulai Transaksi

        try {

            // products stock check
            $checkSql = "SELECT stock, name, price FROM products WHERE id = ? FOR UPDATE";
            $stmtCheck = $this->conn->prepare($checkSql);
            
            $total_amount = 0;
            $validated_items = [];

            foreach ($items as $item) {
                $qty = (int)$item['quantity'];
                if ($qty <= 0) {
                    throw new Exception("Kuantitas untuk produk ID " . $item['product_id'] . " harus lebih dari 0.");
                }

                $stmtCheck->bind_param("i", $item['product_id']);
                $stmtCheck->execute();
                $result = $stmtCheck->get_result();
                $product = $result->fetch_assoc();

                if (!$product) {
                    throw new Exception("Produk ID " . $item['product_id'] . " tidak ditemukan.");
                }

                if ($product['stock'] < $qty) {
                    throw new Exception("Stok tidak cukup untuk: " . $product['name'] . " (Sisa: " . $product['stock'] . ")");
                }


                $total_amount += $item['quantity'] * $item['price_at_sale'];

                $validated_items[] = [
                    'product_id' => $item['product_id'],
                    'quantity' => $qty,
                    'price' => $item['price']
                ];
            }
            
            $stmtCheck->close();

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

            foreach ($validated_items as $item) {
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

    public function getSalesByDateRange($startDate = null, $endDate = null) {
        $sql = "SELECT id, total_amount, payment_method, sales_date 
            FROM sales 
            WHERE DATE(sales_date) BETWEEN ? AND ? 
            ORDER BY sales_date DESC";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("ss", $startDate, $endDate);
        $stmt->execute();
        $result = $stmt->get_result();

        $sales = [];

        while ($row = $result->fetch_assoc()) {
            $sales[] = $row;
        }

        $stmt->close();
        return $sales;
    }

    public function getDailySummary() {
        $summary = [];

        $sql = "SELECT 
                SUM(total_amount) as total_revenue, 
                COUNT(id) as total_transactions 
            FROM sales 
            WHERE DATE(sales_date) = CURDATE()";
        
        $result = $this->conn->query($sql);
        $data = $result->fetch_assoc();

        $summary['total_revenue'] = $data['total_revenue'] ?? 0;
        $summary['total_transactions'] = $data['total_transactions'] ?? 0;

        $sqlPay = "SELECT payment_method, COUNT(*) as count 
               FROM sales 
               WHERE DATE(sales_date) = CURDATE() 
               GROUP BY payment_method";

        $resPay = $this->conn->query($sqlPay);
        $payments = [];
        while ($row = $resPay->fetch_assoc()) {
            $payments[$row['payment_method']] = $row['count'];
        }

        $summary['payment_methods'] = $payments;

        return $summary;
    }

    public function voidSale($sale_id) {
        $this->conn->begin_transaction();

        try {
            $sqlCheck =  "SELECT status FROM sales WHERE id = ? FOR UPDATE";
            $stmtCheck = $this->conn->prepare($sqlCheck);
            $stmtCheck->bind_param("i", $sale_id);
            $stmtCheck->execute();
            $res = $stmtCheck->get_result()->fetch_assoc();

            if (!$res) throw new Exception("Transaksi tidak ditemukan.");
            if ($res['status'] === 'voided') throw new Exception("Transaksi ini sudah dibatalkan sebelumnya.");

            $sqlItems = "SELECT product_id, quantity FROM sale_items WHERE sale_id = ?";
            $stmtItems = $this->conn->prepare($sqlItems);
            $stmtItems->bind_param("i", $sale_id);
            $stmtItems->execute();
            $items = $stmtItems->get_result();

            $sqlUpdateStock = "UPDATE products SET stock = stock + ? WHERE id = ?";
            $stmtUpdateStock = $this->conn->prepare($sqlUpdateStock);

            while ($row = $items->fetch_assoc()) {
                $stmtUpdateStock->bind_param("ii", $row['quantity'], $row['product_id']);
                $stmtUpdateStock->execute();
            }

            $sqlVoid = "UPDATE sales SET status = 'voided' WHERE id = ?";
            $stmtVoid = $this->conn->prepare($sqlVoid);
            $stmtVoid->bind_param("i", $sale_id);
            $stmtVoid->execute();

            $this->conn->commit();
            return true;
        } catch (Exception $e) {
            $this->conn->rollback();
            throw $e;
        }
    }
}
?>