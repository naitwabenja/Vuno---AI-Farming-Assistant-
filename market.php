<?php
require_once __DIR__ . '/Database.php';

class Market {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }
    
    public function getCurrentPrices($cropId = null, $marketId = null, $limit = 50) {
        $params = [];
        $where = "WHERE mp.price_date = CURDATE()";
        
        if ($cropId) {
            $where .= " AND mp.crop_id = ?";
            $params[] = $cropId;
        }
        
        if ($marketId) {
            $where .= " AND mp.market_id = ?";
            $params[] = $marketId;
        }
        
        $sql = "
            SELECT 
                mp.*,
                m.market_name,
                r.region_name,
                r.county,
                c.crop_name,
                c.local_name
            FROM market_prices mp
            JOIN markets m ON mp.market_id = m.market_id
            LEFT JOIN regions r ON m.region_id = r.region_id
            JOIN crops c ON mp.crop_id = c.crop_id
            $where
            ORDER BY mp.price_date DESC, m.market_name
            LIMIT ?
        ";
        
        $params[] = $limit;
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->fetchAll();
    }
    
    public function getPriceHistory($cropId, $marketId = null, $days = 30) {
        $params = ["mp.crop_id = ?"];
        $bindings = [$cropId];
        
        if ($marketId) {
            $params[] = "mp.market_id = ?";
            $bindings[] = $marketId;
        }
        
        $where = "WHERE " . implode(" AND ", $params) . " AND mp.price_date >= DATE_SUB(CURDATE(), INTERVAL ? DAY)";
        $bindings[] = $days;
        
        $sql = "
            SELECT 
                mp.price_date,
                AVG(mp.wholesale_price) as avg_price,
                MIN(mp.wholesale_price) as min_price,
                MAX(mp.wholesale_price) as max_price,
                COUNT(*) as data_points
            FROM market_prices mp
            $where
            GROUP BY mp.price_date
            ORDER BY mp.price_date DESC
        ";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($bindings);
        
        return $stmt->fetchAll();
    }
    
    public function getMarketPriceAlerts($farmerId) {
        $stmt = $this->db->prepare("
            SELECT 
                mpa.*,
                c.crop_name,
                m.market_name,
                mp.wholesale_price as current_price
            FROM market_price_alerts mpa
            JOIN crops c ON mpa.crop_id = c.crop_id
            LEFT JOIN markets m ON mpa.market_id = m.market_id
            LEFT JOIN market_prices mp ON mpa.crop_id = mp.crop_id 
                AND (mpa.market_id IS NULL OR mpa.market_id = mp.market_id)
                AND mp.price_date = CURDATE()
            WHERE mpa.farmer_id = ? AND mpa.is_active = TRUE
        ");
        
        $stmt->execute([$farmerId]);
        return $stmt->fetchAll();
    }
    
    public function createPriceAlert($farmerId, $cropId, $alertType, $thresholdPrice, $marketId = null) {
        $stmt = $this->db->prepare("
            INSERT INTO market_price_alerts 
            (farmer_id, crop_id, market_id, alert_type, threshold_price, created_at)
            VALUES (?, ?, ?, ?, ?, NOW())
        ");
        
        return $stmt->execute([$farmerId, $cropId, $marketId, $alertType, $thresholdPrice]);
    }
    
    public function updatePriceAlert($alertId, $data) {
        $allowedFields = ['alert_type', 'threshold_price', 'market_id', 'is_active'];
        $updates = [];
        $params = [];
        
        foreach ($data as $key => $value) {
            if (in_array($key, $allowedFields)) {
                $updates[] = "$key = ?";
                $params[] = $value;
            }
        }
        
        if (empty($updates)) {
            return false;
        }
        
        $params[] = $alertId;
        $sql = "UPDATE market_price_alerts SET " . implode(', ', $updates) . " WHERE alert_id = ?";
        
        $stmt = $this->db->prepare($sql);
        return $stmt->execute($params);
    }
    
    public function deletePriceAlert($alertId, $farmerId) {
        $stmt = $this->db->prepare("
            DELETE FROM market_price_alerts 
            WHERE alert_id = ? AND farmer_id = ?
        ");
        
        return $stmt->execute([$alertId, $farmerId]);
    }
    
    public function getNearbyMarkets($latitude, $longitude, $radiusKm = 50) {
        // Using Haversine formula to calculate distance
        $sql = "
            SELECT 
                m.*,
                r.region_name,
                r.county,
                (6371 * ACOS(
                    COS(RADIANS(?)) * COS(RADIANS(r.latitude)) 
                    * COS(RADIANS(r.longitude) - RADIANS(?)) 
                    + SIN(RADIANS(?)) * SIN(RADIANS(r.latitude))
                )) AS distance_km
            FROM markets m
            LEFT JOIN regions r ON m.region_id = r.region_id
            WHERE r.latitude IS NOT NULL AND r.longitude IS NOT NULL
            HAVING distance_km <= ?
            ORDER BY distance_km ASC
            LIMIT 10
        ";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$latitude, $longitude, $latitude, $radiusKm]);
        
        return $stmt->fetchAll();
    }
    
    public function getPriceTrends($cropId, $days = 7) {
        $sql = "
            SELECT 
                mp.price_date,
                m.market_name,
                AVG(mp.wholesale_price) as avg_price,
                LAG(AVG(mp.wholesale_price), 1) OVER (PARTITION BY mp.market_id ORDER BY mp.price_date) as prev_price,
                COUNT(*) as data_points
            FROM market_prices mp
            JOIN markets m ON mp.market_id = m.market_id
            WHERE mp.crop_id = ? AND mp.price_date >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
            GROUP BY mp.market_id, mp.price_date
            ORDER BY mp.price_date DESC, m.market_name
        ";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$cropId, $days]);
        $prices = $stmt->fetchAll();
        
        // Calculate trends
        $trends = [];
        foreach ($prices as $price) {
            if ($price['prev_price']) {
                $change = (($price['avg_price'] - $price['prev_price']) / $price['prev_price']) * 100;
                $price['percent_change'] = round($change, 2);
                $price['trend'] = $change > 0 ? 'up' : ($change < 0 ? 'down' : 'stable');
            } else {
                $price['percent_change'] = 0;
                $price['trend'] = 'stable';
            }
            $trends[] = $price;
        }
        
        return $trends;
    }
}
?>