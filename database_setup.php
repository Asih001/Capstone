<?php
// Tentukan path ke folder database relatif dari lokasi skrip ini (folder utama)
$db_folder = 'database'; 
$db_file = $db_folder . '/monitoring.db'; // Nama file database di dalam folder 'database'

// Cek folder database
if (!is_dir($db_folder)) {
    // Coba buat folder jika belum ada
    if (!mkdir($db_folder, 0777, true)) {
         die("Error: Gagal membuat folder 'database'. Harap buat secara manual.");
    }
    echo "Folder 'database' berhasil dibuat.<br>";
}
if (!is_writable($db_folder)) {
    die("Error: Folder 'database' tidak dapat ditulis. Periksa izin folder.");
}

echo "Mencoba membuka/membuat database di: " . realpath($db_folder) . DIRECTORY_SEPARATOR . basename($db_file) . "<br>";

try {
    // Buat (atau buka) koneksi ke database SQLite
    $db = new SQLite3($db_file);
    // Atur timeout agar tidak cepat error jika file dikunci sementara
    $db->busyTimeout(5000); 
    echo "Koneksi database berhasil dibuka atau dibuat.<br>";

    // SQL untuk Membuat Tabel Sensor
    $create_sensor_table_sql = "
    CREATE TABLE IF NOT EXISTS sensor_readings (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        timestamp DATETIME DEFAULT CURRENT_TIMESTAMP,
        temperature REAL NOT NULL,
        gas_level REAL NOT NULL
    );";

    // SQL untuk Membuat Tabel AI
    $create_ai_table_sql = "
    CREATE TABLE IF NOT EXISTS ai_detections (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        timestamp DATETIME DEFAULT CURRENT_TIMESTAMP,
        json_data TEXT NOT NULL,
        image_path TEXT
    );";

    // Eksekusi pembuatan tabel
    if ($db->exec($create_sensor_table_sql)) {
        echo "Tabel 'sensor_readings' berhasil dibuat atau sudah ada.<br>";
    } else {
        throw new Exception("Gagal membuat tabel 'sensor_readings'. Error: " . $db->lastErrorMsg());
    }
    
    if ($db->exec($create_ai_table_sql)) {
        echo "Tabel 'ai_detections' berhasil dibuat atau sudah ada.<br>";
    } else {
         throw new Exception("Gagal membuat tabel 'ai_detections'. Error: " . $db->lastErrorMsg());
    }

    // --- Cek dan Masukkan Data Percobaan (Opsional di sini, bisa pakai fill_dummy_data.php) ---
    // Kode ini bisa diaktifkan jika ingin setup dan isi data sekaligus
    /*
    echo "Mengecek jumlah data di 'sensor_readings'...<br>";
    $count = $db->querySingle("SELECT COUNT(*) FROM sensor_readings");
    echo "Jumlah data saat ini: " . $count . "<br>";

    if ($count == 0) {
        // ... (kode insert data dummy seperti sebelumnya) ...
        echo "Memulai proses memasukkan data percobaan...<br>";
        $db->exec('BEGIN'); 
        $stmt = $db->prepare("INSERT INTO sensor_readings (timestamp, temperature, gas_level) VALUES (:ts, :temp, :gas)");
        // ... (loop for ... $stmt->execute() ... ) ...
        $db->exec('COMMIT');
        echo "Data percobaan berhasil dimasukkan.<br>";
    } else {
        echo "Tabel 'sensor_readings' sudah berisi data.<br>";
    }
    */
    // --- Akhir Cek Data Percobaan ---

} catch (Exception $e) {
    die("Error: " . $e->getMessage());
} finally {
    if (isset($db)) {
        $db->close();
        echo "Koneksi database ditutup.<br>";
    }
}

echo "<br>Setup database selesai. Anda bisa melanjutkan ke <a href='fill_dummy_data.php'>pengisian data dummy</a> atau <a href='index.php'>lihat dashboard</a>.";
?>