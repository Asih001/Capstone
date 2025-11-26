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
            'ai' => ['fire_detected' => false, 'image' => null], // Slot untuk data AI
            'history' => ['labels' => [], 'tempData' => [], 'gasData' => []],
            'error' => null
        ];

        // 1. Ambil Data Sensor Terakhir
        $stmt = $pdo->query("SELECT temperature, gas_level FROM sensor_readings ORDER BY timestamp DESC LIMIT 1");
        $latest = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($latest) $response['latest'] = $latest;

        // 2. Ambil Data AI Terakhir (INI YANG PENTING UNTUK GAMBAR)
        $stmtAI = $pdo->query("SELECT json_data, image_path FROM ai_detections ORDER BY timestamp DESC LIMIT 1");
        $latestAI = $stmtAI->fetch(PDO::FETCH_ASSOC);
        
        if ($latestAI) {
            // Decode JSON dari kolom json_data
            $ai_json = json_decode($latestAI['json_data'], true);
            
            // Cek status api
            $is_fire = false;
            if (isset($ai_json['fire_detected']) && $ai_json['fire_detected']) {
                $is_fire = true;
            }
            
            // Kirim data ke frontend
            $response['ai'] = [
                'fire_detected' => $is_fire,
                // Kirim nama file saja, nanti JS yang nambahin 'uploads/'
                'image' => $latestAI['image_path'] 
            ];
        }

        // 3. Ambil Data Riwayat Grafik
        $sqlHistory = "SELECT DATE_FORMAT(CONVERT_TZ(timestamp, @@session.time_zone, '+07:00'), '%H:%i') as time_label, 
                        temperature, gas_level 
                       FROM sensor_readings 
                       ORDER BY timestamp DESC LIMIT 24";
        $stmtHistory = $pdo->query($sqlHistory);
        $historyData = $stmtHistory->fetchAll(PDO::FETCH_ASSOC);

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
        // --- MODE HISTORIS (Daily/Weekly) ---
        $sensor_type = $_GET['sensor'] ?? '';
        $column = ($sensor_type === 'heat') ? 'temperature' : 'gas_level';
        $sql = "";
        
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
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $labels = []; $values = [];
        foreach ($data as $row) { $labels[] = $row['time_label']; $values[] = round($row['value'], 1); }
        $response = ['labels' => $labels, 'data' => $values];
    }

} catch (Exception $e) {
    $response = ['error' => $e->getMessage()]; http_response_code(500);
}
echo json_encode($response);
?>