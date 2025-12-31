<?php 

require_once __DIR__ . "/../models/Logger.php";

class LogController {
    private $logger;
    
    public function __construct($conn){
        $this->logger = new Logger($conn);
    }

    public function handleGetRequest() {
        $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
        $limit = 20;
        $offset = ($page - 1) * $limit;

        try {
            $logs = $this->logger->getLogs($limit, $offset);

            return [
                "status" => "success",
                "page" => $page,
                "data" => $logs
            ];
        } catch (Exception $e) {
            http_response_code(500);
            return ["status" => "error", "message" => $e->getMessage()];
        }
    }
}