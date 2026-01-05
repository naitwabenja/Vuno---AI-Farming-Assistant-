<?php
require_once __DIR__ . '/../config/constants.php';

class SMSHandler {
    public static function sendSMS($phone, $message) {
        // Using Africa's Talking API (Kenyan focus)
        $url = 'https://api.africastalking.com/version1/messaging';
        
        $headers = [
            'apiKey: ' . SMS_API_KEY,
            'Content-Type: application/x-www-form-urlencoded',
            'Accept: application/json'
        ];
        
        $data = [
            'username' => SMS_USERNAME,
            'to' => $phone,
            'message' => $message,
            'from' => SMS_SHORTCODE
        ];
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode === 201) {
            return ['success' => true, 'response' => json_decode($response, true)];
        }
        
        error_log("SMS sending failed: " . $response);
        return ['success' => false, 'error' => 'SMS sending failed'];
    }
    
    public static function sendOTP($phone) {
        $otp = rand(100000, 999999);
        $message = "Your Vuno verification code is: $otp. Valid for 10 minutes.";
        
        $result = self::sendSMS($phone, $message);
        
        if ($result['success']) {
            // Store OTP in database (in real implementation)
            return ['success' => true, 'otp' => $otp];
        }
        
        return $result;
    }
    
    public static function sendMarketPriceAlert($phone, $crop, $price, $market) {
        $message = "Vuno Price Alert: $crop is selling at KES $price/kg in $market market. Consider selling now!";
        return self::sendSMS($phone, $message);
    }
    
    public static function sendWeatherAlert($phone, $alert, $region) {
        $message = "Vuno Weather Alert for $region: $alert";
        return self::sendSMS($phone, $message);
    }
}
?>