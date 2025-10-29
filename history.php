<?php
// session_start();
// if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
//     header('location: login.php');
//     exit;
// }

// --- Blok Kode untuk Mengambil & Menggabungkan Data dari Database ---
$db_file = 'database/monitoring.db';
$db = new SQLite3($db_file);

// Query BARU: Menggabungkan data dari tabel sensor dan tabel AI, lalu diurutkan berdasarkan waktu
$query = "
SELECT 
    id, 
    timestamp, 
    event_type, 
    temperature, 
    gas_level, 
    json_data,
    strftime('%d %B %Y', timestamp, 'localtime') as date,
    strftime('%H:%M', timestamp, 'localtime') as time
FROM (
    -- Ambil data dari tabel sensor
    SELECT id, timestamp, 'Sensor' as event_type, temperature, gas_level, NULL as json_data FROM sensor_readings
    
    UNION ALL -- Gabungkan dengan
    
    -- Ambil data dari tabel AI
    SELECT id, timestamp, 'AI Detection' as event_type, NULL as temperature, NULL as gas_level, json_data FROM ai_detections
) AS combined_events
ORDER BY timestamp DESC
LIMIT 30;
";

$results = $db->query($query);
// --- Akhir Blok Kode Database ---
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>History - Sistem Monitoring</title>
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <div class="container">
        <div class="sidebar">
            <div class="sidebar-header">
                <i class='bx bxs-leaf logo-icon'></i>
                <span class="logo-text">Monitoring</span>
            </div>
            
            <ul class="nav-links">
                <li class="nav-item"><a href="index.php"><i class='bx bx-grid-alt'></i><span>Dashboard</span></a></li>
                <li class="nav-item active"><a href="history.php"><i class='bx bx-history'></i><span>History</span></a></li>
                <li class="nav-item"><a href="setting.php"><i class='bx bx-cog'></i><span>Setting</span></a></li>
            </ul>
            <div class="logout nav-item"><a href="logout.php"><i class='bx bx-log-out'></i><span>Log Out</span></a></div>
        </div>
        <div class="main-content">
            <div class="header">
                <h2>History</h2>
                <div class="user-info">
                    <img src="https://i.pravatar.cc/30" alt="Admin Avatar" class="avatar">
                    <div class="user-details"><span>User</span></div>
                </div>
            </div>
            <div class="content-body">
                 <div class="status-history-card">
                    <div class="card-header"><h3>Status History</h3></div>
                    <div class="table-responsive">
                        <table>
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Time</th>
                                    <th>Input Type</th>
                                    <th>Details</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($row = $results->fetchArray(SQLITE3_ASSOC)): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($row['id']); ?></td>
                                        <td><?php echo htmlspecialchars($row['date']) . ' ' . htmlspecialchars($row['time']); ?></td>
                                        
                                        <?php if ($row['event_type'] == 'Sensor'): ?>
                                            <td>Sensor Reading</td>
                                            <td>
                                                Temp: <strong><?php echo htmlspecialchars(round($row['temperature'], 1)); ?>°C</strong>, 
                                                Gas: <strong><?php echo htmlspecialchars(round($row['gas_level'])); ?> ppm</strong>
                                            </td>
                                            <td>
                                                <?php
                                                // --- LOGIKA STATUS BARU UNTUK SENSOR ---
                                                $status = 'Normal'; $status_class = 'normal';
                                                
                                                // Cek Gas terlebih dahulu
                                                if ($row['gas_level'] > 250) { $status = 'Danger'; $status_class = 'danger'; }
                                                elseif ($row['gas_level'] > 150) { $status = 'Warning'; $status_class = 'warning'; }

                                                // Cek Suhu, bisa menimpa status jika lebih parah
                                                if ($row['temperature'] >= 30) { $status = 'Danger'; $status_class = 'danger'; }
                                                elseif ($row['temperature'] >= 27 && $status_class != 'danger') { $status = 'Warning'; $status_class = 'warning'; }
                                                
                                                echo '<span class="status ' . $status_class . '">' . $status . '</span>';
                                                ?>
                                            </td>
                                        <?php elseif ($row['event_type'] == 'AI Detection'): ?>
                                            <td>AI Detection</td>
                                            <td>
                                                <?php
                                                // --- LOGIKA BARU UNTUK AI ---
                                                $fire_detected = false;
                                                if ($row['json_data']) {
                                                    $ai_data = json_decode($row['json_data']);
                                                    // Cek apakah ada key 'fire_detected' dan nilainya true
                                                    if (isset($ai_data->fire_detected) && $ai_data->fire_detected) {
                                                        $fire_detected = true;
                                                    }
                                                }
                                                echo 'Pengecekan Api: <strong>' . ($fire_detected ? 'Iya' : 'Tidak Ada') . '</strong>';
                                                ?>
                                            </td>
                                            <td>
                                                <?php
                                                if ($fire_detected) {
                                                    echo '<span class="status danger">Danger</span>';
                                                } else {
                                                    echo '<span class="status normal">Normal</span>';
                                                }
                                                ?>
                                            </td>
                                        <?php endif; ?>
                                    </tr>
                                <?php endwhile; ?>
                                <?php $db->close(); ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="table-footer"><span>Showing last 30 events</span></div>
                </div>
            </div>
        </div>
    </div>
    <script src="js/theme.js"></script>
</body>
</html>