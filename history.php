<?php
// session_start();
// Baris pengecekan login dinonaktifkan untuk sementara
// if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
//     header('location: login.php');
//     exit;
// }

// --- Blok Kode BARU untuk Filter Tanggal yang Diperbaiki ---
$db_file = 'database/monitoring.db';
$db = new SQLite3($db_file);
$db->busyTimeout(5000);

// 1. Cek apakah filter tanggal sedang digunakan. Default ke null (tidak ada filter).
$filter_date = $_GET['filter_date'] ?? null; 

// 2. Siapkan query dasar
$base_query = "
SELECT 
    id, timestamp, event_type, temperature, gas_level, json_data,
    strftime('%d %B %Y', timestamp, 'localtime') as date,
    strftime('%H:%M', timestamp, 'localtime') as time
FROM (
    SELECT id, timestamp, 'Sensor' as event_type, temperature, gas_level, NULL as json_data FROM sensor_readings
    UNION ALL
    SELECT id, timestamp, 'AI Detection' as event_type, NULL as temperature, NULL as gas_level, json_data FROM ai_detections
) AS combined_events
";

$where_clause = "";
$limit_clause = "";
$order_clause = " ORDER BY timestamp DESC"; // Selalu urutkan dari terbaru

// 3. Terapkan logika berdasarkan filter
if ($filter_date !== null && !empty($filter_date)) {
    // --- MODE FILTER AKTIF ---
    // Tampilkan semua data untuk tanggal yang dipilih (tanpa LIMIT)
    $page_title = "History untuk " . date('d F Y', strtotime($filter_date));
    $where_clause = " WHERE date(timestamp, 'localtime') = :filter_date ";
    
    $query = $base_query . $where_clause . $order_clause;
    $stmt = $db->prepare($query);
    if (!$stmt) { die("Query preparation failed: " . $db->lastErrorMsg()); }
    $stmt->bindValue(':filter_date', $filter_date, SQLITE3_TEXT);

} else {
    // --- MODE DEFAULT (TIDAK ADA FILTER) ---
    // Tampilkan 100 entri terbaru
    $page_title = "100 History Terbaru";
    $limit_clause = " LIMIT 100"; // Batasi hasil agar tidak overload
    
    $query = $base_query . $order_clause . $limit_clause;
    $stmt = $db->prepare($query);
    if (!$stmt) { die("Query preparation failed: " . $db->lastErrorMsg()); }
}

// 4. Eksekusi query
$results = $stmt->execute();
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
                    <div class="card-header">
                        <h3><?php echo htmlspecialchars($page_title); ?></h3>
                        
                        <div class="filter-bar">
                            <form action="history.php" method="GET" class="date-filter-form">
                                <label for="filter_date">Pilih Tanggal:</label>
                                <input type="date" id="filter_date" name="filter_date" value="<?php echo htmlspecialchars($filter_date ?? ''); ?>">
                                <button type="submit" class="button primary">Filter</button>
                                <a href="history.php" class="button">Reset</a>
                            </form>
                        </div>
                    </div>
                    <div class="table-responsive">
                        <table>
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Waktu</th> <th>Tipe Input</th>
                                    <th>Detail</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $row_count = 0;
                                while ($row = $results->fetchArray(SQLITE3_ASSOC)): 
                                $row_count++;
                                ?>
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
                                                $status = 'Normal'; $status_class = 'normal';
                                                if ($row['gas_level'] > 250) { $status = 'Danger'; $status_class = 'danger'; }
                                                elseif ($row['gas_level'] > 150) { $status = 'Warning'; $status_class = 'warning'; }
                                                if ($row['temperature'] >= 30) { $status = 'Danger'; $status_class = 'danger'; }
                                                elseif ($row['temperature'] >= 27 && $status_class != 'danger') { $status = 'Warning'; $status_class = 'warning'; }
                                                echo '<span class="status ' . $status_class . '">' . $status . '</span>';
                                                ?>
                                            </td>
                                        <?php elseif ($row['event_type'] == 'AI Detection'): ?>
                                            <td>AI Detection</td>
                                            <td>
                                                <?php
                                                $fire_detected = false;
                                                if ($row['json_data']) {
                                                    $ai_data = json_decode($row['json_data']);
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
                                
                                <?php if ($row_count == 0): ?>
                                    <tr>
                                        <td colspan="5" style="text-align: center; color: #888;">
                                            <?php if ($filter_date !== null): ?>
                                                Tidak ada data yang ditemukan untuk tanggal ini.
                                            <?php else: ?>
                                                Database history masih kosong.
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endif; ?>

                                <?php $db->close(); ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="table-footer">
                        <span>
                            <?php 
                            if ($filter_date !== null && !empty($filter_date)) {
                                echo "Menampilkan $row_count hasil untuk tanggal " . htmlspecialchars(date('d F Y', strtotime($filter_date)));
                            } else {
                                echo "Menampilkan $row_count data history terbaru (Maks 100)";
                            }
                            ?>
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script src="js/theme.js"></script>
</body>
</html>