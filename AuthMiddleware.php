<?php
require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../utils/ResponseHandler.php';

class AuthMiddleware {
    public static function authenticate() {
        $headers = getallheaders();
        $token = null;
        
        // Get token from Authorization header
        if (isset($headers['Authorization'])) {
            $authHeader = $headers['Authorization'];
            if (preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
                $token = $matches[1];
            }
        }
        
        // Get token from query parameter (for testing)
        if (!$token && isset($_GET['token'])) {
            $token = $_GET['token'];
        }
        
        if (!$token) {
            ResponseHandler::sendUnauthorized('Authentication token required');
        }
        
        $userData = User::validateJWT($token);
        
        if (!$userData) {
            ResponseHandler::sendUnauthorized('Invalid or expired token');
        }
        
        return $userData;
    }
    
    public static function optionalAuthenticate() {
        $headers = getallheaders();
        $token = null;
        
        // Get token from Authorization header
        if (isset($headers['Authorization'])) {
            $authHeader = $headers['Authorization'];
            if (preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
                $token = $matches[1];
            }
        }
        
        if ($token) {
            $userData = User::validateJWT($token);
            if ($userData) {
                return $userData;
            }
        }
        
        return null;
    }
}
?>