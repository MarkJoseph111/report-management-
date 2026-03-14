<?php
/**
 * NLP Integration Service
 * Handles communication with the NLP API and database updates
 */

class NLPService {
    private $db;
    private $nlp_api_url;
    private $nlp_enabled;
    
public function __construct($db, $nlp_api_url = null, $nlp_enabled = true) {
        global $NLP_API_URL;
        $this->db = $db;
        $this->nlp_api_url = $nlp_api_url ?? $NLP_API_URL ?? 'http://localhost:5000/analyze';
        $this->nlp_enabled = $nlp_enabled;
        $this->db = $db;
        $this->nlp_api_url = $nlp_api_url;
        $this->nlp_enabled = $nlp_enabled;
    }
    
    /**
     * Analyze report text and return category and priority
     * @param string $text Report content to analyze
     * @return array ['category' => 'normal|spam|duplicate', 'priority' => 'high|medium|low|critical', 'confidence' => 0.95]
     */
    public function analyzeReport($text) {
        // Try NLP API first if enabled
        if ($this->nlp_enabled) {
            $nlp_result = $this->callNLPAPI($text);
            if ($nlp_result !== null) {
                return $nlp_result;
            }
        }
        
        // Fallback to keyword-based analysis
        return $this->keywordBasedAnalysis($text);
    }
    
    /**
     * Call the Flask NLP API
     */
    private function callNLPAPI($text) {
        try {
            $payload = json_encode(['text' => $text]);
            $ch = curl_init($this->nlp_api_url);
            
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 10,
                CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => $payload,
            ]);
            
            $response = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            
            
            // Debug: Log the API response
            error_log("NLP API Response: " . $response);
            error_log("NLP HTTP Code: " . $http_code);
            
