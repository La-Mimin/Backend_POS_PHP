<?php

class Logger {
    private $conn;

    public function __construct($db_conn) {
        $this->conn = $db_conn;
    }

    public function log($action, $description) {
        $user_id = $GLOBALS['user_data']['id'] ?? null;
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

        $sql = "INSERT INTO system_logs (user_id, action, description, ip_address) VALUES (?, ?, ?, ?)";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("isss", $user_id, $action, $description, $ip);
        $stmt->execute();
    }

    public function getLogs($limit = 50, $offset = 0) {
        $sql = "SELECT l.action, u.username, l.description, l.ip_address, l.created_at FROM system_logs l LEFT JOIN users u ON l.user_id = u.id ORDER BY l.created_at DESC LIMIT ? OFFSET ?";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("ii", $limit, $offset);
        $stmt->execute();

        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }
}