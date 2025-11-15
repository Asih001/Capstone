<?php
// Nama file database. File ini akan dibuat di folder yang sama dengan skrip ini.
$db_file = 'monitoring.db'; // Anda bisa menamainya 'amitdb' jika mau

try {
    // Buat (atau buka) koneksi ke database SQLite
    $db = new SQLite3($db_file);
    echo "Koneksi database berhasil dibuka atau dibuat di: " . realpath($db_file) . "<br>";

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

    // Eksekusi perintah SQL
    $db->exec($create_sensor_table_sql);
    echo "Tabel 'sensor_readings' berhasil dibuat atau sudah ada.<br>";

    $db->exec($create_ai_table_sql);
    echo "Tabel 'ai_detections' berhasil dibuat atau sudah ada.<br>";

} catch (Exception $e) {
    die("Error: " . $e->getMessage());
} finally {
    if (isset($db)) {
        $db->close();
        echo "Koneksi database ditutup.<br>";
    }
}
?>