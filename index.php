<?php
// --- Blok Kode untuk Mengambil Data AWAL ---
$db_file = 'database/monitoring.db';
$initial_data = [
    'latest' => ['temperature' => null, 'gas_level' => null], // Use null for initial check
    'history' => ['labels' => [], 'tempData' => [], 'gasData' => []],
];
try {
    $db = new SQLite3($db_file); $db->busyTimeout(5000);
    $query_latest = "SELECT temperature, gas_level FROM sensor_readings ORDER BY timestamp DESC LIMIT 1";
    $latest = $db->querySingle($query_latest, true);
    if ($latest) $initial_data['latest'] = $latest;

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
    $initial_data['history']['labels'] = array_reverse($temp_labels);
    $initial_data['history']['tempData'] = array_reverse($temp_temp_data);
    $initial_data['history']['gasData'] = array_reverse($temp_gas_data);
    $db->close();
} catch (Exception $e) { /* Abaikan error awal */ }
// --- Akhir Blok Data AWAL ---

// --- Logika Status AWAL ---
$gas_level = $initial_data['latest']['gas_level'] ?? 0; // Null coalescing operator
$temperature = $initial_data['latest']['temperature'] ?? 0; // Null coalescing operator
$gas_status_class = ''; $temp_status_class = ''; $overall_status = 'normal'; $notification_message = '';
if ($gas_level > 250) { $gas_status_class = 'danger'; $overall_status = 'danger'; $notification_message = 'ALERT! Gas level critical.'; }
elseif ($gas_level > 150) { $gas_status_class = 'warning'; $overall_status = 'warning'; $notification_message = 'Warning! Gas level high.'; }
if ($temperature >= 30) { $temp_status_class = 'danger'; if ($overall_status != 'danger') { $overall_status = 'danger'; $notification_message = 'ALERT! Temperature critical.'; } }
elseif ($temperature >= 27) { $temp_status_class = 'warning'; if ($overall_status == 'normal') { $overall_status = 'warning'; $notification_message = 'Warning! Temperature high.'; } }
// --- Akhir Logika AWAL ---
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Sistem Monitoring</title>
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <link rel="stylesheet" href="css/style.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style> .notification-banner { transition: opacity 0.5s ease, background-color 0.5s ease; } </style>
</head>
<body>
    <div class="container">
        <div class="sidebar">
             <div class="sidebar-header"><i class='bx bxs-leaf logo-icon'></i><span class="logo-text">Monitoring</span></div>
             <ul class="nav-links">
                 <li class="nav-item active"><a href="index.php"><i class='bx bx-grid-alt'></i><span>Dashboard</span></a></li>
                 <li class="nav-item"><a href="history.php"><i class='bx bx-history'></i><span>History</span></a></li>
                 <li class="nav-item"><a href="settings.php"><i class='bx bx-cog'></i><span>Setting</span></a></li>
             </ul>
             <div class="logout nav-item"><a href="logout.php"><i class='bx bx-log-out'></i><span>Log Out</span></a></div>
        </div>
        <div class="main-content">
            <div class="header">
                 <h2>Dashboard</h2>
                 <div class="user-info">
                     <img src="https://i.pravatar.cc/30" alt="Admin Avatar" class="avatar">
                     <div class="user-details"><span>Admin</span><small>admin@gmail.com</small></div>
                 </div>
            </div>

            <div id="notificationArea" style="display: <?php echo ($overall_status != 'normal' ? 'flex' : 'none'); ?>;" class="notification-banner <?php echo $overall_status; ?>">
                 <i id="notificationIcon" class='bx bxs-error-alt'></i>
                 <div class="notification-text">
                     <strong id="notificationMessage"><?php echo $notification_message ?: 'Status Normal'; ?></strong>
                     <span id="notificationSubtext"><?php echo $overall_status != 'normal' ? 'Checking AI camera...' : ''; ?></span>
                 </div>
            </div>

            <div class="content-body">
                <div class="dashboard-grid">
                     <div class="card weather-card">
                         <i class='bx bx-sun' style="font-size: 36px; color: #ffab00;"></i>
                         <div class="card-content">
                             <h3 id="realtime-clock" style="font-size: 24px;"><?php echo date('H:i:s'); ?></h3>
                             <p>Realtime Insight</p> <p><?php echo date('d F Y'); ?></p>
                         </div>
                     </div>
                     <a href="history.php" class="card-link"><div id="gasCard" class="card <?php echo $gas_status_class; ?>"><h3 id="gasValue"><?php echo htmlspecialchars(round($gas_level)); ?></h3><p>Gas Sensor (ppm)</p><p id="gasStatus" class="status-indicator"><?php if($gas_status_class == 'danger') echo "<i class='bx bxs-hot'></i> Dangerous"; elseif($gas_status_class == 'warning') echo "<i class='bx bxs-error'></i> Warning"; else echo "Latest Reading"; ?></p></div></a>
                     <a href="history.php" class="card-link"><div id="tempCard" class="card <?php echo $temp_status_class; ?>"><h3><span id="tempValue"><?php echo htmlspecialchars(number_format($temperature, 1)); ?></span></h3><p>Heat Sensor (°C)</p><p id="tempStatus" class="status-indicator"><?php if($temp_status_class == 'danger') echo "<i class='bx bxs-hot'></i> Dangerous"; elseif($temp_status_class == 'warning') echo "<i class='bx bxs-error'></i> Warning"; else echo "Latest Reading"; ?></p></div></a>
                     <a href="history.php" class="card-link"><div class="card"><div class="video-placeholder"></div><p>AI Detection</p><p class="status-indicator" style="color: #22A06B;"><i class='bx bx-check-circle'></i>Normal</p></div></a>

                     <div class="card chart-card">
                         <h3>Heat Sensor History (Real-time)</h3>
                         <div class="chart-container"> <canvas id="heatChart"></canvas> </div>
                     </div>
                     <div class="card chart-card">
                         <h3>Gas Sensor History (Real-time)</h3>
                          <div class="chart-container"> <canvas id="gasChart"></canvas> </div>
                     </div>
                </div>
            </div>
        </div>
    </div>

