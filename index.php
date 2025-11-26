<?php
// --- Konfigurasi Zona Waktu ---
date_default_timezone_set('Asia/Jakarta'); 
require_once 'db_connect.php';

$initial_data = [
    'latest' => ['temperature' => 0, 'gas_level' => 0],
    'ai' => ['fire_detected' => false, 'image' => null, 'timestamp' => 0], 
    'history' => ['labels' => [], 'tempData' => [], 'gasData' => []],
];

try {
    // 1. Data Sensor Terakhir
    $stmt = $pdo->query("SELECT temperature, gas_level FROM sensor_readings ORDER BY timestamp DESC LIMIT 1");
    $latest = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($latest) $initial_data['latest'] = $latest;

    // 2. Data AI Terakhir dengan Validasi Waktu
    $stmtAI = $pdo->query("SELECT json_data, image_path, timestamp FROM ai_detections ORDER BY timestamp DESC LIMIT 1");
    $latestAI = $stmtAI->fetch(PDO::FETCH_ASSOC);
    
    if ($latestAI) {
        $ai_json = json_decode($latestAI['json_data'], true);
        $is_fire = (isset($ai_json['fire_detected']) && $ai_json['fire_detected']);
        
        // Hitung selisih waktu (dalam detik)
        $db_time = strtotime($latestAI['timestamp']);
        $now = time();
        $diff = $now - $db_time;

        // Logika: Hanya anggap kebakaran jika data kurang dari 5 menit (300 detik)
        if ($is_fire && $diff <= 300) {
            $initial_data['ai']['fire_detected'] = true;
        } else {
            $initial_data['ai']['fire_detected'] = false; // Reset jika data lama
        }

        $initial_data['ai']['image'] = $latestAI['image_path']; 
        $initial_data['ai']['timestamp'] = $latestAI['timestamp']; // Simpan waktu asli DB
    }

    // 3. Data Grafik Awal
    $sqlHistory = "SELECT DATE_FORMAT(CONVERT_TZ(timestamp, @@session.time_zone, '+07:00'), '%H:%i') as time_label, temperature, gas_level 
                   FROM sensor_readings ORDER BY timestamp DESC LIMIT 24";
    $stmtHistory = $pdo->query($sqlHistory);
    $historyData = $stmtHistory->fetchAll(PDO::FETCH_ASSOC);

    $tempLabels = []; $tempTemp = []; $tempGas = [];
    foreach ($historyData as $row) {
        $tempLabels[] = $row['time_label'];
        $tempTemp[] = $row['temperature'];
        $tempGas[] = $row['gas_level'];
    }
    $initial_data['history']['labels'] = array_reverse($tempLabels);
    $initial_data['history']['tempData'] = array_reverse($tempTemp);
    $initial_data['history']['gasData'] = array_reverse($tempGas);

} catch (PDOException $e) { }

// --- Logika Status Awal (PHP) ---
$gas = $initial_data['latest']['gas_level'];
$temp = $initial_data['latest']['temperature'];
$fire = $initial_data['ai']['fire_detected'];

$gas_cls = ''; $temp_cls = ''; $ai_cls = ''; 
$overall_status = 'normal'; $notif_msg = '';

// Status AI
if ($fire) {
    $ai_cls = 'danger'; 
    $overall_status = 'danger'; 
    $notif_msg = 'DANGER! Fire Detected by AI Camera!';
} else {
    // Jika normal (atau data lama), pastikan kelas kosong
    $ai_cls = '';
}

// Status Sensor
if ($gas > 40) { 
    $gas_cls = 'danger'; 
    if ($overall_status != 'danger') { $overall_status = 'danger'; $notif_msg = 'ALERT! Gas level critical.'; }
} elseif ($gas > 30) { 
    $gas_cls = 'warning'; 
    if ($overall_status == 'normal') { $overall_status = 'warning'; $notif_msg = 'Warning! Gas level high.'; }
}
if ($temp >= 35) { 
    $temp_cls = 'danger'; 
    if ($overall_status != 'danger') { $overall_status = 'danger'; $notif_msg = 'ALERT! Temperature critical.'; }
} elseif ($temp >= 32) { 
    $temp_cls = 'warning'; 
    if ($overall_status == 'normal') { $overall_status = 'warning'; $notif_msg = 'Warning! Temperature high.'; }
}

