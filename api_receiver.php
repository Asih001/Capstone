<?php
// File: api_receiver.php
header('Content-Type: application/json');
require_once 'db_connect.php'; 

// Aktifkan error reporting untuk debugging
ini_set('display_errors', 1);
error_reporting(E_ALL);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method Not Allowed']);
    exit;
}

$contentType = $_SERVER["CONTENT_TYPE"] ?? '';
$response = ['success' => false, 'message' => 'Unknown error'];

try {
    // SKENARIO A: MULTIPART FORM DATA (AI + Gambar)
    if (strpos($contentType, 'multipart/form-data') !== false) {
        
        $json_payload = $_POST['json_data'] ?? null;
        $image_file = $_FILES['image_file'] ?? null;
        
        if ($json_payload) {
            $image_path_db = null;

            // Proses Upload Gambar
            if ($image_file && $image_file['error'] === UPLOAD_ERR_OK) {
                // Gunakan path absolut server
                $base_dir = __DIR__; 
                $upload_dir = $base_dir . '/uploads/';
                
                // Buat folder uploads jika belum ada
                if (!is_dir($upload_dir)) {
                    if (!mkdir($upload_dir, 0755, true)) {
                        throw new Exception("Failed to create uploads directory. Check permissions.");
                    }
                }

                // --- PERUBAHAN DI SINI ---
                // FORCE FIX NAME: Selalu gunakan nama 'fire_centered.jpg'
                // Kita abaikan nama asli dari pengirim untuk memastikan konsistensi.
                $filename = 'fire_centered.jpg'; 
                
                $target_file = $upload_dir . $filename;

                // Pindahkan file (akan menimpa/overwrite file lama jika ada)
                if (move_uploaded_file($image_file['tmp_name'], $target_file)) {
                    $image_path_db = $filename; // Simpan 'fire_centered.jpg' ke DB
                    
                    // (Opsional) Copy juga ke root folder jika dashboard lama mengakses root
                    // Jika tidak perlu, baris copy() ini bisa dihapus.
                    $main_image_path = $base_dir . '/fire_centered.jpg';
                    copy($target_file, $main_image_path); 
                } else {
                      throw new Exception("Failed to move uploaded file. Check folder permissions.");
                }
            }

            // Simpan ke DB
            $sql = "INSERT INTO ai_detections (json_data, image_path) VALUES (:json, :img)";
            $stmt = $pdo->prepare($sql);
            if ($stmt->execute([':json' => $json_payload, ':img' => $image_path_db])) {
                http_response_code(201);
                $response = ['success' => true, 'message' => 'AI Data & Image saved as ' . $image_path_db];
            } else {
                throw new Exception("Failed to insert AI data DB.");
            }
        } else {
            http_response_code(400);
            $response['message'] = 'Missing json_data';
        }

    // SKENARIO B: RAW JSON (Sensor / Data tanpa gambar)
    } else {
        $json_input = file_get_contents('php://input');
        $data = json_decode($json_input, true);

        if (isset($data['temperature'])) {
            $sql = "INSERT INTO sensor_readings (temperature, gas_level) VALUES (:temp, :gas)";
            $stmt = $pdo->prepare($sql);
            if ($stmt->execute([':temp' => $data['temperature'], ':gas' => $data['gas_level']])) {
                http_response_code(201);
                $response = ['success' => true, 'message' => 'Sensor data saved'];
            }
        } elseif (isset($data['json_data'])) {
             // Fallback AI tanpa gambar
             $json_string = is_array($data['json_data']) ? json_encode($data['json_data']) : $data['json_data'];
             $sql = "INSERT INTO ai_detections (json_data, image_path) VALUES (:json, NULL)";
             $stmt = $pdo->prepare($sql);
             $stmt->execute([':json' => $json_string]);
             $response = ['success' => true, 'message' => 'AI data (no img) saved'];
        }
    }

} catch (Exception $e) {
    http_response_code(500);
    $response = ['success' => false, 'message' => 'Server Error: ' . $e->getMessage()];
}

echo json_encode($response);
?>