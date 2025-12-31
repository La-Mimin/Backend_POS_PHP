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

}