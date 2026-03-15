<?php
class NLPService {
    private $db;
    private $nlp_api_url;
    private $nlp_enabled;

    public function __construct($db, $nlp_api_url = null, $nlp_enabled = true) {
        $this->db = $db;
        $this->nlp_api_url = $nlp_api_url ?? getenv('NLP_API_URL') ?: 'http://localhost:5000/analyze';
        $this->nlp_enabled = $nlp_enabled;
    }

    public function analyzeReport($text, $title = '') {
        if ($this->nlp_enabled) {
            $nlp_result = $this->callNLPAPI($text, $title);
            if ($nlp_result !== null) {
                return $nlp_result;
            }
        }
        return $this->keywordBasedAnalysis($text);
    }

    private function callNLPAPI($text, $title = '') {
        try {
            // Send both text and title to the Flask API
            $payload = json_encode([
                'text'  => $text,
                'title' => $title
            ]);

            $ch = curl_init($this->nlp_api_url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT        => 15,
                CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
                CURLOPT_POST           => true,
                CURLOPT_POSTFIELDS     => $payload,
            ]);

            $response  = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            error_log("NLP API Response (HTTP $http_code): " . $response);

            if ($http_code === 200 && $response) {
                $data = json_decode($response, true);

                if (isset($data['priority'])) {
                    return [
                        'category'            => 'normal',
                        'category_confidence' => 1.0,
                        'priority'            => $this->normalizePriority($data['priority']),
                        'priority_confidence' => isset($data['priority_confidence'])
                                                    ? min(round($data['priority_confidence'], 2), 1)
                                                    : 0.5,
                        'source'              => 'nlp',
                        'method'              => $data['method'] ?? 'ml'
                    ];
                }
            }
        } catch (Exception $e) {
            error_log('NLP API Error: ' . $e->getMessage());
        }
        return null;
    }

    private function keywordBasedAnalysis($text) {
        $text_lower = strtolower($text);

        $critical_keywords = ['urgent','emergency','critical','danger','threat','attack','bomb','fire','life-threatening','death','injury','malware','infection','breach','outage','server down'];
        $high_keywords     = ['severe','major','serious','accident','crash','broken','cannot work','fails','error','bug','slow','overload','timeout'];
        $low_keywords      = ['minor','small','slight','question','how','help','assistance','documentation'];

        $critical_count = 0; $high_count = 0; $low_count = 0;

        foreach ($critical_keywords as $kw) { if (strpos($text_lower, $kw) !== false) $critical_count++; }
        foreach ($high_keywords     as $kw) { if (strpos($text_lower, $kw) !== false) $high_count++; }
        foreach ($low_keywords      as $kw) { if (strpos($text_lower, $kw) !== false) $low_count++; }

        if ($critical_count > 0) {
            $priority   = 'critical';
            $confidence = min(0.5 + ($critical_count * 0.15), 0.95);
        } elseif ($high_count > 0) {
            $priority   = 'high';
            $confidence = min(0.5 + ($high_count * 0.12), 0.90);
        } elseif ($low_count > 0) {
            $priority   = 'low';
            $confidence = min(0.5 + ($low_count * 0.1), 0.85);
        } else {
            $priority   = 'medium';
            $confidence = 0.5;
        }

        return [
            'category'            => 'normal',
            'category_confidence' => 1.0,
            'priority'            => $priority,
            'priority_confidence' => round($confidence, 2),
            'source'              => 'keyword'
        ];
    }

    private function normalizePriority($priority) {
        $priority = strtolower(trim($priority));
        $valid    = ['critical', 'high', 'medium', 'low'];
        if (in_array($priority, $valid)) return $priority;
        if (is_numeric($priority)) {
            $map = [0 => 'low', 1 => 'medium', 2 => 'high', 3 => 'critical'];
            return $map[intval($priority)] ?? 'medium';
        }
        return 'medium';
    }

    public function updateReportPriority($report_id, $priority, $confidence, $method = 'nlp_auto', $admin_id = null) {
        $result = $this->db->query("SELECT priority FROM reports WHERE id = " . intval($report_id));
        $row    = $result->fetch_assoc();
        $previous_priority = $row['priority'] ?? 'unset';

        $update_sql = "UPDATE reports SET 
                        priority = '" . $this->db->real_escape_string($priority) . "',
                        priority_confidence = " . floatval($confidence) . ",
                        updated_at = NOW()
                       WHERE id = " . intval($report_id);

        if ($this->db->query($update_sql)) {
            $log_sql = "INSERT INTO priority_logs 
                            (report_id, previous_priority, new_priority, confidence_score, analyzed_by, analysis_method)
                        VALUES (" . intval($report_id) . ",
                                '" . $this->db->real_escape_string($previous_priority) . "',
                                '" . $this->db->real_escape_string($priority) . "',
                                " . floatval($confidence) . ",
                                " . ($admin_id ? intval($admin_id) : 'NULL') . ",
                                '" . $this->db->real_escape_string($method) . "')";
            $this->db->query($log_sql);

            if (in_array($priority, ['critical', 'high'])) {
                $this->notifyAdmins($report_id, $priority);
            }
            return true;
        }
        return false;
    }

    private function notifyAdmins($report_id, $priority) {
        $notification_type = ($priority === 'critical') ? 'critical_report' : 'high_priority';
        $admins = $this->db->query("SELECT id FROM users WHERE role = 'admin'");
        while ($admin = $admins->fetch_assoc()) {
            $this->db->query("INSERT INTO report_notifications (report_id, admin_id, notification_type)
                              VALUES (" . intval($report_id) . ", " . intval($admin['id']) . ", '$notification_type')");
        }
    }

    public function getPriorityStats() {
        $result = $this->db->query("
            SELECT priority, COUNT(*) as count,
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

    public function analyzePendingReports() {
        $result = $this->db->query("
            SELECT id, report_title, report_content FROM reports
            WHERE priority = 'medium' AND status = 'pending'
            ORDER BY created_at DESC LIMIT 10
        ");
        $analyzed = 0;
        while ($report = $result->fetch_assoc()) {
            $analysis   = $this->analyzeReport($report['report_content'], $report['report_title']);
            $priority   = $analysis['priority'] ?? 'medium';
            $confidence = $analysis['priority_confidence'] ?? 0.5;
            if ($this->updateReportPriority($report['id'], $priority, $confidence)) {
                $analyzed++;
            }
        }
        return $analyzed;
    }
}
?>
