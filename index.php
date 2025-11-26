<?php
// --- Konfigurasi Zona Waktu (PHP) ---
date_default_timezone_set('Asia/Jakarta'); // Set ke UTC+7 (WIB)

// --- Blok Kode untuk Mengambil Data AWAL (MySQL Version) ---
require_once 'db_connect.php';

$initial_data = [
    'latest' => ['temperature' => 0, 'gas_level' => 0],
    'history' => ['labels' => [], 'tempData' => [], 'gasData' => []],
];

try {
    // 1. Ambil Data Terakhir
    $query_latest = "SELECT temperature, gas_level FROM sensor_readings ORDER BY timestamp DESC LIMIT 1";
    $stmt_latest = $pdo->query($query_latest);
    $latest = $stmt_latest->fetch(PDO::FETCH_ASSOC);
    
    if ($latest) {
        $initial_data['latest'] = $latest;
    }

    // 2. Ambil Data Riwayat (24 data terakhir untuk grafik awal)
    // Konversi timestamp ke UTC+7 (+07:00) sebelum di-format
    $query_history = "SELECT DATE_FORMAT(CONVERT_TZ(timestamp, @@session.time_zone, '+07:00'), '%H:%i') as time_label, 
                             temperature, gas_level
                      FROM sensor_readings
                      ORDER BY timestamp DESC
                      LIMIT 24";
    
    $stmt_history = $pdo->query($query_history);
    
    $temp_labels = []; 
    $temp_temp_data = []; 
    $temp_gas_data = [];

    while ($row = $stmt_history->fetch(PDO::FETCH_ASSOC)) {
        $temp_labels[] = $row['time_label'];
        $temp_temp_data[] = $row['temperature'];
        $temp_gas_data[] = $row['gas_level'];
    }

    $initial_data['history']['labels'] = array_reverse($temp_labels);
    $initial_data['history']['tempData'] = array_reverse($temp_temp_data);
    $initial_data['history']['gasData'] = array_reverse($temp_gas_data);

} catch (PDOException $e) {
    // Error silent
}
// --- Akhir Blok Data AWAL ---

// --- Logika Status AWAL ---
$gas_level = $initial_data['latest']['gas_level'];
$temperature = $initial_data['latest']['temperature'];
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
                     <strong id="notificationMessage"><?php echo $notification_message; ?></strong>
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
                    <a href="history.php" class="card-link"><div id="gasCard" class="card <?php echo $gas_status_class; ?>"><h3 id="gasValue"><?php echo htmlspecialchars(round($gas_level)); ?></h3><p>Gas Sensor (ppm)</p><p id="gasStatus" class="status-indicator"><?php if($gas_status_class == 'danger') echo "<i class='bx bxs-hot'></i> Dangerous"; elseif($gas_status_class == 'warning') echo "<i class='bx bxs-error'></i> Warning"; else echo "Latest Reading"; ?></p></div></a>
                    <a href="history.php" class="card-link"><div id="tempCard" class="card <?php echo $temp_status_class; ?>"><h3><span id="tempValue"><?php echo htmlspecialchars(number_format($temperature, 1)); ?></span></h3><p>Heat Sensor (°C)</p><p id="tempStatus" class="status-indicator"><?php if($temp_status_class == 'danger') echo "<i class='bx bxs-hot'></i> Dangerous"; elseif($temp_status_class == 'warning') echo "<i class='bx bxs-error'></i> Warning"; else echo "Latest Reading"; ?></p></div></a>
                    <a href="history.php" class="card-link"><div class="card"><div class="video-placeholder"></div><p>AI Detection</p><p class="status-indicator" style="color: #22A06B;"><i class='bx bx-check-circle'></i>Normal</p></div></a>

                    <div class="card chart-card">
                        <h3>Heat Sensor History</h3>
                        <div class="chart-controls" id="heat-controls" data-sensor="heat">
                            <span class="active" data-timeframe="hourly">Hourly</span>
                            <span data-timeframe="daily">Daily</span>
                            <span data-timeframe="weekly">Weekly</span>
                        </div>
                        <div class="chart-container"> <canvas id="heatChart" height="250"></canvas> </div>
                    </div>
                    <div class="card chart-card">
                        <h3>Gas Sensor History</h3>
                         <div class="chart-controls" id="gas-controls" data-sensor="gas">
                            <span class="active" data-timeframe="hourly">Hourly</span>
                            <span data-timeframe="daily">Daily</span>
                            <span data-timeframe="weekly">Weekly</span>
                        </div>
                         <div class="chart-container"> <canvas id="gasChart" height="250"></canvas> </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

