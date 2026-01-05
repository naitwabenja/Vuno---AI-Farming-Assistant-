<?php
require_once __DIR__ . '/../models/Chat.php';
require_once __DIR__ . '/../utils/ResponseHandler.php';
require_once __DIR__ . '/../middleware/AuthMiddleware.php';

class ChatController {
    public function startSession() {
        try {
            $user = AuthMiddleware::authenticate();
            $data = json_decode(file_get_contents('php://input'), true) ?? [];
            
            $chatModel = new Chat();
            $sessionId = $chatModel->createSession(
                $user['farmer_id'],
                $data['session_type'] ?? 'text',
                $data['language'] ?? 'en'
            );
            
            ResponseHandler::sendResponse([
                'session_id' => $sessionId,
                'message' => 'Chat session started'
            ], API_CREATED);
            
        } catch (Exception $e) {
            ResponseHandler::sendError($e->getMessage());
        }
    }
    
    public function sendMessage() {
        try {
            $user = AuthMiddleware::authenticate();
            $data = json_decode(file_get_contents('php://input'), true);
            
            if (empty($data['session_id']) || empty($data['message'])) {
                ResponseHandler::sendValidationError(['session_id and message are required']);
            }
            
            $chatModel = new Chat();
            $result = $chatModel->sendMessage(
                $data['session_id'],
                $data['message'],
                $data['message_type'] ?? 'text',
                $user['farmer_id']
            );
            
            ResponseHandler::sendResponse($result, API_CREATED);
            
        } catch (Exception $e) {
            ResponseHandler::sendError($e->getMessage());
        }
    }
    
    public function getChatHistory() {
        try {
            $user = AuthMiddleware::authenticate();
            
            $data = json_decode(file_get_contents('php://input'), true) ?? [];
            
            $chatModel = new Chat();
            $history = $chatModel->getChatHistory(
                $user['farmer_id'],
                $data['limit'] ?? 20
            );
            
            ResponseHandler::sendResponse($history);
            
        } catch (Exception $e) {
            ResponseHandler::sendError($e->getMessage());
        }
    }
    
    public function getSessionMessages() {
        try {
            $user = AuthMiddleware::authenticate();
            $data = json_decode(file_get_contents('php://input'), true);
            
            if (empty($data['session_id'])) {
                ResponseHandler::sendValidationError(['session_id is required']);
            }
            
            $chatModel = new Chat();
            $messages = $chatModel->getSessionMessages(
                $data['session_id'],
                $user['farmer_id']
            );
            
            ResponseHandler::sendResponse($messages);
            
        } catch (Exception $e) {
            ResponseHandler::sendError($e->getMessage());
        }
    }
    
    public function getQuickQuestions() {
        try {
            AuthMiddleware::authenticate();
            
            $data = json_decode(file_get_contents('php://input'), true) ?? [];
            
            $chatModel = new Chat();
            $questions = $chatModel->getQuickQuestions(
                $data['language'] ?? 'en',
                $data['category'] ?? null
            );
            
            ResponseHandler::sendResponse($questions);
            
        } catch (Exception $e) {
            ResponseHandler::sendError($e->getMessage());
        }
    }
}
?>