            if ($http_code === 200 && $response) {
                $data = json_decode($response, true);
                
                // Check for priority field (not category - NLP API returns priority)
                if (isset($data['priority'])) {
                    $result = [
                        'category' => 'normal',
                        'category_confidence' => 1.0,
                        'source' => 'nlp'
                    ];
                    
                    // Get priority from API response
                    $result['priority'] = $this->normalizePriority($data['priority']);
                    $result['priority_confidence'] = isset($data['priority_confidence']) 
                        ? min(round($data['priority_confidence'], 2), 1) 
                        : 0.5;
                    
                    // Include the analysis method for debugging
                    $result['method'] = isset($data['method']) ? $data['method'] : 'unknown';
                    
                    error_log("NLP API returned priority: " . $result['priority'] . " (confidence: " . $result['priority_confidence'] . ")");
                    
                    return $result;
                }
            }
        } catch (Exception $e) {
            error_log('NLP API Error: ' . $e->getMessage());
        }
        
        return null;
    }
    
    /**
     * Keyword-based category and priority analysis (fallback)
     */
    private function keywordBasedAnalysis($text) {
        $text_lower = strtolower($text);
        
        // Normal report, analyze priority
        $confidence = 0.5;
        $priority = 'medium';
        
        // Critical keywords
        $critical_keywords = ['urgent', 'emergency', 'critical', 'danger', 'threat', 'attack', 'bomb', 'fire', 'life-threatening', 'death', 'injury'];
        
        // High priority keywords
        $high_keywords = ['severe', 'major', 'serious', 'accident', 'injury', 'crash', 'broken', 'cannot work', 'fails'];
        
        // Low priority keywords
        $low_keywords = ['minor', 'small', 'slight', 'question', 'how', 'help', 'assistance', 'documentation'];
        
        $critical_count = 0;
        $high_count = 0;
        $low_count = 0;
        
        foreach ($critical_keywords as $keyword) {
            if (strpos($text_lower, $keyword) !== false) {
                $critical_count++;
            }
        }
        
        foreach ($high_keywords as $keyword) {
            if (strpos($text_lower, $keyword) !== false) {
                $high_count++;
            }
        }
        
        foreach ($low_keywords as $keyword) {
            if (strpos($text_lower, $keyword) !== false) {
                $low_count++;
            }
        }
        
        if ($critical_count > 0) {
            $priority = 'critical';
            $confidence = min(0.5 + ($critical_count * 0.15), 0.95);
        } elseif ($high_count > 0) {
            $priority = 'high';
            $confidence = min(0.5 + ($high_count * 0.12), 0.90);
        } elseif ($low_count > 0) {
            $priority = 'low';
            $confidence = min(0.5 + ($low_count * 0.1), 0.85);
        } else {
            $priority = 'medium';
            $confidence = 0.5;
        }
        
        return [
            'category' => 'normal',
            'category_confidence' => 1.0,
            'priority' => $priority,
            'priority_confidence' => round($confidence, 2),
            'source' => 'keyword'
        ];
    }
    
    /**
     * Normalize priority values
     */
    private function normalizePriority($priority) {
        $priority = strtolower(trim($priority));
        $valid = ['critical', 'high', 'medium', 'low'];
        
        if (in_array($priority, $valid)) {
            return $priority;
        }
        
        // Map numeric values (0-3 or 1-4)
        if (is_numeric($priority)) {
            $p = intval($priority);
            $map = [0 => 'low', 1 => 'medium', 2 => 'high', 3 => 'critical'];
            return $map[$p] ?? 'medium';
        }
        
        return 'medium';
    }
    
    /**
     * Update report priority in database
     */
    public function updateReportPriority($report_id, $priority, $confidence, $method = 'nlp_auto', $admin_id = null) {
        // Get previous priority for logging
        $result = $this->db->query("SELECT priority FROM reports WHERE id = " . intval($report_id));
        $row = $result->fetch_assoc();
        $previous_priority = $row['priority'] ?? 'unset';
        
        // Update report
        $update_sql = "UPDATE reports SET priority = '" . $this->db->real_escape_string($priority) . "', 
                       priority_confidence = " . floatval($confidence) . ",
                       updated_at = NOW() 
                       WHERE id = " . intval($report_id);
        
        if ($this->db->query($update_sql)) {
            // Log the change
            $log_sql = "INSERT INTO priority_logs (report_id, previous_priority, new_priority, confidence_score, analyzed_by, analysis_method) 
                        VALUES (" . intval($report_id) . ", '" . $this->db->real_escape_string($previous_priority) . "', 
                        '" . $this->db->real_escape_string($priority) . "', " . floatval($confidence) . ", " . 
                        ($admin_id ? intval($admin_id) : 'NULL') . ", '" . $this->db->real_escape_string($method) . "')";
            
            $this->db->query($log_sql);
            
            // Notify admins if critical or high priority
            if (in_array($priority, ['critical', 'high'])) {
                $this->notifyAdmins($report_id, $priority);
            }
            
            return true;
        }
        
        return false;
    }
    
    /**
     * Notify admins about high priority reports
     */
    private function notifyAdmins($report_id, $priority) {
        $notification_type = ($priority === 'critical') ? 'critical_report' : 'high_priority';
        
        // Get all admin users
        $admins = $this->db->query("SELECT id FROM users WHERE role = 'admin'");
        
        while ($admin = $admins->fetch_assoc()) {
            $insert_sql = "INSERT INTO report_notifications (report_id, admin_id, notification_type) 
                           VALUES (" . intval($report_id) . ", " . intval($admin['id']) . ", '" . $notification_type . "')";
            $this->db->query($insert_sql);
        }
    }
    
    /**
     * Get priority statistics
     */
    public function getPriorityStats() {
        $result = $this->db->query("
            SELECT 
                priority,
                COUNT(*) as count,
                ROUND(COUNT(*) * 100.0 / (SELECT COUNT(*) FROM reports), 2) as percentage
            FROM reports
            GROUP BY priority
            ORDER BY FIELD(priority, 'critical', 'high', 'medium', 'low')
        ");
        
        $stats = [];
        while ($row = $result->fetch_assoc()) {
            $stats[$row['priority']] = $row;
        }
        
        return $stats;
    }
    
    /**
     * Analyze all pending reports (batch operation)
     */
    public function analyzePendingReports() {
        $result = $this->db->query("
            SELECT id, report_title, report_content FROM reports 
            WHERE priority = 'medium' AND status = 'pending'
            ORDER BY created_at DESC LIMIT 10
        ");
        
        $analyzed = 0;
        while ($report = $result->fetch_assoc()) {
            $analysis = $this->analyzeReport($report['report_title'] . ' ' . $report['report_content']);
            $priority = $analysis['priority'] ?? 'medium';
            $confidence = $analysis['priority_confidence'] ?? $analysis['category_confidence'] ?? 0.5;
            if ($this->updateReportPriority($report['id'], $priority, $confidence)) {
                $analyzed++;
            }
        }
        
        return $analyzed;
    }
}

?>
