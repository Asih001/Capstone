<?php
// session_start();
// if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
//     header('location: login.php');
//     exit;
// }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings - Sistem Monitoring</title>
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
                <li class="nav-item">
                    <a href="index.php">
                        <i class='bx bx-grid-alt'></i>
                        <span>Dashboard</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="history.php">
                        <i class='bx bx-history'></i>
                        <span>History</span>
                    </a>
                </li>
                <li class="nav-item active">
                    <a href="settings.php">
                        <i class='bx bx-cog'></i>
                        <span>Setting</span>
                    </a>
                </li>
            </ul>

        </div>
        <div class="main-content">
            <div class="header">
                <h2>Settings</h2>
                <div class="user-info">
                    <img src="https://i.pravatar.cc/30" alt="Admin Avatar" class="avatar">
                    <div class="user-details">
                        <span>User</span>
                    </div>
                </div>
            </div>
            <div class="content-body">
                <div class="settings-card" style="max-width: 500px; margin: auto;">
                    <h3>Appearance</h3>
                    <p class="card-description">Customize the look and feel of your dashboard.</p>
                    <div class="settings-form">
                        <div class="form-group switch-group">
                            <label for="theme-switch">
                                <i class='bx bx-moon'></i> Dark Mode
                            </label>
                            <label class="switch">
                                <input type="checkbox" id="theme-switch">
                                <span class="slider"></span>
                            </label>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="js/theme.js"></script>
</body>
</html>