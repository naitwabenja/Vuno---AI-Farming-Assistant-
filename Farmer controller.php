<?php
require_once __DIR__ . '/../models/Farmer.php';
require_once __DIR__ . '/../models/Crop.php';
require_once __DIR__ . '/../utils/ResponseHandler.php';
require_once __DIR__ . '/../middleware/AuthMiddleware.php';

class FarmerController {
    public function getProfile() {
        try {
            $user = AuthMiddleware::authenticate();
            
            $farmerModel = new Farmer();
            $profile = $farmerModel->getProfile($user['farmer_id']);
            
            if (!$profile) {
                ResponseHandler::sendNotFound('Farmer profile');
            }
            
            ResponseHandler::sendResponse($profile);
            
        } catch (Exception $e) {
            ResponseHandler::sendError($e->getMessage());
        }
    }
    
    public function updateProfile() {
        try {
            $user = AuthMiddleware::authenticate();
            $data = json_decode(file_get_contents('php://input'), true);
            
            $farmerModel = new Farmer();
            $updated = $farmerModel->updateProfile($user['farmer_id'], $data);
            
            if ($updated) {
                ResponseHandler::sendResponse(['message' => 'Farmer profile updated']);
            } else {
                ResponseHandler::sendError('No valid fields to update');
            }
            
        } catch (Exception $e) {
            ResponseHandler::sendError($e->getMessage());
        }
    }
    
    public function updateLocation() {
        try {
            $user = AuthMiddleware::authenticate();
            $data = json_decode(file_get_contents('php://input'), true);
            
            $required = ['region_id', 'specific_location'];
            foreach ($required as $field) {
                if (empty($data[$field])) {
                    ResponseHandler::sendValidationError(["$field is required"]);
                }
            }
            
            $farmerModel = new Farmer();
            $updated = $farmerModel->updateLocation(
                $user['farmer_id'],
                $data['region_id'],
                $data['specific_location'],
                $data['latitude'] ?? null,
                $data['longitude'] ?? null
            );
            
            if ($updated) {
                ResponseHandler::sendResponse(['message' => 'Location updated successfully']);
            } else {
                ResponseHandler::sendError('Failed to update location');
            }
            
        } catch (Exception $e) {
            ResponseHandler::sendError($e->getMessage());
        }
    }
    
    public function addCrop() {
        try {
            $user = AuthMiddleware::authenticate();
            $data = json_decode(file_get_contents('php://input'), true);
            
            $required = ['crop_id', 'plot_size'];
            foreach ($required as $field) {
                if (empty($data[$field])) {
                    ResponseHandler::sendValidationError(["$field is required"]);
                }
            }
            
            $farmerModel = new Farmer();
            $added = $farmerModel->addCrop(
                $user['farmer_id'],
                $data['crop_id'],
                $data['plot_size'],
                $data['planting_date'] ?? null,
                $data['soil_type'] ?? null
            );
            
            if ($added) {
                ResponseHandler::sendResponse(['message' => 'Crop added successfully'], API_CREATED);
            } else {
                ResponseHandler::sendError('Failed to add crop');
            }
            
        } catch (Exception $e) {
            ResponseHandler::sendError($e->getMessage());
        }
    }
    
    public function getActiveCrops() {
        try {
            $user = AuthMiddleware::authenticate();
            
            $farmerModel = new Farmer();
            $crops = $farmerModel->getActiveCrops($user['farmer_id']);
            
            ResponseHandler::sendResponse($crops);
            
        } catch (Exception $e) {
            ResponseHandler::sendError($e->getMessage());
        }
    }
    
    public function addPlot() {
        try {
            $user = AuthMiddleware::authenticate();
            $data = json_decode(file_get_contents('php://input'), true);
            
            $required = ['plot_name', 'plot_size', 'soil_type'];
            foreach ($required as $field) {
                if (empty($data[$field])) {
                    ResponseHandler::sendValidationError(["$field is required"]);
                }
            }
            
            $farmerModel = new Farmer();
            $added = $farmerModel->addPlot(
                $user['farmer_id'],
                $data['plot_name'],
                $data['plot_size'],
                $data['soil_type'],
                $data['location_id'] ?? null
            );
            
            if ($added) {
                ResponseHandler::sendResponse(['message' => 'Plot added successfully'], API_CREATED);
            } else {
                ResponseHandler::sendError('Failed to add plot');
            }
            
        } catch (Exception $e) {
            ResponseHandler::sendError($e->getMessage());
        }
    }
    
    public function getPlots() {
        try {
            $user = AuthMiddleware::authenticate();
            
            $farmerModel = new Farmer();
            $plots = $farmerModel->getPlots($user['farmer_id']);
            
            ResponseHandler::sendResponse($plots);
            
        } catch (Exception $e) {
            ResponseHandler::sendError($e->getMessage());
        }
    }
    
    public function getDashboard() {
        try {
            $user = AuthMiddleware::authenticate();
            
            $farmerModel = new Farmer();
            $dashboard = $farmerModel->getDashboardData($user['farmer_id']);
            
            ResponseHandler::sendResponse($dashboard);
            
        } catch (Exception $e) {
            ResponseHandler::sendError($e->getMessage());
        }
    }
    
    public function getRegions() {
        try {
            AuthMiddleware::authenticate();
            
            $cropModel = new Crop();
            $regions = $cropModel->getAllRegions();
            
            ResponseHandler::sendResponse($regions);
            
        } catch (Exception $e) {
            ResponseHandler::sendError($e->getMessage());
        }
    }
    
    public function getCrops() {
        try {
            AuthMiddleware::authenticate();
            
            $cropModel = new Crop();
            $crops = $cropModel->getAllCrops();
            
            ResponseHandler::sendResponse($crops);
            
        } catch (Exception $e) {
            ResponseHandler::sendError($e->getMessage());
        }
    }
}
?>