// Tentukan Gambar Awal
$initial_img_src = 'fire_centered.jpg'; // Default placeholder
// Tampilkan gambar dari DB HANYA jika status api AKTIF (baru)
if ($fire && $initial_data['ai']['image']) {
    $initial_img_src = 'uploads/' . $initial_data['ai']['image'];
}elseif (file_exists('fire_centered.jpg')) {

    $initial_img_src = 'fire_centered.jpg';

}
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
                <li class="nav-item"><a href="setting.php"><i class='bx bx-cog'></i><span>Setting</span></a></li>
            </ul>
        </div>
        <div class="main-content">
            <div class="header">
                <h2>Dashboard</h2>
                <div class="user-info">
                    <img src="https://i.pravatar.cc/30" alt="Admin Avatar" class="avatar">
                    <div class="user-details"><span>User</span></div>
                </div>
            </div>

            <div id="notificationArea" style="display: <?php echo ($overall_status != 'normal' ? 'flex' : 'none'); ?>;" class="notification-banner <?php echo $overall_status; ?>">
                 <i id="notificationIcon" class='bx bxs-error-alt'></i>
                 <div class="notification-text">
                     <strong id="notificationMessage"><?php echo $notif_msg; ?></strong>
                     <span id="notificationSubtext">Checking AI camera...</span>
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
                    <a href="history.php" class="card-link"><div id="gasCard" class="card <?php echo $gas_cls; ?>"><h3 id="gasValue"><?php echo htmlspecialchars(round($gas)); ?></h3><p>Gas Sensor (ppm)</p><p id="gasStatus" class="status-indicator">Latest Reading</p></div></a>
                    <a href="history.php" class="card-link"><div id="tempCard" class="card <?php echo $temp_cls; ?>"><h3><span id="tempValue"><?php echo htmlspecialchars(number_format($temp, 1)); ?></span></h3><p>Heat Sensor (°C)</p><p id="tempStatus" class="status-indicator">Latest Reading</p></div></a>
                    
                    <a href="history.php" class="card-link">
                        <div id="aiCard" class="card <?php echo $ai_cls; ?>">
                            <div class="video-placeholder" style="overflow: hidden; position: relative;">
                                <img id="aiImage" 
                                     src="<?php echo $initial_img_src; ?>" 
                                     alt="AI Feed" 
                                     style="width: 100%; height: 100%; object-fit: cover; position: absolute; top:0; left:0;"
                                     onerror="this.src='fire_centered.jpg'"> 
                            </div>
                            <p>AI Detection</p>
                            <p id="aiStatus" class="status-indicator" style="<?php echo $fire ? 'color:#C72B2B' : 'color:#22A06B'; ?>">
                                <i class='bx <?php echo $fire ? 'bxs-hot' : 'bx-check-circle'; ?>'></i>
                                <?php echo $fire ? 'Fire Detected!' : 'Normal'; ?>
                            </p>
                        </div>
                    </a>

                    <div class="card chart-card">
                        <h3>Heat Sensor History</h3>
                        <div class="chart-controls" id="heat-controls" data-sensor="heat"><span class="active" data-timeframe="hourly">Hourly</span><span data-timeframe="daily">Daily</span><span data-timeframe="weekly">Weekly</span></div>
                        <div class="chart-container"> <canvas id="heatChart" height="250"></canvas> </div>
                    </div>
                    <div class="card chart-card">
                        <h3>Gas Sensor History</h3>
                         <div class="chart-controls" id="gas-controls" data-sensor="gas"><span class="active" data-timeframe="hourly">Hourly</span><span data-timeframe="daily">Daily</span><span data-timeframe="weekly">Weekly</span></div>
                         <div class="chart-container"> <canvas id="gasChart" height="250"></canvas> </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

