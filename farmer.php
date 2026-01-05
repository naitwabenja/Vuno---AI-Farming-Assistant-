<?php
require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/../utils/Validator.php';

class Farmer {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }
    
    public function getProfile($farmerId) {
        $stmt = $this->db->prepare("
            SELECT 
                f.*,
                u.full_name,
                u.phone_number,
                u.email,
                u.language_preference,
                fl.specific_location,
                r.region_name,
                r.county,
                r.latitude,
                r.longitude
            FROM farmers f
            JOIN users u ON f.user_id = u.user_id
            LEFT JOIN farmer_locations fl ON f.farmer_id = fl.farmer_id AND fl.is_primary_location = TRUE
            LEFT JOIN regions r ON fl.region_id = r.region_id
            WHERE f.farmer_id = ?
        ");
        
        $stmt->execute([$farmerId]);
        $profile = $stmt->fetch();
        
        if (!$profile) {
            return null;
        }
        
        // Get active crops
        $profile['active_crops'] = $this->getActiveCrops($farmerId);
        
        // Get farm plots
        $profile['plots'] = $this->getPlots($farmerId);
        
        return $profile;
    }
    
    public function updateProfile($farmerId, $data) {
        $allowedFields = [
            'gender', 'date_of_birth', 'education_level', 
            'farming_experience_years', 'farm_size_total',
            'annual_income_range', 'is_whatsapp_user', 'whatsapp_number'
        ];
        
        $updates = [];
        $params = [];
        
        foreach ($data as $key => $value) {
            if (in_array($key, $allowedFields)) {
                $updates[] = "$key = ?";
                $params[] = Validator::sanitizeInput($value);
            }
        }
        
        if (empty($updates)) {
            return false;
        }
        
        $params[] = $farmerId;
        $sql = "UPDATE farmers SET " . implode(', ', $updates) . ", updated_at = NOW() WHERE farmer_id = ?";
        
        $stmt = $this->db->prepare($sql);
        return $stmt->execute($params);
    }
    
    public function updateLocation($farmerId, $regionId, $specificLocation, $latitude = null, $longitude = null) {
        // Check if location exists
        $stmt = $this->db->prepare("
            SELECT location_id FROM farmer_locations 
            WHERE farmer_id = ? AND is_primary_location = TRUE
        ");
        
        $stmt->execute([$farmerId]);
        $existing = $stmt->fetch();
        
        if ($existing) {
            // Update existing
            $stmt = $this->db->prepare("
                UPDATE farmer_locations 
                SET region_id = ?, specific_location = ?, latitude = ?, longitude = ?, verified_at = NOW()
                WHERE location_id = ?
            ");
            
            return $stmt->execute([$regionId, $specificLocation, $latitude, $longitude, $existing['location_id']]);
        } else {
            // Insert new
            $stmt = $this->db->prepare("
                INSERT INTO farmer_locations (farmer_id, region_id, specific_location, latitude, longitude, is_primary_location, created_at)
                VALUES (?, ?, ?, ?, ?, TRUE, NOW())
            ");
            
            return $stmt->execute([$farmerId, $regionId, $specificLocation, $latitude, $longitude]);
        }
    }
    
    public function addCrop($farmerId, $cropId, $plotSize, $plantingDate = null, $soilType = null) {
        $stmt = $this->db->prepare("
            INSERT INTO farmer_crops (farmer_id, crop_id, plot_size, planting_date, soil_type, created_at)
            VALUES (?, ?, ?, ?, ?, NOW())
        ");
        
        return $stmt->execute([$farmerId, $cropId, $plotSize, $plantingDate, $soilType]);
    }
    
    public function getActiveCrops($farmerId) {
        $stmt = $this->db->prepare("
            SELECT 
                fc.*,
                c.crop_name,
                c.local_name,
                c.crop_type,
                c.growth_duration_days
            FROM farmer_crops fc
            JOIN crops c ON fc.crop_id = c.crop_id
            WHERE fc.farmer_id = ? AND fc.is_active = TRUE
            ORDER BY fc.planting_date DESC
        ");
        
        $stmt->execute([$farmerId]);
        return $stmt->fetchAll();
    }
    
    public function getPlots($farmerId) {
        $stmt = $this->db->prepare("
            SELECT * FROM farmer_plots 
            WHERE farmer_id = ? 
            ORDER BY created_at DESC
        ");
        
        $stmt->execute([$farmerId]);
        return $stmt->fetchAll();
    }
    
    public function addPlot($farmerId, $plotName, $plotSize, $soilType, $locationId = null) {
        $stmt = $this->db->prepare("
            INSERT INTO farmer_plots (farmer_id, plot_name, plot_size, soil_type, location_id, created_at)
            VALUES (?, ?, ?, ?, ?, NOW())
        ");
        
        return $stmt->execute([$farmerId, $plotName, $plotSize, $soilType, $locationId]);
    }
    
    public function getDashboardData($farmerId) {
        $data = [];
        
        // Basic farmer info
        $stmt = $this->db->prepare("
            SELECT COUNT(*) as active_crops, SUM(plot_size) as total_plot_size
            FROM farmer_crops 
            WHERE farmer_id = ? AND is_active = TRUE
        ");
        
        $stmt->execute([$farmerId]);
        $data['crops_summary'] = $stmt->fetch();
        
        // Recent diagnoses
        $stmt = $this->db->prepare("
            SELECT dd.*, c.crop_name, d.disease_name
            FROM disease_diagnoses dd
            JOIN crops c ON dd.crop_id = c.crop_id
            LEFT JOIN diseases d ON dd.disease_id = d.disease_id
            WHERE dd.farmer_id = ?
            ORDER BY dd.diagnosis_date DESC
            LIMIT 5
        ");
        
        $stmt->execute([$farmerId]);
        $data['recent_diagnoses'] = $stmt->fetchAll();
        
        // Recent chat interactions
        $stmt = $this->db->prepare("
            SELECT cs.session_id, cs.started_at, COUNT(cm.message_id) as message_count
            FROM chat_sessions cs
            LEFT JOIN chat_messages cm ON cs.session_id = cm.session_id
            WHERE cs.farmer_id = ?
            GROUP BY cs.session_id
            ORDER BY cs.started_at DESC
            LIMIT 5
        ");
        
        $stmt->execute([$farmerId]);
        $data['recent_chats'] = $stmt->fetchAll();
        
        return $data;
    }
}
?>