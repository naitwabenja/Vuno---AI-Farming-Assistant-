<?php
require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/../utils/Validator.php';

class User {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }
    
    public function register($phone, $password, $fullName, $email = null) {
        // Validate inputs
        if (!Validator::validatePhoneNumber($phone)) {
            throw new Exception('Invalid phone number format');
        }
        
        if (!Validator::validatePassword($password)) {
            throw new Exception('Password must be at least 8 characters with uppercase, lowercase and number');
        }
        
        if ($email && !Validator::validateEmail($email)) {
            throw new Exception('Invalid email format');
        }
        
        // Check if user exists
        $stmt = $this->db->prepare("SELECT user_id FROM users WHERE phone_number = ?");
        $stmt->execute([$phone]);
        
        if ($stmt->fetch()) {
            throw new Exception('Phone number already registered');
        }
        
        // Hash password
        $passwordHash = password_hash($password, PASSWORD_DEFAULT);
        
        // Generate verification code
        $verificationCode = rand(100000, 999999);
        
        // Insert user
        $stmt = $this->db->prepare("
            INSERT INTO users (phone_number, password_hash, full_name, email, verification_code, created_at) 
            VALUES (?, ?, ?, ?, ?, NOW())
        ");
        
        $stmt->execute([$phone, $passwordHash, $fullName, $email, $verificationCode]);
        
        $userId = $this->db->lastInsertId();
        
        // Create farmer record
        $stmt = $this->db->prepare("
            INSERT INTO farmers (user_id, created_at) 
            VALUES (?, NOW())
        ");
        
        $stmt->execute([$userId]);
        
        return [
            'user_id' => $userId,
            'farmer_id' => $this->db->lastInsertId(),
            'verification_code' => $verificationCode
        ];
    }
    
    public function login($phone, $password) {
        $stmt = $this->db->prepare("
            SELECT u.*, f.farmer_id 
            FROM users u 
            LEFT JOIN farmers f ON u.user_id = f.user_id 
            WHERE u.phone_number = ? AND u.is_active = TRUE
        ");
        
        $stmt->execute([$phone]);
        $user = $stmt->fetch();
        
        if (!$user) {
            throw new Exception('User not found');
        }
        
        if (!password_verify($password, $user['password_hash'])) {
            throw new Exception('Invalid credentials');
        }
        
        if (!$user['is_verified']) {
            throw new Exception('Account not verified. Please verify your phone number.');
        }
        
        // Update last login
        $stmt = $this->db->prepare("UPDATE users SET last_login = NOW() WHERE user_id = ?");
        $stmt->execute([$user['user_id']]);
        
        // Generate JWT token
        $token = $this->generateJWT($user);
        
        // Remove sensitive data
        unset($user['password_hash']);
        unset($user['verification_code']);
        
        return [
            'user' => $user,
            'token' => $token
        ];
    }
    
    public function verifyPhone($phone, $code) {
        $stmt = $this->db->prepare("
            SELECT user_id FROM users 
            WHERE phone_number = ? AND verification_code = ? AND is_verified = FALSE
        ");
        
        $stmt->execute([$phone, $code]);
        $user = $stmt->fetch();
        
        if (!$user) {
            throw new Exception('Invalid verification code or already verified');
        }
        
        $stmt = $this->db->prepare("
            UPDATE users 
            SET is_verified = TRUE, verification_code = NULL 
            WHERE user_id = ?
        ");
        
        return $stmt->execute([$user['user_id']]);
    }
    
    public function requestPasswordReset($phone) {
        $stmt = $this->db->prepare("SELECT user_id FROM users WHERE phone_number = ? AND is_active = TRUE");
        $stmt->execute([$phone]);
        $user = $stmt->fetch();
        
        if (!$user) {
            return true; // Don't reveal if user exists
        }
        
        $resetCode = rand(100000, 999999);
        $expiry = date('Y-m-d H:i:s', strtotime('+1 hour'));
        
        $stmt = $this->db->prepare("
            UPDATE users 
            SET verification_code = ?, verification_expiry = ? 
            WHERE user_id = ?
        ");
        
        $stmt->execute([$resetCode, $expiry, $user['user_id']]);
        
        return $resetCode;
    }
    
    public function resetPassword($phone, $code, $newPassword) {
        if (!Validator::validatePassword($newPassword)) {
            throw new Exception('Password must be at least 8 characters with uppercase, lowercase and number');
        }
        
        $stmt = $this->db->prepare("
            SELECT user_id FROM users 
            WHERE phone_number = ? AND verification_code = ? 
            AND verification_expiry > NOW() AND is_active = TRUE
        ");
        
        $stmt->execute([$phone, $code]);
        $user = $stmt->fetch();
        
        if (!$user) {
            throw new Exception('Invalid or expired reset code');
        }
        
        $passwordHash = password_hash($newPassword, PASSWORD_DEFAULT);
        
        $stmt = $this->db->prepare("
            UPDATE users 
            SET password_hash = ?, verification_code = NULL, verification_expiry = NULL 
            WHERE user_id = ?
        ");
        
        return $stmt->execute([$passwordHash, $user['user_id']]);
    }
    
    public function updateProfile($userId, $data) {
        $allowedFields = ['full_name', 'email', 'language_preference'];
        $updates = [];
        $params = [];
        
        foreach ($data as $key => $value) {
            if (in_array($key, $allowedFields)) {
                $updates[] = "$key = ?";
                $params[] = Validator::sanitizeInput($value);
            }
        }
        
        if (empty($updates)) {
            return false;
        }
        
        $params[] = $userId;
        $sql = "UPDATE users SET " . implode(', ', $updates) . " WHERE user_id = ?";
        
        $stmt = $this->db->prepare($sql);
        return $stmt->execute($params);
    }
    
    private function generateJWT($user) {
        $header = json_encode(['typ' => 'JWT', 'alg' => 'HS256']);
        $payload = json_encode([
            'user_id' => $user['user_id'],
            'farmer_id' => $user['farmer_id'],
            'phone' => $user['phone_number'],
            'exp' => time() + JWT_EXPIRY
        ]);
        
        $base64Header = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($header));
        $base64Payload = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($payload));
        
        $signature = hash_hmac('sha256', "$base64Header.$base64Payload", JWT_SECRET, true);
        $base64Signature = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($signature));
        
        return "$base64Header.$base64Payload.$base64Signature";
    }
    
    public static function validateJWT($token) {
        $parts = explode('.', $token);
        
        if (count($parts) !== 3) {
            return false;
        }
        
        list($base64Header, $base64Payload, $base64Signature) = $parts;
        
        $signature = hash_hmac('sha256', "$base64Header.$base64Payload", JWT_SECRET, true);
        $calculatedSignature = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($signature));
        
        if ($calculatedSignature !== $base64Signature) {
            return false;
        }
        
        $payload = json_decode(base64_decode($base64Payload), true);
        
        if (isset($payload['exp']) && $payload['exp'] < time()) {
            return false;
        }
        
        return $payload;
    }
}
?>