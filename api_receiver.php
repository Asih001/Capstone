<?php

header('Content-Type: application/json');

require_once 'db_connect.php'; 

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); 
    echo json_encode(['success' => false, 'message' => 'Method Not Allowed. Use POST.']);
    exit;
}

$json_input = file_get_contents('php://input');
$data = json_decode($json_input, true);
$response = ['success' => false, 'message' => 'Invalid data structure'];

try {
    if (isset($data['temperature']) && isset($data['gas_level'])) {
        
        $temp = filter_var($data['temperature'], FILTER_VALIDATE_FLOAT);
        $gas = filter_var($data['gas_level'], FILTER_VALIDATE_FLOAT);

        if ($temp === false || $gas === false) {
            http_response_code(400);
            throw new Exception("Invalid sensor values. Must be numbers.");
        }

        $sql = "INSERT INTO sensor_readings (temperature, gas_level) VALUES (:temp, :gas)";
        $stmt = $pdo->prepare($sql);
        
        if ($stmt->execute([':temp' => $temp, ':gas' => $gas])) {
            http_response_code(201); // Created
            $response = ['success' => true, 'message' => 'Sensor data saved to MySQL'];
        } else {
            throw new Exception("Failed to insert sensor data.");
        }

    } elseif (isset($data['json_data'])) {
        
        $sql = "INSERT INTO ai_detections (json_data, image_path) VALUES (:json, :img)";
        $stmt = $pdo->prepare($sql);
        
        // Pastikan json_data formatnya string
        $json_string = is_array($data['json_data']) ? json_encode($data['json_data']) : $data['json_data'];
        $image_path = $data['image_path'] ?? null; // Opsional

        if ($stmt->execute([':json' => $json_string, ':img' => $image_path])) {
            http_response_code(201); // Created
            $response = ['success' => true, 'message' => 'AI data saved to MySQL'];
        } else {
            throw new Exception("Failed to insert AI data.");
        }
        
    } else {
        // Jika data tidak cocok dengan format sensor maupun AI
        http_response_code(400);
        $response['message'] = 'Incomplete data. Required fields missing.';
    }

} catch (Exception $e) {
    http_response_code(500); // Internal Server Error
    $response = ['success' => false, 'message' => 'Server Error: ' . $e->getMessage()];
}

// Kirim balasan ke Hardware
echo json_encode($response);
?>