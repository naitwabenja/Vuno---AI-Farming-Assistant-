<?php
require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../utils/ResponseHandler.php';
require_once __DIR__ . '/../utils/SMSHandler.php';
require_once __DIR__ . '/../middleware/AuthMiddleware.php';

class AuthController {
    public function register() {
        try {
            $data = json_decode(file_get_contents('php://input'), true);
            
            // Validate required fields
            $required = ['phone', 'password', 'full_name'];
            foreach ($required as $field) {
                if (empty($data[$field])) {
                    ResponseHandler::sendValidationError(["$field is required"]);
                }
            }
            
            $userModel = new User();
            $result = $userModel->register(
                $data['phone'],
                $data['password'],
                $data['full_name'],
                $data['email'] ?? null
            );
            
            // Send verification SMS
            $smsResult = SMSHandler::sendOTP($data['phone']);
            
            ResponseHandler::sendResponse([
                'user_id' => $result['user_id'],
                'farmer_id' => $result['farmer_id'],
                'verification_sent' => $smsResult['success'],
                'message' => 'Registration successful. Please verify your phone number.'
            ], API_CREATED);
            
        } catch (Exception $e) {
            ResponseHandler::sendError($e->getMessage());
        }
    }
    
    public function verify() {
        try {
            $data = json_decode(file_get_contents('php://input'), true);
            
            if (empty($data['phone']) || empty($data['code'])) {
                ResponseHandler::sendValidationError(["phone and code are required"]);
            }
            
            $userModel = new User();
            $verified = $userModel->verifyPhone($data['phone'], $data['code']);
            
            if ($verified) {
                ResponseHandler::sendResponse(['message' => 'Phone verified successfully']);
            } else {
                ResponseHandler::sendError('Verification failed');
            }
            
        } catch (Exception $e) {
            ResponseHandler::sendError($e->getMessage());
        }
    }
    
    public function login() {
        try {
            $data = json_decode(file_get_contents('php://input'), true);
            
            if (empty($data['phone']) || empty($data['password'])) {
                ResponseHandler::sendValidationError(["phone and password are required"]);
            }
            
            $userModel = new User();
            $result = $userModel->login($data['phone'], $data['password']);
            
            ResponseHandler::sendResponse($result);
            
        } catch (Exception $e) {
            ResponseHandler::sendError($e->getMessage(), API_UNAUTHORIZED);
        }
    }
    
    public function forgotPassword() {
        try {
            $data = json_decode(file_get_contents('php://input'), true);
            
            if (empty($data['phone'])) {
                ResponseHandler::sendValidationError(["phone is required"]);
            }
            
            $userModel = new User();
            $resetCode = $userModel->requestPasswordReset($data['phone']);
            
            // Send SMS with reset code
            SMSHandler::sendSMS($data['phone'], 
                "Your Vuno password reset code is: $resetCode. Valid for 1 hour.");
            
            ResponseHandler::sendResponse(['message' => 'Reset code sent to your phone']);
            
        } catch (Exception $e) {
            ResponseHandler::sendError($e->getMessage());
        }
    }
    
    public function resetPassword() {
        try {
            $data = json_decode(file_get_contents('php://input'), true);
            
            $required = ['phone', 'code', 'new_password'];
            foreach ($required as $field) {
                if (empty($data[$field])) {
                    ResponseHandler::sendValidationError(["$field is required"]);
                }
            }
            
            $userModel = new User();
            $reset = $userModel->resetPassword(
                $data['phone'],
                $data['code'],
                $data['new_password']
            );
            
            if ($reset) {
                ResponseHandler::sendResponse(['message' => 'Password reset successful']);
            } else {
                ResponseHandler::sendError('Password reset failed');
            }
            
        } catch (Exception $e) {
            ResponseHandler::sendError($e->getMessage());
        }
    }
    
    public function profile() {
        try {
            $user = AuthMiddleware::authenticate();
            
            $userModel = new User();
            $profile = $userModel->getProfile($user['user_id']);
            
            ResponseHandler::sendResponse($profile);
            
        } catch (Exception $e) {
            ResponseHandler::sendError($e->getMessage(), API_UNAUTHORIZED);
        }
    }
    
    public function updateProfile() {
        try {
            $user = AuthMiddleware::authenticate();
            $data = json_decode(file_get_contents('php://input'), true);
            
            $userModel = new User();
            $updated = $userModel->updateProfile($user['user_id'], $data);
            
            if ($updated) {
                ResponseHandler::sendResponse(['message' => 'Profile updated successfully']);
            } else {
                ResponseHandler::sendError('No changes made');
            }
            
        } catch (Exception $e) {
            ResponseHandler::sendError($e->getMessage());
        }
    }
}
?>