<script>
    // --- Variabel Global ---
    let heatChart = null, gasChart = null;
    let fetchDataInterval = null; 

    // --- Inisialisasi Chart.js ---
    const initialLabels = <?php echo json_encode($initial_data['history']['labels']); ?>;
    const initialTempData = <?php echo json_encode($initial_data['history']['tempData']); ?>;
    const initialGasData = <?php echo json_encode($initial_data['history']['gasData']); ?>;

    function getSafeChartContext(canvasId) {
        const canvas = document.getElementById(canvasId);
        return canvas ? canvas.getContext('2d') : null;
    }

    function updateChartXAxis(chart, newLabel) {
        if (chart && chart.options.scales.x.title) {
            chart.options.scales.x.title.text = newLabel;
        } else if (chart) {
            chart.options.scales.x.title = { display: true, text: newLabel };
        }
    }
    
    document.addEventListener('DOMContentLoaded', () => {
        const heatCtx = getSafeChartContext('heatChart');
        if (heatCtx) {
            heatChart = new Chart(heatCtx, { 
                type: 'line', 
                data: { 
                    labels: initialLabels, 
                    datasets: [{ label: 'Temperature (°C)', data: initialTempData, borderColor: 'rgb(255, 99, 132)', backgroundColor: 'rgba(255, 99, 132, 0.2)', tension: 0.1, fill: true }] 
                }, 
                options: { 
                    maintainAspectRatio: false, 
                    animation: false, 
                    scales: { 
                        x: { title: { display: true, text: 'Waktu (24 data terakhir)' } }, 
                        y: { beginAtZero: false, title: { display: true, text: 'Temperature (°C)'} } 
                    } 
                } 
            });
        }
        const gasCtx = getSafeChartContext('gasChart');
        if (gasCtx) {
            gasChart = new Chart(gasCtx, { 
                type: 'line', 
                data: { 
                    labels: initialLabels, 
                    datasets: [{ label: 'Gas Level (ppm)', data: initialGasData, borderColor: 'rgb(54, 162, 235)', backgroundColor: 'rgba(54, 162, 235, 0.2)', tension: 0.1, fill: true }] 
                }, 
                options: { 
                    maintainAspectRatio: false, 
                    animation: false, 
                    scales: { 
                        x: { title: { display: true, text: 'Waktu (24 data terakhir)' } }, 
                        y: { beginAtZero: true, title: { display: true, text: 'Gas (ppm)'} } 
                    } 
                } 
            });
        }

        const sidebar = document.querySelector('.sidebar');
        if (sidebar) {
            sidebar.addEventListener('transitionend', () => {
                if (heatChart) heatChart.resize();
                if (gasChart) gasChart.resize();
            });
        }

        startRealtimeUpdates(); 

        document.getElementById('heat-controls').addEventListener('click', handleChartControlClick);
        document.getElementById('gas-controls').addEventListener('click', handleChartControlClick);
    });

    function updateDashboardUI(data) {
        if (!data || !data.latest) { console.error("Invalid data for UI update"); return; }
        const latestGas = Math.round(data.latest.gas_level);
        const latestTemp = typeof data.latest.temperature === 'number' ? data.latest.temperature.toFixed(1) : 'N/A';
        
        const gasValueEl = document.getElementById('gasValue');
        const tempValueEl = document.getElementById('tempValue');
        if (gasValueEl) gasValueEl.textContent = latestGas;
        if (tempValueEl) tempValueEl.textContent = latestTemp;

        let currentGasStatus = ''; let currentTempStatus = ''; let currentOverallStatus = 'normal'; let currentNotification = '';
        if (latestGas > 250) { currentGasStatus = 'danger'; currentOverallStatus = 'danger'; currentNotification = 'ALERT! Gas level critical.'; }
        else if (latestGas > 150) { currentGasStatus = 'warning'; currentOverallStatus = 'warning'; currentNotification = 'Warning! Gas level high.'; }
        if (latestTemp >= 30) { currentTempStatus = 'danger'; if (currentOverallStatus != 'danger') { currentOverallStatus = 'danger'; currentNotification = 'ALERT! Temperature critical.'; } }
        else if (latestTemp >= 27) { currentTempStatus = 'warning'; if (currentOverallStatus == 'normal') { currentOverallStatus = 'warning'; currentNotification = 'Warning! Temperature high.'; } }

        document.getElementById('gasCard').className = 'card ' + currentGasStatus;
        document.getElementById('tempCard').className = 'card ' + currentTempStatus;
        document.getElementById('gasStatus').innerHTML = currentGasStatus == 'danger' ? "<i class='bx bxs-hot'></i> Dangerous" : (currentGasStatus == 'warning' ? "<i class='bx bxs-error'></i> Warning" : "Latest Reading");
        document.getElementById('tempStatus').innerHTML = currentTempStatus == 'danger' ? "<i class='bx bxs-hot'></i> Dangerous" : (currentTempStatus == 'warning' ? "<i class='bx bxs-error'></i> Warning" : "Latest Reading");

        const notifArea = document.getElementById('notificationArea');
        if (currentOverallStatus !== 'normal') {
            notifArea.className = 'notification-banner ' + currentOverallStatus;
            document.getElementById('notificationMessage').textContent = currentNotification;
            notifArea.style.display = 'flex';
        } else {
            notifArea.style.display = 'none';
        }
    }

    function updateChartsRealtime(data) {
        if (!data || !data.history || !Array.isArray(data.history.labels)) { console.error("Invalid data for chart update"); return; }
        
        if (heatChart) {
            heatChart.data.labels = data.history.labels;
            heatChart.data.datasets[0].data = data.history.tempData;
            heatChart.update();
        }
        if (gasChart) {
            gasChart.data.labels = data.history.labels;
            gasChart.data.datasets[0].data = data.history.gasData;
            gasChart.update();
        }
    }

    async function fetchRealtimeData() {
        try {
            // Panggilan ke API gabungan (MySQL)
            const response = await fetch('get_chart_data.php?timeframe=hourly&_=' + new Date().getTime());
            if (!response.ok) { throw new Error(`HTTP error! status: ${response.status}`); }
            const data = await response.json();
            
            if (data.error) { throw new Error(data.error); }

            updateDashboardUI(data); 
            updateChartsRealtime(data);
        } catch (error) {
            console.error('Fetch error (Realtime):', error);
        }
    }
    
    async function loadHistoricalData(chart, sensorType, timeframe) {
        try {
            const response = await fetch(`get_chart_data.php?sensor=${sensorType}&timeframe=${timeframe}&_=${new Date().getTime()}`);
            if (!response.ok) { throw new Error(`HTTP error! status: ${response.status}`); }
            const data = await response.json();

            if (data.error) { throw new Error(data.error); }

            if (chart) {
                chart.data.labels = data.labels;
                chart.data.datasets[0].data = data.data;
                const newXAxisLabel = (timeframe === 'daily') ? 'Tanggal (30 Hari Terakhir)' : 'Minggu (1 Tahun Terakhir)';
                updateChartXAxis(chart, newXAxisLabel);
                chart.update();
            }
        } catch (error) {
            console.error(`Failed to load ${timeframe} data for ${sensorType}:`, error);
        }
    }

    function handleChartControlClick(event) {
        if (event.target.tagName !== 'SPAN') return;
        const clickedButton = event.target;
        const timeframe = clickedButton.dataset.timeframe;
        if (clickedButton.classList.contains('active')) return;

        const controlsContainer = clickedButton.parentElement;
        const sensorType = controlsContainer.dataset.sensor;
        const chart = (sensorType === 'heat') ? heatChart : gasChart;

        controlsContainer.querySelector('span.active').classList.remove('active');
        clickedButton.classList.add('active');

        if (timeframe === 'hourly') {
            updateChartXAxis(chart, 'Waktu (24 data terakhir)');
            
            let initialChartLabels = (sensorType === 'heat') ? initialLabels : initialLabels;
            let initialChartData = (sensorType === 'heat') ? initialTempData : initialGasData;
            
            chart.data.labels = initialChartLabels;
            chart.data.datasets[0].data = initialChartData;
            chart.update();
            
            startRealtimeUpdates(); 
        } else {
            stopRealtimeUpdates(); 
            loadHistoricalData(chart, sensorType, timeframe);
        }
    }

    function startRealtimeUpdates() {
        if (fetchDataInterval) return; 
        console.log("Starting real-time updates...");
        fetchRealtimeData(); 
        fetchDataInterval = setInterval(fetchRealtimeData, 5000);
    }

    function stopRealtimeUpdates() {
        if (fetchDataInterval) {
            console.log("Stopping real-time updates...");
            clearInterval(fetchDataInterval);
            fetchDataInterval = null;
        }
    }
    
    // Update Jam (UTC+7)
    setInterval(function(){
        const clockElement = document.getElementById('realtime-clock');
        if (clockElement) {
           // Menggunakan 'en-GB' dan opsi timezone untuk memaksa tampilan WIB (Asia/Jakarta)
           clockElement.innerHTML=new Date().toLocaleTimeString('en-GB', { timeZone: 'Asia/Jakarta' });
        }
    },1000);

</script>
<script src="js/theme.js"></script>
</body>
</html>