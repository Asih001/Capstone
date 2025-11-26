<?php
// File: get_chart_data.php
header('Content-Type: application/json');
require_once 'db_connect.php'; 
date_default_timezone_set('Asia/Jakarta'); 

$timeframe = $_GET['timeframe'] ?? 'hourly';
$response = ['error' => null];

try {
    if ($timeframe === 'hourly') {
        // --- MODE REAL-TIME ---
        $response = [
            'latest' => ['temperature' => 0, 'gas_level' => 0],
            'ai' => ['fire_detected' => false, 'image' => null, 'timestamp' => null], // Tambahan Data AI
            'history' => ['labels' => [], 'tempData' => [], 'gasData' => []],
            'error' => null
        ];

        // 1. Ambil Data Sensor Terakhir
        $stmt = $pdo->query("SELECT temperature, gas_level FROM sensor_readings ORDER BY timestamp DESC LIMIT 1");
        $latest = $stmt->fetch();
        if ($latest) $response['latest'] = $latest;

        // 2. Ambil Data AI Terakhir (TAMBAHAN PENTING)
        $stmtAI = $pdo->query("SELECT json_data, image_path, timestamp FROM ai_detections ORDER BY timestamp DESC LIMIT 1");
        $latestAI = $stmtAI->fetch();
        
        if ($latestAI) {
            $ai_json = json_decode($latestAI['json_data'], true);
            // Cek apakah api terdeteksi (bisa dari key 'fire_detected' atau logika lain)
            $is_fire = false;
            if (isset($ai_json['fire_detected']) && $ai_json['fire_detected']) {
                $is_fire = true;
            }
            
            $response['ai'] = [
                'fire_detected' => $is_fire,
                'image' => $latestAI['image_path'] ? 'uploads/' . $latestAI['image_path'] : null,
                'timestamp' => $latestAI['timestamp']
            ];
        }

        // 3. Ambil Data Riwayat Grafik (UTC+7)
        $sqlHistory = "SELECT DATE_FORMAT(CONVERT_TZ(timestamp, @@session.time_zone, '+07:00'), '%H:%i') as time_label, 
                        temperature, gas_level 
                       FROM sensor_readings 
                       ORDER BY timestamp DESC LIMIT 24";
        $stmtHistory = $pdo->query($sqlHistory);
        $historyData = $stmtHistory->fetchAll();

        $labels = []; $tempData = []; $gasData = [];
        foreach ($historyData as $row) {
            $labels[] = $row['time_label'];
            $tempData[] = $row['temperature'];
            $gasData[] = $row['gas_level'];
        }
        $response['history']['labels'] = array_reverse($labels);
        $response['history']['tempData'] = array_reverse($tempData);
        $response['history']['gasData'] = array_reverse($gasData);

    } else {
        // ... (Bagian Mode Historis Daily/Weekly TETAP SAMA) ...
        $sensor_type = $_GET['sensor'] ?? '';
        $column = ($sensor_type === 'heat') ? 'temperature' : 'gas_level';
        
        // ... (Kode query daily/weekly sama seperti sebelumnya) ...
        if ($timeframe === 'daily') {
            $sql = "SELECT DATE_FORMAT(CONVERT_TZ(timestamp, @@session.time_zone, '+07:00'), '%Y-%m-%d') as time_label, AVG($column) as value
                    FROM sensor_readings WHERE timestamp >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                    GROUP BY time_label ORDER BY time_label ASC";
        } else {
             $sql = "SELECT DATE_FORMAT(CONVERT_TZ(timestamp, @@session.time_zone, '+07:00'), '%Y-W%u') as time_label, AVG($column) as value
                    FROM sensor_readings WHERE timestamp >= DATE_SUB(NOW(), INTERVAL 1 YEAR)
                    GROUP BY time_label ORDER BY time_label ASC";
        }
        $stmt = $pdo->query($sql);
        $data = $stmt->fetchAll();
        $labels = []; $values = [];
        foreach ($data as $row) { $labels[] = $row['time_label']; $values[] = round($row['value'], 1); }
        $response = ['labels' => $labels, 'data' => $values];
    }

} catch (PDOException $e) {
    $response = ['error' => $e->getMessage()]; http_response_code(500);
} catch (Exception $e) {
    $response = ['error' => $e->getMessage()]; http_response_code(400);
}
echo json_encode($response);
?>