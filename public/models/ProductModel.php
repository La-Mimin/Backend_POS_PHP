<?php
class ProductModel {
    private $conn;

    public function __construct($db_conn) {
        $this->conn = $db_conn;
    }

    // Fungsi READ: Mengambil semua produk
    public function getAllProducts() {
        $sql = "SELECT id, name, price, stock FROM products ORDER BY id DESC";
        $result = $this->conn->query($sql);
        $products = [];
        if ($result->num_rows > 0) {
            while($row = $result->fetch_assoc()) {
                $products[] = $row;
            }
        }
        return $products;
    }

    // Fungsi CREATE: Menambah produk baru (menggunakan Prepared Statement)
    public function createProduct($name, $price, $stock) {
        $sql = "INSERT INTO products (name, price, stock) VALUES (?, ?, ?)";
        $stmt = $this->conn->prepare($sql);
        if ($stmt === false) {
             throw new Exception("Gagal menyiapkan query CREATE: " . $this->conn->error);
        }
        $stmt->bind_param("sdi", $name, $price, $stock);
        
        if ($stmt->execute()) {
            $new_id = $this->conn->insert_id;
            $stmt->close();
            return $new_id;
        } else {
            $error = $stmt->error;
            $stmt->close();
            throw new Exception("Gagal mengeksekusi CREATE: " . $error);
        }
    }

    public function updateProduct($id, $data) {
        // Membangun query secara dinamis
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
            $params[] = $data['price'];
            $types .= 'd';
        }

        if (isset($data['stock'])) {
            $sets[] = "stock = ?";
            $params[] = (int)$data['stock'];
            $types .= 'i';
        }
        
        if (empty($sets)) {
            // Tidak ada data untuk diupdate
            return 0; 
        }

        // Tambahkan ID produk ke akhir parameter dan types (untuk klausa WHERE)
        $params[] = (int)$id;
        $types .= 'i';

        $sql = "UPDATE products SET " . implode(', ', $sets) . " WHERE id = ?";
        
        $stmt = $this->conn->prepare($sql);
        if ($stmt === false) {
             throw new Exception("Gagal menyiapkan query UPDATE: " . $this->conn->error);
        }

        // Mengikat parameter secara dinamis (menggunakan referensi)
        $bind_names = [$types];
        for ($i = 0; $i < count($params); $i++) {
            $bind_names[] = &$params[$i];
        }
        call_user_func_array([$stmt, 'bind_param'], $bind_names); 
        
        if ($stmt->execute()) {
            $affected = $stmt->affected_rows;
            $stmt->close();
            return $affected; // Mengembalikan jumlah baris yang terpengaruh (updated/deleted)
        } else {
            $error = $stmt->error;
            $stmt->close();
            throw new Exception("Gagal mengeksekusi UPDATE: " . $error);
        }
    }

    // Fungsi DELETE: Menghapus produk (menggunakan Prepared Statement)
    public function deleteProduct($id) {
        $sql = "DELETE FROM products WHERE id = ?";
        $stmt = $this->conn->prepare($sql);
        if ($stmt === false) {
             throw new Exception("Gagal menyiapkan query DELETE: " . $this->conn->error);
        }

        $id = (int)$id;
        $stmt->bind_param("i", $id); // 'i' = integer
        
        if ($stmt->execute()) {
            $affected = $stmt->affected_rows;
            $stmt->close();
            return $affected;
        } else {
            $error = $stmt->error;
            $stmt->close();
            throw new Exception("Gagal mengeksekusi DELETE: " . $error);
        }
    }
}
?>