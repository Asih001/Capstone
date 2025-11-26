<?php
// File: get_chart_data.php
// API GABUNGAN: Mengurus data real-time (hourly) DAN data historis (daily/weekly) dengan MySQL

header('Content-Type: application/json');
require_once 'db_connect.php'; 

// Set timezone PHP ke WIB (UTC+7)
date_default_timezone_set('Asia/Jakarta'); 

$timeframe = $_GET['timeframe'] ?? 'hourly';
$response = ['error' => null];

try {
    if ($timeframe === 'hourly') {
        $response = [
            'latest' => ['temperature' => 0, 'gas_level' => 0],
            'history' => ['labels' => [], 'tempData' => [], 'gasData' => []],
            'error' => null
        ];

        // 1. Ambil data TERAKHIR (tidak perlu convert TZ karena diambil mentah)
        $stmt = $pdo->query("SELECT temperature, gas_level FROM sensor_readings ORDER BY timestamp DESC LIMIT 1");
        $latest = $stmt->fetch();
        if ($latest) {
            $response['latest'] = $latest;
        }

        // 2. Ambil data RIWAYAT dengan Konversi Waktu ke UTC+7 (WIB)
        // Menggunakan CONVERT_TZ(kolom, dari_zona_server, ke_zona_+07:00)
        $sqlHistory = "SELECT 
                        DATE_FORMAT(CONVERT_TZ(timestamp, @@session.time_zone, '+07:00'), '%H:%i') as time_label, 
                        temperature, 
                        gas_level 
                       FROM sensor_readings 
                       ORDER BY timestamp DESC 
                       LIMIT 24";
                       
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
        // --- MODE HISTORIS ---
        $sensor_type = $_GET['sensor'] ?? '';
        if ($sensor_type !== 'heat' && $sensor_type !== 'gas') {
            throw new Exception('Invalid sensor type. Must be "heat" or "gas".');
        }
        $column = ($sensor_type === 'heat') ? 'temperature' : 'gas_level';

        $labels = [];
        $values = [];
        $sql = "";

        if ($timeframe === 'daily') {
            // Rata-rata harian (UTC+7)
            $sql = "SELECT 
                        DATE_FORMAT(CONVERT_TZ(timestamp, @@session.time_zone, '+07:00'), '%Y-%m-%d') as time_label, 
                        AVG($column) as value
                    FROM sensor_readings
                    WHERE timestamp >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                    GROUP BY time_label 
                    ORDER BY time_label ASC";
                    
        } elseif ($timeframe === 'weekly') {
            // Rata-rata mingguan (UTC+7)
            $sql = "SELECT 
                        DATE_FORMAT(CONVERT_TZ(timestamp, @@session.time_zone, '+07:00'), '%Y-W%u') as time_label, 
                        AVG($column) as value
                    FROM sensor_readings
                    WHERE timestamp >= DATE_SUB(NOW(), INTERVAL 1 YEAR)
                    GROUP BY time_label 
                    ORDER BY time_label ASC";
        } else {
            throw new Exception("Invalid timeframe.");
        }

        $stmt = $pdo->query($sql);
        $data = $stmt->fetchAll();

        foreach ($data as $row) {
            $labels[] = $row['time_label'];
            $values[] = round($row['value'], 1);
        }
        $response = ['labels' => $labels, 'data' => $values];
    }

} catch (PDOException $e) {
    $response = ['error' => $e->getMessage()];
    http_response_code(500);
} catch (Exception $e) {
    $response = ['error' => $e->getMessage()];
    http_response_code(400);
}

echo json_encode($response);
?>