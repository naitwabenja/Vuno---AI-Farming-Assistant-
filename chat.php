<?php
require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/../utils/AIService.php';

class Chat {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }
    
    public function createSession($farmerId, $sessionType = 'text', $language = 'en') {
        $stmt = $this->db->prepare("
            INSERT INTO chat_sessions 
            (farmer_id, session_type, language_used, started_at, created_at)
            VALUES (?, ?, ?, NOW(), NOW())
        ");
        
        $stmt->execute([$farmerId, $sessionType, $language]);
        
        return $this->db->lastInsertId();
    }
    
    public function sendMessage($sessionId, $message, $messageType = 'text', $farmerId = null) {
        // Validate session belongs to farmer if farmerId provided
        if ($farmerId) {
            $stmt = $this->db->prepare("
                SELECT session_id FROM chat_sessions 
                WHERE session_id = ? AND farmer_id = ?
            ");
            
            $stmt->execute([$sessionId, $farmerId]);
            
            if (!$stmt->fetch()) {
                throw new Exception('Invalid session');
            }
        }
        
        // Store user message
        $stmt = $this->db->prepare("
            INSERT INTO chat_messages 
            (session_id, message_type, content_type, message_text, created_at)
            VALUES (?, 'user', ?, ?, NOW())
        ");
        
        $stmt->execute([$sessionId, $messageType, $message]);
        $messageId = $this->db->lastInsertId();
        
        // Get AI response
        $aiResponse = $this->getAIResponse($sessionId, $message);
        
        // Store AI response
        $stmt = $this->db->prepare("
            INSERT INTO chat_messages 
            (session_id, message_type, content_type, message_text, ai_intent, ai_confidence, created_at)
            VALUES (?, 'ai', 'text', ?, ?, ?, NOW())
        ");
        
        $stmt->execute([$sessionId, $aiResponse['response'], $aiResponse['intent'], $aiResponse['confidence']]);
        
        // Update session
        $this->updateSessionActivity($sessionId);
        
        return [
            'user_message_id' => $messageId,
            'ai_response' => $aiResponse['response'],
            'ai_intent' => $aiResponse['intent']
        ];
    }
    
    private function getAIResponse($sessionId, $userMessage) {
        // Get session context
        $context = $this->getSessionContext($sessionId);
        
        // Call AI service
        $aiService = new AIService();
        $response = $aiService->getChatResponse($userMessage, $context);
        
        // Log common question if detected
        if (isset($response['intent'])) {
            $this->logCommonQuestion($userMessage, $response['intent']);
        }
        
        return $response;
    }
    
    private function getSessionContext($sessionId) {
        // Get recent messages for context
        $stmt = $this->db->prepare("
            SELECT cm.message_type, cm.message_text, cm.ai_intent
            FROM chat_messages cm
            WHERE cm.session_id = ?
            ORDER BY cm.created_at DESC
            LIMIT 10
        ");
        
        $stmt->execute([$sessionId]);
        $messages = $stmt->fetchAll();
        
        // Get farmer info for context
        $stmt = $this->db->prepare("
            SELECT f.farmer_id, u.full_name, fl.specific_location, r.region_name
            FROM chat_sessions cs
            JOIN farmers f ON cs.farmer_id = f.farmer_id
            JOIN users u ON f.user_id = u.user_id
            LEFT JOIN farmer_locations fl ON f.farmer_id = fl.farmer_id AND fl.is_primary_location = TRUE
            LEFT JOIN regions r ON fl.region_id = r.region_id
            WHERE cs.session_id = ?
        ");
        
        $stmt->execute([$sessionId]);
        $farmerInfo = $stmt->fetch();
        
        // Get farmer's crops
        $stmt = $this->db->prepare("
            SELECT c.crop_name, fc.plot_size
            FROM farmer_crops fc
            JOIN crops c ON fc.crop_id = c.crop_id
            WHERE fc.farmer_id = ? AND fc.is_active = TRUE
        ");
        
        $stmt->execute([$farmerInfo['farmer_id'] ?? null]);
        $crops = $stmt->fetchAll();
        
        return [
            'recent_messages' => array_reverse($messages),
            'farmer_info' => $farmerInfo,
            'current_crops' => $crops,
            'current_date' => date('Y-m-d'),
            'system_prompt' => $this->getSystemPrompt($farmerInfo['language_preference'] ?? 'en')
        ];
    }
    
    private function getSystemPrompt($language = 'en') {
        $prompts = [
            'en' => "You are Vuno, an AI farming assistant for smallholder farmers in East Africa. 
                    Provide accurate, practical advice in simple language. 
                    Focus on organic solutions when possible. 
                    Consider local availability of resources. 
                    Be encouraging and supportive. 
                    Ask clarifying questions when needed.",
            'sw' => "Wewe ni Vuno, msaidizi wa kilimo wa AI kwa wakulima wadogo Afrika Mashariki. 
                    Toa ushauri sahihi, unaoweza kutekelezwa kwa lugha rahisi. 
                    Zingatia suluhisho za kikaboni iwezekanavyo. 
                    Fikiria upatikanaji wa rasilimali ndani ya nchi. 
                    Kuwa na moyo wa kusaidia na kuhamasisha. 
                    Uliza maswali ya kufafanua unapohitaji."
        ];
        
        return $prompts[$language] ?? $prompts['en'];
    }
    
    private function updateSessionActivity($sessionId) {
        $stmt = $this->db->prepare("
            UPDATE chat_sessions 
            SET ended_at = NOW(),
                session_duration_seconds = TIMESTAMPDIFF(SECOND, started_at, NOW())
            WHERE session_id = ?
        ");
        
        $stmt->execute([$sessionId]);
    }
    
    private function logCommonQuestion($question, $intent = null) {
        $stmt = $this->db->prepare("
            INSERT INTO common_questions (question_text, question_intent, first_asked, last_asked)
            VALUES (?, ?, NOW(), NOW())
            ON DUPLICATE KEY UPDATE 
                frequency_count = frequency_count + 1,
                last_asked = NOW()
        ");
        
        $stmt->execute([$question, $intent]);
    }
    
    public function getChatHistory($farmerId, $limit = 20) {
        $stmt = $this->db->prepare("
            SELECT 
                cs.session_id,
                cs.started_at,
                cs.session_type,
                cs.language_used,
                COUNT(cm.message_id) as message_count
            FROM chat_sessions cs
            LEFT JOIN chat_messages cm ON cs.session_id = cm.session_id
            WHERE cs.farmer_id = ?
            GROUP BY cs.session_id
            ORDER BY cs.started_at DESC
            LIMIT ?
        ");
        
        $stmt->execute([$farmerId, $limit]);
        return $stmt->fetchAll();
    }
    
    public function getSessionMessages($sessionId, $farmerId = null) {
        $params = ["cm.session_id = ?"];
        $bindings = [$sessionId];
        
        if ($farmerId) {
            $params[] = "cs.farmer_id = ?";
            $bindings[] = $farmerId;
        }
        
        $where = "WHERE " . implode(" AND ", $params);
        
        $sql = "
            SELECT 
                cm.*,
                cs.farmer_id
            FROM chat_messages cm
            JOIN chat_sessions cs ON cm.session_id = cs.session_id
            $where
            ORDER BY cm.created_at ASC
        ";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($bindings);
        return $stmt->fetchAll();
    }
    
    public function getQuickQuestions($language = 'en', $category = null) {
        $params = ["language = ?"];
        $bindings = [$language];
        
        if ($category) {
            $params[] = "category = ?";
            $bindings[] = $category;
        }
        
        $where = "WHERE " . implode(" AND ", $params);
        
        $sql = "
            SELECT question_text, category
            FROM common_questions
            $where
            ORDER BY frequency_count DESC
            LIMIT 10
        ";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($bindings);
        return $stmt->fetchAll();
    }
}
?>