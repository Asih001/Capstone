<?php
// File: api_receiver.php
header('Content-Type: application/json');
require_once 'db_connect.php'; 

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method Not Allowed']);
    exit;
}

// Cek Content-Type header untuk menentukan cara membaca data
$contentType = $_SERVER["CONTENT_TYPE"] ?? '';
$response = ['success' => false, 'message' => 'Unknown error'];

try {
    // =================================================================
    // SKENARIO A: MULTIPART FORM DATA (Dari AI Python + Gambar)
    // =================================================================
    if (strpos($contentType, 'multipart/form-data') !== false) {
        
        $json_payload = $_POST['json_data'] ?? null;
        $image_file = $_FILES['image_file'] ?? null;
        
        if ($json_payload) {
            $image_path_db = null;

            // 1. Proses Upload Gambar (Jika ada)
            if ($image_file && $image_file['error'] === UPLOAD_ERR_OK) {
                $upload_dir = 'uploads/';
                // Buat folder jika belum ada
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0755, true);
                }

                // Nama file unik berdasarkan waktu
                $filename = 'ai_' . time() . '.jpg'; 
                $target_file = $upload_dir . $filename;

                // Pindahkan file dari temp ke folder uploads
                if (move_uploaded_file($image_file['tmp_name'], $target_file)) {
                    $image_path_db = $filename; // Nama ini yang masuk DB
                    
                    // PENTING: Timpa file 'fire_centered.jpg' di root
                    // Ini agar Dashboard langsung menampilkan gambar terbaru tanpa refresh path
                    @copy($target_file, 'fire_centered.jpg'); 
                }
            }

            // 2. Simpan ke Database MySQL
            $sql = "INSERT INTO ai_detections (json_data, image_path) VALUES (:json, :img)";
            $stmt = $pdo->prepare($sql);
            
            if ($stmt->execute([':json' => $json_payload, ':img' => $image_path_db])) {
                http_response_code(201);
                $response = ['success' => true, 'message' => 'AI Data & Image saved successfully'];
            } else {
                throw new Exception("Failed to insert AI data.");
            }
        } else {
            http_response_code(400);
            $response['message'] = 'Missing json_data in multipart request';
        }

    // =================================================================
    // SKENARIO B: RAW JSON BODY (Dari Sensor / Python tanpa gambar)
    // =================================================================
    } else {
        $json_input = file_get_contents('php://input');
        $data = json_decode($json_input, true);

        if (isset($data['temperature']) && isset($data['gas_level'])) {
            // Validasi Angka
            $temp = filter_var($data['temperature'], FILTER_VALIDATE_FLOAT);
            $gas = filter_var($data['gas_level'], FILTER_VALIDATE_FLOAT);

            if ($temp === false || $gas === false) {
                http_response_code(400);
                throw new Exception("Invalid sensor values.");
            }

            // Simpan Sensor ke DB
            $sql = "INSERT INTO sensor_readings (temperature, gas_level) VALUES (:temp, :gas)";
            $stmt = $pdo->prepare($sql);
            
            if ($stmt->execute([':temp' => $temp, ':gas' => $gas])) {
                http_response_code(201);
                $response = ['success' => true, 'message' => 'Sensor data saved'];
            } else {
                throw new Exception("Failed to insert sensor data.");
            }
        } 
        // Fallback jika AI mengirim JSON raw (tanpa gambar)
        elseif (isset($data['json_data'])) {
             $json_string = is_array($data['json_data']) ? json_encode($data['json_data']) : $data['json_data'];
             $sql = "INSERT INTO ai_detections (json_data, image_path) VALUES (:json, NULL)";
             $stmt = $pdo->prepare($sql);
             $stmt->execute([':json' => $json_string]);
             $response = ['success' => true, 'message' => 'AI data (no image) saved'];
        }
        else {
            http_response_code(400);
            $response['message'] = 'Invalid data format';
        }
    }

} catch (Exception $e) {
    http_response_code(500);
    $response = ['success' => false, 'message' => 'Server Error: ' . $e->getMessage()];
}

echo json_encode($response);
?>