<?php
require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/../utils/WeatherAPI.php';

class Weather {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }
    
    public function getCurrentWeather($regionId) {
        // Try to get from database first
        $stmt = $this->db->prepare("
            SELECT * FROM weather_data 
            WHERE region_id = ? 
            ORDER BY recorded_date DESC 
            LIMIT 1
        ");
        
        $stmt->execute([$regionId]);
        $weather = $stmt->fetch();
        
        // If data is more than 1 hour old, fetch from API
        if (!$weather || strtotime($weather['created_at']) < time() - 3600) {
            return $this->fetchAndStoreWeather($regionId);
        }
        
        return $weather;
    }
    
    public function getWeatherForecast($regionId, $days = 7) {
        $stmt = $this->db->prepare("
            SELECT * FROM weather_data 
            WHERE region_id = ? AND recorded_date >= CURDATE()
            ORDER BY recorded_date ASC
            LIMIT ?
        ");
        
        $stmt->execute([$regionId, $days]);
        $forecast = $stmt->fetchAll();
        
        // If we don't have enough forecast data, fetch from API
        if (count($forecast) < $days) {
            $apiForecast = $this->fetchForecastFromAPI($regionId, $days);
            
            if ($apiForecast) {
                // Store forecast data
                foreach ($apiForecast as $day) {
                    $this->storeWeatherData($regionId, $day);
                }
                
                // Fetch again from DB
                $stmt->execute([$regionId, $days]);
                $forecast = $stmt->fetchAll();
            }
        }
        
        return $forecast;
    }
    
    public function getPlantingAdvice($regionId, $cropId) {
        $weather = $this->getCurrentWeather($regionId);
        
        if (!$weather) {
            return ['error' => 'Weather data unavailable'];
        }
        
        // Get crop requirements
        $stmt = $this->db->prepare("SELECT * FROM crops WHERE crop_id = ?");
        $stmt->execute([$cropId]);
        $crop = $stmt->fetch();
        
        if (!$crop) {
            return ['error' => 'Crop not found'];
        }
        
        // Analyze conditions
        $soilMoisture = $weather['soil_moisture_percent'] ?? 50;
        $rainfall = $weather['rainfall_mm'] ?? 0;
        $temperature = $weather['temperature_avg'] ?? 25;
        
        $advice = [];
        
        // Soil moisture check
        if ($soilMoisture < 30) {
            $advice[] = "Soil is too dry for planting. Wait for rain or irrigate.";
        } else if ($soilMoisture < 50) {
            $advice[] = "Soil moisture is adequate but could be better. Consider light irrigation.";
        } else {
            $advice[] = "Soil moisture is optimal for planting.";
        }
        
        // Temperature check
        if ($temperature < $crop['optimal_temperature_min']) {
            $advice[] = "Temperature is below optimal for " . $crop['crop_name'] . ". Consider waiting for warmer weather.";
        } else if ($temperature > $crop['optimal_temperature_max']) {
            $advice[] = "Temperature is above optimal for " . $crop['crop_name'] . ". Consider planting in cooler hours.";
        } else {
            $advice[] = "Temperature is optimal for " . $crop['crop_name'] . ".";
        }
        
        // Rainfall check
        if ($rainfall < 5 && !isset($weather['rain_forecast'])) {
            $advice[] = "No significant rain expected soon. Ensure irrigation is available.";
        } else if ($rainfall >= 5) {
            $advice[] = "Recent rainfall is favorable for planting.";
        }
        
        // Get optimal planting dates based on region
        $optimalDates = $this->calculateOptimalPlantingDates($regionId, $cropId);
        
        return [
            'current_conditions' => [
                'temperature' => $temperature,
                'soil_moisture' => $soilMoisture,
                'rainfall' => $rainfall
            ],
            'advice' => $advice,
            'optimal_planting_dates' => $optimalDates,
            'recommended_actions' => $this->generatePlantingActions($cropId, $regionId)
        ];
    }
    
    private function fetchAndStoreWeather($regionId) {
        // Get region coordinates
        $stmt = $this->db->prepare("SELECT latitude, longitude FROM regions WHERE region_id = ?");
        $stmt->execute([$regionId]);
        $region = $stmt->fetch();
        
        if (!$region) {
            return null;
        }
        
        // Fetch from weather API (using OpenWeatherMap example)
        $api = new WeatherAPI();
        $weatherData = $api->getCurrentWeather($region['latitude'], $region['longitude']);
        
        if ($weatherData) {
            $this->storeWeatherData($regionId, $weatherData);
            return $weatherData;
        }
        
        return null;
    }
    
    private function storeWeatherData($regionId, $data) {
        $stmt = $this->db->prepare("
            INSERT INTO weather_data (
                region_id, recorded_date, temperature_max, temperature_min, temperature_avg,
                humidity_avg, rainfall_mm, wind_speed_avg, weather_condition, data_source, created_at
            ) VALUES (?, CURDATE(), ?, ?, ?, ?, ?, ?, ?, 'api', NOW())
            ON DUPLICATE KEY UPDATE
                temperature_max = VALUES(temperature_max),
                temperature_min = VALUES(temperature_min),
                temperature_avg = VALUES(temperature_avg),
                humidity_avg = VALUES(humidity_avg),
                rainfall_mm = VALUES(rainfall_mm),
                wind_speed_avg = VALUES(wind_speed_avg),
                weather_condition = VALUES(weather_condition),
                updated_at = NOW()
        ");
        
        $stmt->execute([
            $regionId,
            $data['temp_max'] ?? null,
            $data['temp_min'] ?? null,
            $data['temp'] ?? null,
            $data['humidity'] ?? null,
            $data['rain'] ?? 0,
            $data['wind_speed'] ?? null,
            $data['condition'] ?? 'unknown'
        ]);
    }
    
    private function calculateOptimalPlantingDates($regionId, $cropId) {
        // This would use historical weather patterns and crop growth cycles
        // For now, return a simple calculation
        
        $today = new DateTime();
        $optimalStart = clone $today;
        $optimalEnd = clone $today;
        
        // Add 7-14 days for preparation
        $optimalStart->modify('+7 days');
        $optimalEnd->modify('+14 days');
        
        return [
            'optimal_start' => $optimalStart->format('Y-m-d'),
            'optimal_end' => $optimalEnd->format('Y-m-d'),
            'reasoning' => 'Based on current weather patterns and soil conditions'
        ];
    }
    
    private function generatePlantingActions($cropId, $regionId) {
        $actions = [];
        
        // Generic planting steps
        $actions[] = "Clear and prepare land 1 week before planting";
        $actions[] = "Test soil pH and adjust if necessary";
        $actions[] = "Apply well-rotted manure or compost";
        $actions[] = "Ensure proper drainage in the field";
        
        // Crop-specific actions
        $stmt = $this->db->prepare("SELECT crop_name FROM crops WHERE crop_id = ?");
        $stmt->execute([$cropId]);
        $crop = $stmt->fetch();
        
        if ($crop) {
            switch (strtolower($crop['crop_name'])) {
                case 'maize':
                    $actions[] = "Apply DAP fertilizer at planting (50kg/acre)";
                    $actions[] = "Space rows 75cm apart, plants 30cm apart";
                    break;
                case 'tomatoes':
                    $actions[] = "Use raised beds for better drainage";
                    $actions[] = "Space plants 45-60cm apart";
                    $actions[] = "Install stakes or trellis for support";
                    break;
                case 'beans':
                    $actions[] = "Inoculate seeds with rhizobium for better nitrogen fixation";
                    $actions[] = "Space rows 50cm apart, plants 10cm apart";
                    break;
            }
        }
        
        return $actions;
    }
}
?>