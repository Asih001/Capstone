<?php
// File: get_chart_data.php
// API GABUNGAN: Mengurus data real-time (hourly) DAN data historis (daily/weekly)

header('Content-Type: application/json');

$db_file = 'database/monitoring.db';
$response = ['error' => null];

try {
    $db = new SQLite3($db_file);
    $db->busyTimeout(5000);

    // Tentukan mode: 'hourly' (real-time) atau 'daily'/'weekly' (historis)
    $timeframe = $_GET['timeframe'] ?? 'hourly'; // Default ke 'hourly'

    if ($timeframe === 'hourly') {
        // --- MODE HOURLY (REAL-TIME) ---
        // Mengembalikan data 'latest' untuk kartu DAN data 'history' untuk grafik
        $response = [
            'latest' => ['temperature' => 0, 'gas_level' => 0],
            'history' => ['labels' => [], 'tempData' => [], 'gasData' => []],
            'error' => null
        ];

        // 1. Ambil data TERAKHIR untuk kartu
        $query_latest = "SELECT temperature, gas_level FROM sensor_readings ORDER BY timestamp DESC LIMIT 1";
        $latest = $db->querySingle($query_latest, true);
        if ($latest) {
            $response['latest'] = $latest;
        }

        // 2. Ambil data RIWAYAT (24 data terakhir) untuk grafik 'hourly'
        $query_history = "SELECT strftime('%H:%M', timestamp, 'localtime') as time_label, temperature, gas_level 
                          FROM sensor_readings 
                          ORDER BY timestamp DESC 
                          LIMIT 24";
        $history_results = $db->query($query_history);
        
        $temp_labels = []; $temp_temp_data = []; $temp_gas_data = [];
        while ($row = $history_results->fetchArray(SQLITE3_ASSOC)) {
            $temp_labels[] = $row['time_label'];
            $temp_temp_data[] = $row['temperature'];
            $temp_gas_data[] = $row['gas_level'];
        }
        // Balik urutan agar data tertua di awal (cocok untuk chart)
        $response['history']['labels'] = array_reverse($temp_labels);
        $response['history']['tempData'] = array_reverse($temp_temp_data);
        $response['history']['gasData'] = array_reverse($temp_gas_data);

    } else {
        // --- MODE HISTORIS (DAILY / WEEKLY) ---
        // Hanya mengembalikan data untuk satu grafik
        
        $sensor_type = $_GET['sensor'] ?? '';
        if ($sensor_type !== 'heat' && $sensor_type !== 'gas') {
            throw new Exception('Invalid sensor type. Must be "heat" or "gas".');
        }
        $column = ($sensor_type === 'heat') ? 'temperature' : 'gas_level';

        $labels = [];
        $values = [];

        if ($timeframe === 'daily') {
            $query = "SELECT strftime('%Y-%m-%d', timestamp, 'localtime') as time_label, AVG($column) as value
                      FROM sensor_readings
                      WHERE timestamp >= datetime('now', '-30 days', 'localtime')
                      GROUP BY time_label ORDER BY time_label ASC";
        } elseif ($timeframe === 'weekly') {
            $query = "SELECT strftime('%Y-W%W', timestamp, 'localtime') as time_label, AVG($column) as value
                      FROM sensor_readings
                      WHERE timestamp >= datetime('now', '-1 year', 'localtime')
                      GROUP BY time_label ORDER BY time_label ASC";
        } else {
            throw new Exception("Invalid timeframe. Must be 'daily' or 'weekly'.");
        }

        $results = $db->query($query);
        if (!$results) { throw new Exception("Query failed: " . $db->lastErrorMsg()); }

        while ($row = $results->fetchArray(SQLITE3_ASSOC)) {
            $labels[] = $row['time_label'];
            $values[] = round($row['value'], 1);
        }
        $response = ['labels' => $labels, 'data' => $values]; // Respons simpel
    }

} catch (Exception $e) {
    $response = ['error' => $e->getMessage()];
    http_response_code(500);
} finally {
    if (isset($db)) {
        $db->close();
    }
}

echo json_encode($response);
?>