<script>
    // --- Inisialisasi Chart.js ---
    const initialLabels = <?php echo json_encode($initial_data['history']['labels']); ?>;
    const initialTempData = <?php echo json_encode($initial_data['history']['tempData']); ?>;
    const initialGasData = <?php echo json_encode($initial_data['history']['gasData']); ?>;

    function getSafeChartContext(canvasId) {
        const canvas = document.getElementById(canvasId);
        return canvas ? canvas.getContext('2d') : null;
    }

    let heatChart = null, gasChart = null;
    const heatCtx = getSafeChartContext('heatChart');
    if (heatCtx) {
        heatChart = new Chart(heatCtx, { type: 'line', data: { labels: initialLabels, datasets: [{ label: 'Temperature (°C)', data: initialTempData, borderColor: 'rgb(255, 99, 132)', backgroundColor: 'rgba(255, 99, 132, 0.2)', tension: 0.1, fill: true }] }, options: { animation: false, scales: { y: { beginAtZero: false } } } });
    } else { console.error("Canvas 'heatChart' not found."); }

    const gasCtx = getSafeChartContext('gasChart');
    if (gasCtx) {
        gasChart = new Chart(gasCtx, { type: 'line', data: { labels: initialLabels, datasets: [{ label: 'Gas Level (ppm)', data: initialGasData, borderColor: 'rgb(54, 162, 235)', backgroundColor: 'rgba(54, 162, 235, 0.2)', tension: 0.1, fill: true }] }, options: { animation: false, scales: { y: { beginAtZero: true } } } });
    } else { console.error("Canvas 'gasChart' not found."); }


    // --- Fungsi untuk update UI ---
    function updateDashboard(data) {
        // console.log("Received data:", data); // Aktifkan jika perlu debug

        if (data.error || !data || !data.latest || !data.history || !Array.isArray(data.history.labels)) {
            console.error("Error or invalid data received:", data?.error || "Incomplete data");
            // Handle error display
            return;
        }

        // --- Update Kartu Sensor ---
        // PERBAIKAN: Pastikan nilai ada sebelum memformat
        const latestGas = typeof data.latest.gas_level === 'number' ? Math.round(data.latest.gas_level) : 'N/A';
        const latestTemp = typeof data.latest.temperature === 'number' ? data.latest.temperature.toFixed(1) : 'N/A';

        const gasValueEl = document.getElementById('gasValue');
        const tempValueEl = document.getElementById('tempValue');

        if (gasValueEl) gasValueEl.textContent = latestGas;
        if (tempValueEl) tempValueEl.textContent = latestTemp;


        // --- Update Status & Warna Kartu + Notifikasi ---
        let currentGasStatus = ''; let currentTempStatus = ''; let currentOverallStatus = 'normal'; let currentNotification = '';
        // Gunakan nilai numerik yang sudah divalidasi
        const gasNum = typeof data.latest.gas_level === 'number' ? data.latest.gas_level : -1;
        const tempNum = typeof data.latest.temperature === 'number' ? data.latest.temperature : -1;

        if (gasNum > 250) { currentGasStatus = 'danger'; currentOverallStatus = 'danger'; currentNotification = 'ALERT! Gas level critical.'; }
        else if (gasNum > 150) { currentGasStatus = 'warning'; currentOverallStatus = 'warning'; currentNotification = 'Warning! Gas level high.'; }
        if (tempNum >= 30) { currentTempStatus = 'danger'; if (currentOverallStatus != 'danger') { currentOverallStatus = 'danger'; currentNotification = 'ALERT! Temperature critical.'; } }
        else if (tempNum >= 27) { currentTempStatus = 'warning'; if (currentOverallStatus == 'normal') { currentOverallStatus = 'warning'; currentNotification = 'Warning! Temperature high.'; } }

        const gasCard = document.getElementById('gasCard');
        const tempCard = document.getElementById('tempCard');
        const gasStatusEl = document.getElementById('gasStatus');
        const tempStatusEl = document.getElementById('tempStatus');

        if (gasCard) gasCard.className = 'card ' + currentGasStatus;
        if (tempCard) tempCard.className = 'card ' + currentTempStatus;

        if (gasStatusEl) gasStatusEl.innerHTML = currentGasStatus == 'danger' ? "<i class='bx bxs-hot'></i> Dangerous" : (currentGasStatus == 'warning' ? "<i class='bx bxs-error'></i> Warning" : "Latest Reading");
        if (tempStatusEl) tempStatusEl.innerHTML = currentTempStatus == 'danger' ? "<i class='bx bxs-hot'></i> Dangerous" : (currentTempStatus == 'warning' ? "<i class='bx bxs-error'></i> Warning" : "Latest Reading");

        const notifArea = document.getElementById('notificationArea');
        if (notifArea){
            if (currentOverallStatus !== 'normal') {
                notifArea.className = 'notification-banner ' + currentOverallStatus;
                document.getElementById('notificationMessage').textContent = currentNotification;
                notifArea.style.display = 'flex';
            } else {
                 // PERBAIKAN: Sembunyikan jika status normal
                 notifArea.style.display = 'none';
            }
        }

        // --- Update Grafik (Labels & Data) ---
         if (Array.isArray(data.history.labels) && data.history.labels.length > 0) {
             // Pastikan chart sudah terinisialisasi
             if (heatChart && gasChart) {
                 // Update data dan labels
                 heatChart.data.labels = data.history.labels;
                 heatChart.data.datasets[0].data = data.history.tempData;

                 gasChart.data.labels = data.history.labels;
                 gasChart.data.datasets[0].data = data.history.gasData;

                 // Panggil update SETELAH mengubah data
                 heatChart.update();
                 gasChart.update();
             }
         } else {
              console.warn("No valid history labels received for charts.");
         }
    }

    // --- Fungsi Fetch Data Berkala ---
    async function fetchData() {
        try {

            // Ambil data terbaru
            const response = await fetch('get_latest_data.php?_=' + new Date().getTime());
            if (!response.ok) { throw new Error(`HTTP error fetching latest data! status: ${response.status}`); }
            const data = await response.json();
            updateDashboard(data); // Panggil fungsi update
        } catch (error) {
            console.error('Fetch error:', error);
            const notifArea = document.getElementById('notificationArea');
             if (notifArea) { /* Update notifikasi jika fetch gagal */
                notifArea.className = 'notification-banner danger';
                document.getElementById('notificationMessage').textContent = "Failed to fetch data!";
                document.getElementById('notificationSubtext').textContent = error.message;
                notifArea.style.display = 'flex';
             }
        }
    }

    // Jalankan fetch data setiap 5 detik
    const fetchDataInterval = setInterval(fetchData, 5000);

    // Update jam
    const clockInterval = setInterval(function(){
        const clockElement = document.getElementById('realtime-clock');
        if (clockElement) {
           clockElement.innerHTML=new Date().toLocaleTimeString('en-GB');
        } else {
           clearInterval(clockInterval);
           clearInterval(fetchDataInterval);
        }
    },1000);

</script>
<script src="js/theme.js"></script>
</body>
</html>