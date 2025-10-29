<?php
header('Content-Type: application/json'); // Penting! Beri tahu browser ini JSON

$db_file = 'database/monitoring.db';
$response = [
    'latest' => ['temperature' => 0, 'gas_level' => 0, 'timestamp' => null, 'time_label' => 'N/A'],
    'history' => ['labels' => [], 'tempData' => [], 'gasData' => []],
    'error' => null
];

try {
    $db = new SQLite3($db_file);
    $db->busyTimeout(5000);

    // 1. Ambil data TERAKHIR
    $query_latest = "SELECT temperature, gas_level, timestamp, strftime('%H:%M', timestamp, 'localtime') as time_label 
                     FROM sensor_readings 
                     ORDER BY timestamp DESC LIMIT 1";
    $latest = $db->querySingle($query_latest, true);
    if ($latest) {
        $response['latest'] = $latest;
    }

    // 2. Ambil data RIWAYAT (misal: 24 data terakhir untuk grafik)
    $query_history = "SELECT strftime('%H:%M', timestamp, 'localtime') as time_label, temperature, gas_level 
                      FROM sensor_readings 
                      ORDER BY timestamp DESC 
                      LIMIT 24"; // Ambil 24 data terakhir
    $history_results = $db->query($query_history);

    $temp_labels = [];
    $temp_temp_data = [];
    $temp_gas_data = [];
    while ($row = $history_results->fetchArray(SQLITE3_ASSOC)) {
        $temp_labels[] = $row['time_label'];
        $temp_temp_data[] = $row['temperature'];
        $temp_gas_data[] = $row['gas_level'];
    }
    // Balik urutan array agar data tertua di awal (cocok untuk chart)
    $response['history']['labels'] = array_reverse($temp_labels);
    $response['history']['tempData'] = array_reverse($temp_temp_data);
    $response['history']['gasData'] = array_reverse($temp_gas_data);


} catch (Exception $e) {
    $response['error'] = "Database Error: " . $e->getMessage();
} finally {
    if (isset($db)) {
        $db->close();
    }
}

// Kembalikan data dalam format JSON
echo json_encode($response);
?>