<?php
date_default_timezone_set('Asia/Jakarta'); 
require_once 'db_connect.php';

$initial_data = [
    'latest' => ['temperature' => 0, 'gas_level' => 0],
    'ai' => ['fire_detected' => false, 'image' => null], 
    'history' => ['labels' => [], 'tempData' => [], 'gasData' => []],
];

try {
    // 1. Data Sensor
    $stmt = $pdo->query("SELECT temperature, gas_level FROM sensor_readings ORDER BY timestamp DESC LIMIT 1");
    $latest = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($latest) $initial_data['latest'] = $latest;

    // 2. Data AI (Ambil path gambar)
    $stmtAI = $pdo->query("SELECT json_data, image_path FROM ai_detections ORDER BY timestamp DESC LIMIT 1");
    $latestAI = $stmtAI->fetch(PDO::FETCH_ASSOC);
    if ($latestAI) {
        $ai_json = json_decode($latestAI['json_data'], true);
        $initial_data['ai']['fire_detected'] = (isset($ai_json['fire_detected']) && $ai_json['fire_detected']);
        $initial_data['ai']['image'] = $latestAI['image_path']; // Simpan nama file saja
    }

    // 3. Data Grafik
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

// Logika Status Awal PHP (Sama seperti sebelumnya)
$gas = $initial_data['latest']['gas_level'];
$temp = $initial_data['latest']['temperature'];
$fire = $initial_data['ai']['fire_detected'];
$gas_cls = ''; $temp_cls = ''; $ai_cls = ''; $overall_status = 'normal'; $notif_msg = '';

if ($fire) { $ai_cls = 'danger'; $overall_status = 'danger'; $notif_msg = 'DANGER! Fire Detected by AI Camera!'; }
if ($gas > 250) { $gas_cls = 'danger'; if ($overall_status != 'danger') { $overall_status = 'danger'; $notif_msg = 'ALERT! Gas level critical.'; } } 
elseif ($gas > 150) { $gas_cls = 'warning'; if ($overall_status == 'normal') { $overall_status = 'warning'; $notif_msg = 'Warning! Gas level high.'; } }
if ($temp >= 30) { $temp_cls = 'danger'; if ($overall_status != 'danger') { $overall_status = 'danger'; $notif_msg = 'ALERT! Temperature critical.'; } } 
elseif ($temp >= 27) { $temp_cls = 'warning'; if ($overall_status == 'normal') { $overall_status = 'warning'; $notif_msg = 'Warning! Temperature high.'; } }

// Tentukan sumber gambar awal
$initial_img_src = 'fire_centered.jpg'; // Default
if ($initial_data['ai']['image']) {
    // Jika database punya record gambar, gunakan itu dari folder uploads
    $initial_img_src = 'uploads/' . $initial_data['ai']['image'];
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
            <div class="logout nav-item"><a href="logout.php"><i class='bx bx-log-out'></i><span>Log Out</span></a></div>
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
                                     onerror="this.src='fire_centered.jpg'"> </div>
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

        // 1. Update Nilai Sensor
        const gas = Math.round(data.latest.gas_level);
        const temp = parseFloat(data.latest.temperature).toFixed(1);
        document.getElementById('gasValue').textContent = gas;
        document.getElementById('tempValue').textContent = temp;

        // 2. Update Kartu AI (LOGIKA GAMBAR DIPERBAIKI)
        if (data.ai) {
            const aiCard = document.getElementById('aiCard');
            const aiImage = document.getElementById('aiImage');
            const aiStatus = document.getElementById('aiStatus');
            const isFire = data.ai.fire_detected;

            // Update Warna & Teks Status
            if (isFire) {
                aiCard.className = 'card danger';
                aiStatus.style.color = '#C72B2B';
                aiStatus.innerHTML = "<i class='bx bxs-hot'></i> Fire Detected!";
            } else {
                aiCard.className = 'card'; 
                aiStatus.style.color = '#22A06B';
                aiStatus.innerHTML = "<i class='bx bx-check-circle'></i> Normal";
            }

            // Update Gambar
            // Jika DB mengirim nama file (contoh: 'ai_17326099.jpg'), kita tambahkan prefix 'uploads/'
            // Jika DB null, kita gunakan 'fire_centered.jpg' yang ada di root
            let newSrc = 'fire_centered.jpg'; 
            if (data.ai.image) {
                newSrc = 'uploads/' + data.ai.image;
            }
            // Tambahkan timestamp agar browser tidak cache gambar lama
            aiImage.src = newSrc + '?t=' + new Date().getTime();
        }

        // 3. Logika Status & Notifikasi (Sama seperti sebelumnya)
        let gCls = '', tCls = '', overall = 'normal', msg = '';
        if (gas > 250) { gCls = 'danger'; overall = 'danger'; msg = 'ALERT! Gas critical.'; }
        else if (gas > 150) { gCls = 'warning'; overall = 'warning'; msg = 'Warning! Gas high.'; }
        
        if (data.ai && data.ai.fire_detected) { overall = 'danger'; msg = 'DANGER! Fire Detected by AI!'; } 
        else if (temp >= 30) { tCls = 'danger'; if (overall != 'danger') { overall = 'danger'; msg = 'ALERT! Temp critical.'; } } 
        else if (temp >= 27) { tCls = 'warning'; if (overall == 'normal') { overall = 'warning'; msg = 'Warning! Temp high.'; } }

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