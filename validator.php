<?php
class Validator {
    public static function validatePhoneNumber($phone) {
        // Validate Kenyan phone numbers (+2547XXXXXXXX)
        $pattern = '/^\+254[17]\d{8}$/';
        return preg_match($pattern, $phone);
    }
    
    public static function validateEmail($email) {
        return filter_var($email, FILTER_VALIDATE_EMAIL);
    }
    
    public static function validatePassword($password) {
        // At least 8 characters, one uppercase, one lowercase, one number
        return strlen($password) >= 8 && 
               preg_match('/[A-Z]/', $password) &&
               preg_match('/[a-z]/', $password) &&
               preg_match('/[0-9]/', $password);
    }
    
    public static function validateCoordinates($lat, $lng) {
        return is_numeric($lat) && is_numeric($lng) &&
               $lat >= -90 && $lat <= 90 &&
               $lng >= -180 && $lng <= 180;
    }
    
    public static function sanitizeInput($input) {
        if (is_array($input)) {
            return array_map([self::class, 'sanitizeInput'], $input);
        }
        return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
    }
    
    public static function validateImageFile($file) {
        $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        $maxSize = 5 * 1024 * 1024; // 5MB
        
        if (!isset($file['error']) || $file['error'] !== UPLOAD_ERR_OK) {
            return "File upload error";
        }
        
        if ($file['size'] > $maxSize) {
            return "File too large. Maximum size is 5MB";
        }
        
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        
        if (!in_array($mime, $allowedTypes)) {
            return "Invalid file type. Allowed: JPG, PNG, GIF, WebP";
        }
        
        return true;
    }
}
?>