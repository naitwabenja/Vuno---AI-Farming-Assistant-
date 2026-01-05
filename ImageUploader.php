<?php
require_once __DIR__ . '/../config/constants.php';

class ImageUploader {
    public static function uploadPlantImage($file, $farmerId, $cropId) {
        $validation = Validator::validateImageFile($file);
        
        if ($validation !== true) {
            throw new Exception($validation);
        }
        
        // Create directory if it doesn't exist
        $uploadDir = UPLOAD_PATH . 'plants/' . $farmerId . '/';
        if (!file_exists($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }
        
        // Generate unique filename
        $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $filename = 'plant_' . $cropId . '_' . time() . '_' . uniqid() . '.' . $extension;
        $filepath = $uploadDir . $filename;
        
        // Move uploaded file
        if (move_uploaded_file($file['tmp_name'], $filepath)) {
            // Compress image if needed
            self::compressImage($filepath);
            
            // Return relative path for database storage
            return 'uploads/plants/' . $farmerId . '/' . $filename;
        }
        
        throw new Exception('Failed to upload image');
    }
    
    private static function compressImage($sourcePath, $quality = 80) {
        $info = getimagesize($sourcePath);
        $mime = $info['mime'];
        
        switch ($mime) {
            case 'image/jpeg':
                $image = imagecreatefromjpeg($sourcePath);
                imagejpeg($image, $sourcePath, $quality);
                break;
            case 'image/png':
                $image = imagecreatefrompng($sourcePath);
                imagepng($image, $sourcePath, round(9 * $quality / 100));
                break;
            case 'image/gif':
                $image = imagecreatefromgif($sourcePath);
                imagegif($image, $sourcePath);
                break;
            case 'image/webp':
                $image = imagecreatefromwebp($sourcePath);
                imagewebp($image, $sourcePath, $quality);
                break;
        }
        
        if (isset($image)) {
            imagedestroy($image);
        }
    }
    
    public static function deleteImage($imagePath) {
        $fullPath = dirname(__DIR__) . '/' . $imagePath;
        if (file_exists($fullPath) && is_file($fullPath)) {
            return unlink($fullPath);
        }
        return false;
    }
}
?>