<script>
    let heatChart = null, gasChart = null, fetchDataInterval = null; 
    const initialLabels = <?php echo json_encode($initial_data['history']['labels']); ?>;
    const initialTempData = <?php echo json_encode($initial_data['history']['tempData']); ?>;
    const initialGasData = <?php echo json_encode($initial_data['history']['gasData']); ?>;

    function getSafeChartContext(id) { const c = document.getElementById(id); return c ? c.getContext('2d') : null; }
    function updateChartXAxis(chart, label) { if(chart) chart.options.scales.x.title = { display: true, text: label }; }
    
    document.addEventListener('DOMContentLoaded', () => {
        const heatCtx = getSafeChartContext('heatChart');
        if (heatCtx) heatChart = new Chart(heatCtx, { type: 'line', data: { labels: initialLabels, datasets: [{ label: 'Temperature (°C)', data: initialTempData, borderColor: 'rgb(255, 99, 132)', backgroundColor: 'rgba(255, 99, 132, 0.2)', tension: 0.1, fill: true }] }, options: { maintainAspectRatio: false, animation: false, scales: { x: { title: { display: true, text: 'Waktu (24 data terakhir)' } }, y: { beginAtZero: false, title: { display: true, text: 'Temperature (°C)'} } } } });
        
        const gasCtx = getSafeChartContext('gasChart');
        if (gasCtx) gasChart = new Chart(gasCtx, { type: 'line', data: { labels: initialLabels, datasets: [{ label: 'Gas Level (ppm)', data: initialGasData, borderColor: 'rgb(54, 162, 235)', backgroundColor: 'rgba(54, 162, 235, 0.2)', tension: 0.1, fill: true }] }, options: { maintainAspectRatio: false, animation: false, scales: { x: { title: { display: true, text: 'Waktu (24 data terakhir)' } }, y: { beginAtZero: true, title: { display: true, text: 'Gas (ppm)'} } } } });

        const sidebar = document.querySelector('.sidebar');
        if (sidebar) sidebar.addEventListener('transitionend', () => { if (heatChart) heatChart.resize(); if (gasChart) gasChart.resize(); });

        startRealtimeUpdates(); 
        document.getElementById('heat-controls').addEventListener('click', handleChartControlClick);
        document.getElementById('gas-controls').addEventListener('click', handleChartControlClick);
    });

    function updateDashboardUI(data) {
        if (!data || !data.latest) return;

        // Update Sensor Values
        const gas = Math.round(data.latest.gas_level);
        const temp = parseFloat(data.latest.temperature).toFixed(1);
        document.getElementById('gasValue').textContent = gas;
        document.getElementById('tempValue').textContent = temp;

        // --- UPDATE LOGIKA AI DENGAN TIMEOUT ---
        let isFire = false;
        let aiImageSrc = 'fire_centered.jpg'; // Default ke placeholder aman

        if (data.ai && data.ai.fire_detected) {
            // Parse timestamp dari database (format YYYY-MM-DD HH:MM:SS)
            // Kita asumsikan waktu server DB dan server PHP sinkron (UTC+7)
            const dbTime = new Date(data.ai.timestamp).getTime();
            const now = new Date().getTime();
            
            // Selisih waktu dalam milidetik
            const diff = now - dbTime;
            const fiveMinutes = 5 * 60 * 1000; // 300.000 ms

            // Jika data kurang dari 5 menit yang lalu, maka VALID KEBAKARAN
            if (diff <= fiveMinutes) {
                isFire = true;
                if (data.ai.image) aiImageSrc = 'uploads/' + data.ai.image;
            }
        }

        // Update Tampilan Kartu AI
        const aiCard = document.getElementById('aiCard');
        const aiImage = document.getElementById('aiImage');
        const aiStatus = document.getElementById('aiStatus');

        if (isFire) {
            aiCard.className = 'card danger';
            aiStatus.style.color = '#C72B2B';
            aiStatus.innerHTML = "<i class='bx bxs-hot'></i> Fire Detected!";
            // Tampilkan gambar kejadian
            aiImage.src = aiImageSrc; 
        } else {
            aiCard.className = 'card'; 
            aiStatus.style.color = '#22A06B';
            aiStatus.innerHTML = "<i class='bx bx-check-circle'></i> Normal";
            // Kembalikan ke gambar default/aman
            aiImage.src = 'fire_centered.jpg?t=' + new Date().getTime();
        }

        // Update Status Global & Notifikasi
        let gCls = '', tCls = '', overall = 'normal', msg = '';
        if (gas > 40) { gCls = 'danger'; overall = 'danger'; msg = 'ALERT! Gas critical.'; }
        else if (gas > 30) { gCls = 'warning'; overall = 'warning'; msg = 'Warning! Gas high.'; }
        
        // Prioritas: Api > Suhu > Gas
        if (isFire) { overall = 'danger'; msg = 'DANGER! Fire Detected by AI!'; } 
        else if (temp >= 35) { tCls = 'danger'; if (overall != 'danger') { overall = 'danger'; msg = 'ALERT! Temp critical.'; } } 
        else if (temp >= 32) { tCls = 'warning'; if (overall == 'normal') { overall = 'warning'; msg = 'Warning! Temp high.'; } }

        document.getElementById('gasCard').className = 'card ' + gCls;
        document.getElementById('tempCard').className = 'card ' + tCls;
        
        const notif = document.getElementById('notificationArea');
        if (overall !== 'normal') {
            notif.className = 'notification-banner ' + overall;
            document.getElementById('notificationMessage').textContent = msg;
            notif.style.display = 'flex';
        } else {
            notif.style.display = 'none';
        }
    }

    function updateChartsRealtime(data) {
        if (data.history && Array.isArray(data.history.labels)) {
            if (heatChart) { heatChart.data.labels = data.history.labels; heatChart.data.datasets[0].data = data.history.tempData; heatChart.update(); }
            if (gasChart) { gasChart.data.labels = data.history.labels; gasChart.data.datasets[0].data = data.history.gasData; gasChart.update(); }
        }
    }

    async function fetchRealtimeData() {
        try {
            const response = await fetch('get_chart_data.php?timeframe=hourly&_=' + new Date().getTime());
            if (response.ok) {
                const data = await response.json();
                updateDashboardUI(data); 
                updateChartsRealtime(data);
            }
        } catch (error) { console.error(error); }
    }
    
    // ... (Fungsi loadHistoricalData, handleChartControlClick, dll SAMA SEPERTI SEBELUMNYA) ...
    async function loadHistoricalData(chart, sensorType, timeframe) {
        try {
            const response = await fetch(`get_chart_data.php?sensor=${sensorType}&timeframe=${timeframe}&_=${new Date().getTime()}`);
            if (!response.ok) return;
            const data = await response.json();
            if (chart) {
                chart.data.labels = data.labels;
                chart.data.datasets[0].data = data.data;
                updateChartXAxis(chart, timeframe === 'daily' ? 'Tanggal (30 Hari)' : 'Minggu (1 Tahun)');
                chart.update();
            }
        } catch (e) {}
    }

    function handleChartControlClick(event) {
        if (event.target.tagName !== 'SPAN') return;
        const btn = event.target;
        if (btn.classList.contains('active')) return;
        
        const container = btn.parentElement;
        const sensor = container.dataset.sensor;
        const timeframe = btn.dataset.timeframe;
        const chart = sensor === 'heat' ? heatChart : gasChart;

        container.querySelector('.active').classList.remove('active');
        btn.classList.add('active');

        if (timeframe === 'hourly') {
            updateChartXAxis(chart, 'Waktu (24 data)');
            fetchRealtimeData(); 
            startRealtimeUpdates();
        } else {
            stopRealtimeUpdates();
            loadHistoricalData(chart, sensor, timeframe);
        }
    }

    function startRealtimeUpdates() { if (fetchDataInterval) return; fetchRealtimeData(); fetchDataInterval = setInterval(fetchRealtimeData, 5000); }
    function stopRealtimeUpdates() { if (fetchDataInterval) { clearInterval(fetchDataInterval); fetchDataInterval = null; } }

    setInterval(function(){
        const el = document.getElementById('realtime-clock');
        if(el) el.innerHTML=new Date().toLocaleTimeString('en-GB', { timeZone: 'Asia/Jakarta' });
    },1000);
</script>
<script src="js/theme.js"></script>
</body>
</html>