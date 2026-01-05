<?php
require_once __DIR__ . '/../models/Market.php';
require_once __DIR__ . '/../utils/ResponseHandler.php';
require_once __DIR__ . '/../middleware/AuthMiddleware.php';

class MarketController {
    public function getCurrentPrices() {
        try {
            AuthMiddleware::authenticate();
            
            $data = json_decode(file_get_contents('php://input'), true) ?? [];
            
            $marketModel = new Market();
            $prices = $marketModel->getCurrentPrices(
                $data['crop_id'] ?? null,
                $data['market_id'] ?? null,
                $data['limit'] ?? 50
            );
            
            ResponseHandler::sendResponse($prices);
            
        } catch (Exception $e) {
            ResponseHandler::sendError($e->getMessage());
        }
    }
    
    public function getPriceHistory() {
        try {
            AuthMiddleware::authenticate();
            
            $data = json_decode(file_get_contents('php://input'), true);
            
            if (empty($data['crop_id'])) {
                ResponseHandler::sendValidationError(['crop_id is required']);
            }
            
            $marketModel = new Market();
            $history = $marketModel->getPriceHistory(
                $data['crop_id'],
                $data['market_id'] ?? null,
                $data['days'] ?? 30
            );
            
            ResponseHandler::sendResponse($history);
            
        } catch (Exception $e) {
            ResponseHandler::sendError($e->getMessage());
        }
    }
    
    public function getPriceTrends() {
        try {
            AuthMiddleware::authenticate();
            
            $data = json_decode(file_get_contents('php://input'), true);
            
            if (empty($data['crop_id'])) {
                ResponseHandler::sendValidationError(['crop_id is required']);
            }
            
            $marketModel = new Market();
            $trends = $marketModel->getPriceTrends(
                $data['crop_id'],
                $data['days'] ?? 7
            );
            
            ResponseHandler::sendResponse($trends);
            
        } catch (Exception $e) {
            ResponseHandler::sendError($e->getMessage());
        }
    }
    
    public function getMarkets() {
        try {
            $user = AuthMiddleware::authenticate();
            
            $data = json_decode(file_get_contents('php://input'), true) ?? [];
            
            $marketModel = new Market();
            
            if (isset($data['latitude']) && isset($data['longitude'])) {
                // Get nearby markets
                $markets = $marketModel->getNearbyMarkets(
                    $data['latitude'],
                    $data['longitude'],
                    $data['radius_km'] ?? 50
                );
            } else {
                // Get all markets
                $markets = $marketModel->getAllMarkets();
            }
            
            ResponseHandler::sendResponse($markets);
            
        } catch (Exception $e) {
            ResponseHandler::sendError($e->getMessage());
        }
    }
    
    public function getPriceAlerts() {
        try {
            $user = AuthMiddleware::authenticate();
            
            $marketModel = new Market();
            $alerts = $marketModel->getMarketPriceAlerts($user['farmer_id']);
            
            ResponseHandler::sendResponse($alerts);
            
        } catch (Exception $e) {
            ResponseHandler::sendError($e->getMessage());
        }
    }
    
    public function createPriceAlert() {
        try {
            $user = AuthMiddleware::authenticate();
            $data = json_decode(file_get_contents('php://input'), true);
            
            $required = ['crop_id', 'alert_type', 'threshold_price'];
            foreach ($required as $field) {
                if (empty($data[$field])) {
                    ResponseHandler::sendValidationError(["$field is required"]);
                }
            }
            
            $marketModel = new Market();
            $created = $marketModel->createPriceAlert(
                $user['farmer_id'],
                $data['crop_id'],
                $data['alert_type'],
                $data['threshold_price'],
                $data['market_id'] ?? null
            );
            
            if ($created) {
                ResponseHandler::sendResponse(['message' => 'Price alert created'], API_CREATED);
            } else {
                ResponseHandler::sendError('Failed to create price alert');
            }
            
        } catch (Exception $e) {
            ResponseHandler::sendError($e->getMessage());
        }
    }
    
    public function updatePriceAlert() {
        try {
            $user = AuthMiddleware::authenticate();
            $data = json_decode(file_get_contents('php://input'), true);
            
            if (empty($data['alert_id'])) {
                ResponseHandler::sendValidationError(['alert_id is required']);
            }
            
            $marketModel = new Market();
            $updated = $marketModel->updatePriceAlert($data['alert_id'], $data);
            
            if ($updated) {
                ResponseHandler::sendResponse(['message' => 'Price alert updated']);
            } else {
                ResponseHandler::sendError('Failed to update price alert');
            }
            
        } catch (Exception $e) {
            ResponseHandler::sendError($e->getMessage());
        }
    }
    
    public function deletePriceAlert() {
        try {
            $user = AuthMiddleware::authenticate();
            $data = json_decode(file_get_contents('php://input'), true);
            
            if (empty($data['alert_id'])) {
                ResponseHandler::sendValidationError(['alert_id is required']);
            }
            
            $marketModel = new Market();
            $deleted = $marketModel->deletePriceAlert($data['alert_id'], $user['farmer_id']);
            
            if ($deleted) {
                ResponseHandler::sendResponse(['message' => 'Price alert deleted']);
            } else {
                ResponseHandler::sendError('Failed to delete price alert');
            }
            
        } catch (Exception $e) {
            ResponseHandler::sendError($e->getMessage());
        }
    }
}
?>