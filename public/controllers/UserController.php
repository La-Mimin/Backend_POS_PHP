<?php 

require_once __DIR__ . '/../models/UserModel.php';

class UserController {
    private $userModel;
    private $secretKey = 's9F2kL!aP#xR7mQeV@4ZC1D8Wn0B$yT';

    public function __construct($conn) {
        $this->userModel = new UserModel($conn);
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
        } catch (Exception $e) {
            http_response_code(500);
            return ["status" => "error", "message" => "Gagal proses login: " . $e->getMessage()];
        }
    }
}