<?php
require_once __DIR__ . '/../config/constants.php';

class ResponseHandler {
    public static function sendResponse($data = null, $statusCode = API_SUCCESS, $message = 'Success') {
        http_response_code($statusCode);
        
        $response = [
            'success' => $statusCode < 400,
            'message' => $message,
            'timestamp' => time(),
            'data' => $data
        ];
        
        header('Content-Type: application/json');
        echo json_encode($response);
        exit;
    }
    
    public static function sendError($message = 'An error occurred', $statusCode = API_ERROR, $errors = []) {
        self::sendResponse($errors, $statusCode, $message);
    }
    
    public static function sendValidationError($errors) {
        self::sendError('Validation failed', API_BAD_REQUEST, $errors);
    }
    
    public static function sendNotFound($resource = 'Resource') {
        self::sendError("$resource not found", API_NOT_FOUND);
    }
    
    public static function sendUnauthorized($message = 'Authentication required') {
        self::sendError($message, API_UNAUTHORIZED);
    }
    
    public static function sendForbidden($message = 'Access denied') {
        self::sendError($message, API_FORBIDDEN);
    }
}
?>