<?php
// api_receiver.php - Endpoint untuk menerima data dari hardware
header('Content-Type: application/json');

// 1. Konfigurasi Database
$db_file = 'database/monitoring.db';
$response = ['success' => false, 'message' => 'Unknown error'];

// 2. Cek Metode Request (Sebaiknya gunakan POST untuk pengiriman data)
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); // Method Not Allowed
    echo json_encode(['success' => false, 'message' => 'Method Not Allowed. Use POST.']);
    exit;
}

// 3. Ambil Data yang Dikirim
// Opsi A: Jika hardware mengirim data sebagai JSON raw body (paling umum untuk IoT modern)
$json_input = file_get_contents('php://input');
$data = json_decode($json_input, true);

// Opsi B: Jika hardware mengirim sebagai form-data (application/x-www-form-urlencoded)
// $data = $_POST;

// 4. Validasi Data
// Pastikan 'temperature' dan 'gas_level' ada dalam data yang diterima
if (!isset($data['temperature']) || !isset($data['gas_level'])) {
    http_response_code(400); // Bad Request
    echo json_encode(['success' => false, 'message' => 'Incomplete data. "temperature" and "gas_level" are required.']);
    exit;
}

$temp = filter_var($data['temperature'], FILTER_VALIDATE_FLOAT);
$gas = filter_var($data['gas_level'], FILTER_VALIDATE_FLOAT);

if ($temp === false || $gas === false) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid data format. Values must be numbers.']);
    exit;
}

try {
    // 5. Simpan ke Database
    $db = new SQLite3($db_file);
    $db->busyTimeout(5000);

    $stmt = $db->prepare("INSERT INTO sensor_readings (temperature, gas_level) VALUES (:temp, :gas)");
    $stmt->bindValue(':temp', $temp, SQLITE3_FLOAT);
    $stmt->bindValue(':gas', $gas, SQLITE3_FLOAT);

    if ($stmt->execute()) {
        http_response_code(201); // Created
        $response = ['success' => true, 'message' => 'Data inserted successfully'];
    } else {
        throw new Exception("Database insert failed: " . $db->lastErrorMsg());
    }

} catch (Exception $e) {
    http_response_code(500); // Internal Server Error
    $response = ['success' => false, 'message' => $e->getMessage()];
} finally {
    if (isset($db)) {
        $db->close();
    }
}

echo json_encode($response);
?>