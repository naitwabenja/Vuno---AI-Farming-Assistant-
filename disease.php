<?php
require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/../utils/ImageUploader.php';
require_once __DIR__ . '/../utils/AIService.php';

class Disease {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }
    
    public function diagnoseFromImage($imageFile, $farmerId, $cropId, $symptoms = '') {
        try {
            // Upload image
            $imagePath = ImageUploader::uploadPlantImage($imageFile, $farmerId, $cropId);
            
            // Call AI service for diagnosis
            $aiService = new AIService();
            $diagnosis = $aiService->analyzePlantImage($imagePath, $symptoms);
            
            // Store diagnosis
            $diagnosisId = $this->storeDiagnosis($farmerId, $cropId, $imagePath, $symptoms, $diagnosis);
            
            // Get recommended treatments
            $treatments = $this->getRecommendedTreatments($diagnosis['disease_id'] ?? null);
            
            return [
                'diagnosis_id' => $diagnosisId,
                'image_url' => BASE_URL . '/' . $imagePath,
                'ai_diagnosis' => $diagnosis,
                'recommended_treatments' => $treatments
            ];
            
        } catch (Exception $e) {
            throw new Exception("Diagnosis failed: " . $e->getMessage());
        }
    }
    
    public function diagnoseFromSymptoms($farmerId, $cropId, $symptoms) {
        // Use AI to analyze symptoms
        $aiService = new AIService();
        $diagnosis = $aiService->analyzeSymptoms($symptoms, $cropId);
        
        // Store diagnosis
        $diagnosisId = $this->storeDiagnosis($farmerId, $cropId, null, $symptoms, $diagnosis);
        
        // Get recommended treatments
        $treatments = $this->getRecommendedTreatments($diagnosis['disease_id'] ?? null);
        
        return [
            'diagnosis_id' => $diagnosisId,
            'ai_diagnosis' => $diagnosis,
            'recommended_treatments' => $treatments
        ];
    }
    
    private function storeDiagnosis($farmerId, $cropId, $imagePath, $symptoms, $aiDiagnosis) {
        $stmt = $this->db->prepare("
            INSERT INTO disease_diagnoses 
            (farmer_id, crop_id, image_url, symptoms_description, disease_id, 
             confidence_level, ai_model_version, diagnosis_date, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, CURDATE(), NOW())
        ");
        
        $stmt->execute([
            $farmerId,
            $cropId,
            $imagePath,
            $symptoms,
            $aiDiagnosis['disease_id'] ?? null,
            $aiDiagnosis['confidence'] ?? null,
            $aiDiagnosis['model_version'] ?? '1.0'
        ]);
        
        return $this->db->lastInsertId();
    }
    
    public function getRecommendedTreatments($diseaseId) {
        if (!$diseaseId) {
            // Get general treatments for common issues
            return $this->getGeneralTreatments();
        }
        
        $stmt = $this->db->prepare("
            SELECT t.* 
            FROM treatments t
            WHERE t.disease_id = ? AND t.availability_east_africa IN ('common', 'uncommon')
            ORDER BY 
                CASE WHEN t.treatment_type = 'organic' THEN 1 ELSE 2 END,
                t.effectiveness_rating DESC,
                t.safety_rating DESC
        ");
        
        $stmt->execute([$diseaseId]);
        return $stmt->fetchAll();
    }
    
    private function getGeneralTreatments() {
        $stmt = $this->db->prepare("
            SELECT 
                'General Advice' as treatment_name,
                'preventive' as treatment_type,
                'Apply these general good farming practices:' as application_method,
                'Always applicable' as dosage_per_acre,
                5 as safety_rating
            UNION ALL
            SELECT 
                'Neem oil spray',
                'organic',
                'Mix 2% neem oil with water and spray on affected plants',
                '200ml per 20 liters water',
                5
            UNION ALL
            SELECT 
                'Copper-based fungicide',
                'chemical',
                'Apply as per manufacturer instructions for fungal issues',
                'As per product label',
                3
            UNION ALL
            SELECT 
                'Proper spacing and ventilation',
                'cultural',
                'Ensure adequate spacing between plants for air circulation',
                'Follow crop-specific spacing guidelines',
                5
        ");
        
        $stmt->execute();
        return $stmt->fetchAll();
    }
    
    public function getDiseaseInfo($diseaseId) {
        $stmt = $this->db->prepare("
            SELECT d.*, 
                   ARRAY_AGG(c.crop_name) as affected_crops
            FROM diseases d
            LEFT JOIN crops c ON c.crop_id = ANY(d.affected_crop_ids)
            WHERE d.disease_id = ?
            GROUP BY d.disease_id
        ");
        
        $stmt->execute([$diseaseId]);
        return $stmt->fetch();
    }
    
    public function getCommonDiseases($cropId = null) {
        $params = [];
        $where = "";
        
        if ($cropId) {
            $where = "WHERE ? = ANY(d.affected_crop_ids)";
            $params[] = $cropId;
        }
        
        $sql = "
            SELECT d.*,
                   ARRAY_AGG(c.crop_name) as affected_crops
            FROM diseases d
            LEFT JOIN crops c ON c.crop_id = ANY(d.affected_crop_ids)
            $where
            GROUP BY d.disease_id
            ORDER BY d.severity_level DESC, d.disease_name
            LIMIT 20
        ";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
    
    public function updateDiagnosisOutcome($diagnosisId, $farmerId, $treatmentId, $outcome, $notes = '') {
        $stmt = $this->db->prepare("
            UPDATE disease_diagnoses 
            SET treatment_applied_id = ?, 
                treatment_application_date = CURDATE(),
                treatment_outcome = ?,
                outcome_notes = ?
            WHERE diagnosis_id = ? AND farmer_id = ?
        ");
        
        return $stmt->execute([$treatmentId, $outcome, $notes, $diagnosisId, $farmerId]);
    }
    
    public function getDiagnosisHistory($farmerId, $limit = 20) {
        $stmt = $this->db->prepare("
            SELECT 
                dd.*,
                c.crop_name,
                d.disease_name,
                t.treatment_name
            FROM disease_diagnoses dd
            JOIN crops c ON dd.crop_id = c.crop_id
            LEFT JOIN diseases d ON dd.disease_id = d.disease_id
            LEFT JOIN treatments t ON dd.treatment_applied_id = t.treatment_id
            WHERE dd.farmer_id = ?
            ORDER BY dd.diagnosis_date DESC, dd.created_at DESC
            LIMIT ?
        ");
        
        $stmt->execute([$farmerId, $limit]);
        return $stmt->fetchAll();
    }
}
?>