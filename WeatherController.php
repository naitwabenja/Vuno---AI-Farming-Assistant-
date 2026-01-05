<?php
require_once __DIR__ . '/../models/Weather.php';
require_once __DIR__ . '/../utils/ResponseHandler.php';
require_once __DIR__ . '/../middleware/AuthMiddleware.php';

class WeatherController {
    public function getCurrentWeather() {
        try {
            $user = AuthMiddleware::authenticate();
            
            // Get farmer's region
            $farmerModel = new Farmer();
            $profile = $farmerModel->getProfile($user['farmer_id']);
            
            if (empty($profile['region_id'])) {
                ResponseHandler::sendError('Please set your location first');
            }
            
            $weatherModel = new Weather();
            $weather = $weatherModel->getCurrentWeather($profile['region_id']);
            
            ResponseHandler::sendResponse($weather);
            
        } catch (Exception $e) {
            ResponseHandler::sendError($e->getMessage());
        }
    }
    
    public function getForecast() {
        try {
            $user = AuthMiddleware::authenticate();
            
            $data = json_decode(file_get_contents('php://input'), true);
            $days = $data['days'] ?? 7;
            
            // Get farmer's region
            $farmerModel = new Farmer();
            $profile = $farmerModel->getProfile($user['farmer_id']);
            
            if (empty($profile['region_id'])) {
                ResponseHandler::sendError('Please set your location first');
            }
            
            $weatherModel = new Weather();
            $forecast = $weatherModel->getWeatherForecast($profile['region_id'], $days);
            
            ResponseHandler::sendResponse($forecast);
            
        } catch (Exception $e) {
            ResponseHandler::sendError($e->getMessage());
        }
    }
    
    public function getPlantingAdvice() {
        try {
            $user = AuthMiddleware::authenticate();
            $data = json_decode(file_get_contents('php://input'), true);
            
            if (empty($data['crop_id'])) {
                ResponseHandler::sendValidationError(['crop_id is required']);
            }
            
            // Get farmer's region
            $farmerModel = new Farmer();
            $profile = $farmerModel->getProfile($user['farmer_id']);
            
            if (empty($profile['region_id'])) {
                ResponseHandler::sendError('Please set your location first');
            }
            
            $weatherModel = new Weather();
            $advice = $weatherModel->getPlantingAdvice($profile['region_id'], $data['crop_id']);
            
            ResponseHandler::sendResponse($advice);
            
        } catch (Exception $e) {
            ResponseHandler::sendError($e->getMessage());
        }
    }
    
    public function getWeatherAlerts() {
        try {
            $user = AuthMiddleware::authenticate();
            
            // Get farmer's region
            $farmerModel = new Farmer();
            $profile = $farmerModel->getProfile($user['farmer_id']);
            
            if (empty($profile['region_id'])) {
                ResponseHandler::sendError('Please set your location first');
            }
            
            $weatherModel = new Weather();
            $alerts = $weatherModel->getWeatherAlerts($profile['region_id']);
            
            ResponseHandler::sendResponse($alerts);
            
        } catch (Exception $e) {
            ResponseHandler::sendError($e->getMessage());
        }
    }
}
?>