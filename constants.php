<?php
define('APP_NAME', 'Vuno AI Farming Assistant');
define('APP_VERSION', '1.0.0');
define('BASE_URL', 'http://localhost/vuno-api');
define('UPLOAD_PATH', dirname(__DIR__) . '/uploads/');
define('MAX_UPLOAD_SIZE', 5242880); // 5MB
define('JWT_SECRET', 'vuno_farming_secret_key_2024');
define('JWT_EXPIRY', 86400); // 24 hours

// API Response Codes
define('API_SUCCESS', 200);
define('API_CREATED', 201);
define('API_BAD_REQUEST', 400);
define('API_UNAUTHORIZED', 401);
define('API_FORBIDDEN', 403);
define('API_NOT_FOUND', 404);
define('API_ERROR', 500);

// SMS Gateway (Africa's Talking)
define('SMS_USERNAME', 'sandbox');
define('SMS_API_KEY', 'atsk_310234f6e2c25e307a12d1c8c77e532bd57e5aaaa9da84b35d92cf136cc53ae1a9787a4b');
define('SMS_SHORTCODE', 'VUNO');

// Weather API
define('WEATHER_API_KEY', '8d800b67c20a73fc4230e7e1ffbc4ff4');
define('WEATHER_API_URL', 'https://api.openweathermap.org/data/2.5/weather');

// AI Service (DeepSeek)
define('DEEPSEEK_API_KEY', 'sk-4d0b233676e34169bd1b1e914f983f07');
define('DEEPSEEK_API_URL', 'https://api.deepseek.com/v1/chat/completions');
?>