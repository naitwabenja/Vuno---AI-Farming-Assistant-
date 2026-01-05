<?php
require_once __DIR__ . '/../config/constants.php';

class AIService {
    public function analyzePlantImage($imagePath, $symptoms = '') {
        // Call DeepSeek Vision API
        $apiUrl = DEEPSEEK_API_URL;
        $apiKey = DEEPSEEK_API_KEY;
        
        $prompt = "You are an agricultural expert specializing in East African crops. 
                  Analyze this plant image and any provided symptoms.
                  Symptoms provided: $symptoms
                  
                  Provide diagnosis in this JSON format:
                  {
                    'disease_name': 'Name of disease or pest',
                    'confidence': 'Confidence percentage (0-100)',
                    'symptoms_matched': ['List of matched symptoms'],
                    'affected_parts': ['Which parts of plant are affected'],
                    'urgency': 'low/medium/high',
                    'organic_treatments': ['List of organic treatments available in East Africa'],
                    'chemical_treatments': ['List of chemical treatments if organic not possible'],
                    'prevention_measures': ['How to prevent in future'],
                    'additional_advice': 'Any additional advice'
                  }";
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $apiUrl);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $apiKey,
            'Content-Type: application/json'
        ]);
        
        $payload = [
            'model' => 'deepseek-vision',
            'messages' => [
                [
                    'role' => 'user',
                    'content' => [
                        ['type' => 'text', 'text' => $prompt],
                        ['type' => 'image_url', 'image_url' => ['url' => BASE_URL . '/' . $imagePath]]
                    ]
                ]
            ],
            'max_tokens' => 1000,
            'temperature' => 0.3
        ];
        
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode === 200) {
            $result = json_decode($response, true);
            $aiResponse = $result['choices'][0]['message']['content'] ?? '';
            
            // Parse JSON from AI response
            $diagnosis = json_decode($aiResponse, true);
            
            if ($diagnosis) {
                // Try to match with known diseases in database
                $matchedDisease = $this->matchWithKnownDiseases($diagnosis['disease_name']);
                
                return array_merge($diagnosis, [
                    'disease_id' => $matchedDisease['disease_id'] ?? null,
                    'model_version' => 'deepseek-vision-1.0'
                ]);
            }
        }
        
        // Fallback response
        return [
            'disease_name' => 'Unable to diagnose from image',
            'confidence' => 0,
            'urgency' => 'medium',
            'organic_treatments' => [
                'Remove affected plant parts',
                'Apply neem oil spray (2% solution)',
                'Improve air circulation around plants'
            ],
            'advice' => 'Please consult with a local agricultural extension officer for accurate diagnosis.'
        ];
    }
    
    public function analyzeSymptoms($symptoms, $cropId) {
        // Call DeepSeek Chat API for symptom analysis
        $apiUrl = DEEPSEEK_API_URL;
        $apiKey = DEEPSEEK_API_KEY;
        
        // Get crop name for context
        $cropName = $this->getCropName($cropId);
        
        $prompt = "You are an agricultural expert. A farmer growing $cropName reports these symptoms: $symptoms
                  
                  Analyze and provide diagnosis in this JSON format:
                  {
                    'likely_diseases': ['List of possible diseases'],
                    'most_likely': 'Most likely disease',
                    'confidence': 'Confidence percentage (0-100)',
                    'key_symptoms': ['Key symptoms that led to diagnosis'],
                    'urgency': 'low/medium/high',
                    'immediate_actions': ['Immediate actions farmer should take'],
                    'organic_solutions': ['Organic solutions available in East Africa'],
                    'when_to_consult_expert': 'When to seek expert help',
                    'prevention_tips': ['Tips to prevent recurrence']
                  }";
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $apiUrl);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $apiKey,
            'Content-Type: application/json'
        ]);
        
        $payload = [
            'model' => 'deepseek-chat',
            'messages' => [
                ['role' => 'user', 'content' => $prompt]
            ],
            'max_tokens' => 800,
            'temperature' => 0.4
        ];
        
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode === 200) {
            $result = json_decode($response, true);
            $aiResponse = $result['choices'][0]['message']['content'] ?? '';
            
            $diagnosis = json_decode($aiResponse, true);
            
            if ($diagnosis) {
                // Try to match with known diseases
                $matchedDisease = $this->matchWithKnownDiseases($diagnosis['most_likely']);
                
                return array_merge($diagnosis, [
                    'disease_id' => $matchedDisease['disease_id'] ?? null,
                    'model_version' => 'deepseek-chat-1.0'
                ]);
            }
        }
        
        // Fallback response
        return [
            'likely_diseases' => ['Multiple possible causes'],
            'most_likely' => 'Nutrient deficiency or fungal infection',
            'confidence' => 50,
            'immediate_actions' => [
                'Remove severely affected leaves',
                'Avoid overhead watering',
                'Apply organic fungicide like baking soda solution'
            ],
            'organic_solutions' => [
                'Neem oil spray (2% solution)',
                'Baking soda spray (1 tbsp per liter water)',
                'Proper spacing for air circulation'
            ]
        ];
    }
    
    public function getChatResponse($userMessage, $context) {
        // Call DeepSeek Chat API
        $apiUrl = DEEPSEEK_API_URL;
        $apiKey = DEEPSEEK_API_KEY;
        
        // Build system prompt with context
        $systemPrompt = $context['system_prompt'];
        
        // Add farmer context
        if ($context['farmer_info']) {
            $systemPrompt .= "\nFarmer Information: " . json_encode($context['farmer_info']);
        }
        
        if ($context['current_crops']) {
            $systemPrompt .= "\nCurrent Crops: " . json_encode($context['current_crops']);
        }
        
        // Build conversation history
        $messages = [['role' => 'system', 'content' => $systemPrompt]];
        
        foreach ($context['recent_messages'] as $msg) {
            $role = $msg['message_type'] === 'user' ? 'user' : 'assistant';
            $messages[] = ['role' => $role, 'content' => $msg['message_text']];
        }
        
        // Add current message
        $messages[] = ['role' => 'user', 'content' => $userMessage];
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $apiUrl);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $apiKey,
            'Content-Type: application/json'
        ]);
        
        $payload = [
            'model' => 'deepseek-chat',
            'messages' => $messages,
            'max_tokens' => 500,
            'temperature' => 0.7,
            'stream' => false
        ];
        
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        
        $startTime = microtime(true);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $endTime = microtime(true);
        
        curl_close($ch);
        
        if ($httpCode === 200) {
            $result = json_decode($response, true);
            $aiResponse = $result['choices'][0]['message']['content'] ?? '';
            
            // Detect intent from response
            $intent = $this->detectIntent($userMessage, $aiResponse);
            
            return [
                'response' => $aiResponse,
                'intent' => $intent,
                'confidence' => 0.9,
                'response_time_ms' => round(($endTime - $startTime) * 1000)
            ];
        }
        
        // Fallback response
        return [
            'response' => "I apologize, but I'm having trouble processing your request. Please try again or contact support if the issue persists.",
            'intent' => 'error',
            'confidence' => 0,
            'response_time_ms' => 0
        ];
    }
    
    private function matchWithKnownDiseases($diseaseName) {
        // Simple keyword matching - in production, use more sophisticated NLP
        $keywords = [
            'blight' => ['early blight', 'late blight'],
            'rot' => ['root rot', 'stem rot', 'fruit rot'],
            'wilt' => ['wilt', 'wilting'],
            'mosaic' => ['mosaic virus'],
            'rust' => ['rust', 'leaf rust'],
            'mildew' => ['mildew', 'powdery mildew']
        ];
        
        // This would query the database in real implementation
        return ['disease_id' => null];
    }
    
    private function getCropName($cropId) {
        // Query database for crop name
        // For now, return placeholder
        return "the crop";
    }
    
    private function detectIntent($userMessage, $aiResponse) {
        $userMessage = strtolower($userMessage);
        
        if (strpos($userMessage, 'plant') !== false || strpos($userMessage, 'grow') !== false) {
            return 'planting_advice';
        } elseif (strpos($userMessage, 'disease') !== false || strpos($userMessage, 'sick') !== false) {
            return 'disease_diagnosis';
        } elseif (strpos($userMessage, 'price') !== false || strpos($userMessage, 'market') !== false) {
            return 'market_info';
        } elseif (strpos($userMessage, 'weather') !== false || strpos($userMessage, 'rain') !== false) {
            return 'weather_info';
        } elseif (strpos($userMessage, 'fertilizer') !== false || strpos($userMessage, 'water') !== false) {
            return 'resource_advice';
        } else {
            return 'general_advice';
        }
    }
}
?>