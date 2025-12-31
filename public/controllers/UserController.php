<?php 

require_once __DIR__ . '/../models/UserModel.php';
require_once __DIR__ . '/../models/Logger.php';

class UserController {
    private $userModel;
    private $logger;
    private $secretKey = 's9F2kL!aP#xR7mQeV@4ZC1D8Wn0B$yT';    

    public function __construct($conn) {
        $this->userModel = new UserModel($conn);
        $this->logger = new Logger($conn);

    }

    private function generateJWT($data) {
        $header = base64_encode(json_encode(['typ' => 'JWT', 'alg' => 'HS256']));

        $payload = array_merge($data, ['exp' => time() + (3600 * 24)]);
        $payload_encoded = base64_encode(json_encode($payload));

        $signature = hash_hmac('sha256', "$header.$payload_encoded", $this->secretKey, true);

        //$signature_encoded = base64_encode($signature);
        $signature_encoded = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($signature));

        return "$header.$payload_encoded.$signature_encoded";
    }

    public function verifyJWT($token) {
        if (empty($token)) {
            return false;
        }

        @list($header_encoded, $payload_encoded, $signature_encoded) = explode('.', $token);

        if (!$header_encoded || !$payload_encoded || !$signature_encoded) {
            return false;
        }

        $expected_signature = hash_hmac('sha256', "$header_encoded.$payload_encoded", $this->secretKey, true);
        $expected_signature_encoded = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($expected_signature));

        if ($signature_encoded !== $expected_signature_encoded) {
            return false;
        }

        $payload_cleaned = str_replace(['-', '_'], ['+', '/'], $payload_encoded);
        $payload_json = base64_decode($payload_cleaned);
        $payload = json_decode($payload_json, true);

        if (!isset($payload['exp']) || $payload['exp'] < time()) {
            return false;
        }

        return $payload;
    }

    public function handleLoginRequest() {
        $data = json_decode(file_get_contents('php://input'), true);

        if (!isset($data['username']) || !isset($data['password'])) {
            http_response_code(400);
            return [
                "status" => "error",
                "message" => "Username dan password diperlukan."
            ];
        }

        try {
            $user = $this->userModel->findUserByCredentials($data['username'], $data['password']);

            if ($user) {
                $token = $this->generateJWT($user);

                http_response_code(200);
                return [
                    "status" => "success",
                    "message" => "Login berhasil.",
                    "token" => $token
                ];
            } else {
                http_response_code(401);
                return [
                    "status" => "error",
                    "message" => "username dan password salah!"
                ];
            }

            $this->logger->log("USER_LOGIN", "User " . $user['username'] . " berhasil login.");
        } catch (Exception $e) {
            http_response_code(500);
            return ["status" => "error", "message" => "Gagal proses login: " . $e->getMessage()];
        }
    }
}