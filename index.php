<?php
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../utils/ResponseHandler.php';

// Enable CORS
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Set content type
header('Content-Type: application/json');

// Get request method and path
$method = $_SERVER['REQUEST_METHOD'];
$path = trim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/');
$pathSegments = explode('/', $path);

// Route the request
try {
    switch ($pathSegments[0]) {
        case 'auth':
            require_once __DIR__ . '/../controllers/AuthController.php';
            $controller = new AuthController();
            
            switch ($pathSegments[1] ?? '') {
                case 'register':
                    if ($method === 'POST') $controller->register();
                    break;
                case 'verify':
                    if ($method === 'POST') $controller->verify();
                    break;
                case 'login':
                    if ($method === 'POST') $controller->login();
                    break;
                case 'forgot-password':
                    if ($method === 'POST') $controller->forgotPassword();
                    break;
                case 'reset-password':
                    if ($method === 'POST') $controller->resetPassword();
                    break;
                case 'profile':
                    if ($method === 'GET') $controller->profile();
                    else if ($method === 'PUT') $controller->updateProfile();
                    break;
                default:
                    ResponseHandler::sendNotFound('Auth endpoint');
            }
            break;
            
        case 'farmer':
            require_once __DIR__ . '/../controllers/FarmerController.php';
            $controller = new FarmerController();
            
            switch ($pathSegments[1] ?? '') {
                case 'profile':
                    if ($method === 'GET') $controller->getProfile();
                    else if ($method === 'PUT') $controller->updateProfile();
                    break;
                case 'location':
                    if ($method === 'PUT') $controller->updateLocation();
                    break;
                case 'crops':
                    if ($method === 'GET') $controller->getActiveCrops();
                    else if ($method === 'POST') $controller->addCrop();
                    break;
                case 'plots':
                    if ($method === 'GET') $controller->getPlots();
                    else if ($method === 'POST') $controller->addPlot();
                    break;
                case 'dashboard':
                    if ($method === 'GET') $controller->getDashboard();
                    break;
                case 'regions':
                    if ($method === 'GET') $controller->getRegions();
                    break;
                case 'crops-list':
                    if ($method === 'GET') $controller->getCrops();
                    break;
                default:
                    ResponseHandler::sendNotFound('Farmer endpoint');
            }
            break;
            
        case 'weather':
            require_once __DIR__ . '/../controllers/WeatherController.php';
            $controller = new WeatherController();
            
            switch ($pathSegments[1] ?? '') {
                case 'current':
                    if ($method === 'GET') $controller->getCurrentWeather();
                    break;
                case 'forecast':
                    if ($method === 'GET') $controller->getForecast();
                    break;
                case 'planting-advice':
                    if ($method === 'POST') $controller->getPlantingAdvice();
                    break;
                case 'alerts':
                    if ($method === 'GET') $controller->getWeatherAlerts();
                    break;
                default:
                    ResponseHandler::sendNotFound('Weather endpoint');
            }
            break;
            
        case 'market':
            require_once __DIR__ . '/../controllers/MarketController.php';
            $controller = new MarketController();
            
            switch ($pathSegments[1] ?? '') {
                case 'prices':
                    if ($method === 'GET') $controller->getCurrentPrices();
                    break;
                case 'price-history':
                    if ($method === 'GET') $controller->getPriceHistory();
                    break;
                case 'price-trends':
                    if ($method === 'GET') $controller->getPriceTrends();
                    break;
                case 'markets':
                    if ($method === 'GET') $controller->getMarkets();
                    break;
                case 'alerts':
                    if ($method === 'GET') $controller->getPriceAlerts();
                    else if ($method === 'POST') $controller->createPriceAlert();
                    else if ($method === 'PUT') $controller->updatePriceAlert();
                    else if ($method === 'DELETE') $controller->deletePriceAlert();
                    break;
                default:
                    ResponseHandler::sendNotFound('Market endpoint');
            }
            break;
            
        case 'disease':
            require_once __DIR__ . '/../controllers/DiseaseController.php';
            $controller = new DiseaseController();
            
            switch ($pathSegments[1] ?? '') {
                case 'diagnose-image':
                    if ($method === 'POST') $controller->diagnoseFromImage();
                    break;
                case 'diagnose-symptoms':
                    if ($method === 'POST') $controller->diagnoseFromSymptoms();
                    break;
                case 'info':
                    if ($method === 'GET') $controller->getDiseaseInfo();
                    break;
                case 'common':
                    if ($method === 'GET') $controller->getCommonDiseases();
                    break;
                case 'update-outcome':
                    if ($method === 'PUT') $controller->updateDiagnosisOutcome();
                    break;
                case 'history':
                    if ($method === 'GET') $controller->getDiagnosisHistory();
                    break;
                default:
                    ResponseHandler::sendNotFound('Disease endpoint');
            }
            break;
            
        case 'chat':
            require_once __DIR__ . '/../controllers/ChatController.php';
            $controller = new ChatController();
            
            switch ($pathSegments[1] ?? '') {
                case 'start':
                    if ($method === 'POST') $controller->startSession();
                    break;
                case 'send':
                    if ($method === 'POST') $controller->sendMessage();
                    break;
                case 'history':
                    if ($method === 'GET') $controller->getChatHistory();
                    break;
                case 'session':
                    if ($method === 'GET') $controller->getSessionMessages();
                    break;
                case 'quick-questions':
                    if ($method === 'GET') $controller->getQuickQuestions();
                    break;
                default:
                    ResponseHandler::sendNotFound('Chat endpoint');
            }
            break;
            
        case '':
            ResponseHandler::sendResponse([
                'app' => APP_NAME,
                'version' => APP_VERSION,
                'status' => 'online',
                'endpoints' => [
                    'POST /auth/register' => 'Register new farmer',
                    'POST /auth/verify' => 'Verify phone number',
                    'POST /auth/login' => 'Login',
                    'GET /farmer/profile' => 'Get farmer profile',
                    'GET /weather/current' => 'Get current weather',
                    'GET /market/prices' => 'Get market prices',
                    'POST /disease/diagnose-image' => 'Diagnose plant disease from image',
                    'POST /chat/send' => 'Send message to AI assistant'
                ]
            ]);
            break;
            
        default:
            ResponseHandler::sendNotFound('API endpoint');
    }
    
} catch (Exception $e) {
    ResponseHandler::sendError('Internal server error: ' . $e->getMessage(), API_ERROR);
}
?>