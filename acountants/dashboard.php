<?php
// Start session and check authentication
session_start();

// Regenerate session ID to prevent fixation attacks
session_regenerate_id(true);

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../acountants/login.php");
    exit();
}

// Set security headers
header("X-Frame-Options: DENY");
header("X-XSS-Protection: 1; mode=block");
header("X-Content-Type-Options: nosniff");

// Database connection
$host = 'localhost';
$dbname = "mubende_school_db";
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
    
    // Initialize database if not exists
    $pdo->exec("CREATE TABLE IF NOT EXISTS students (
        id INT AUTO_INCREMENT PRIMARY KEY,
        student_id VARCHAR(20) NOT NULL UNIQUE,
        first_name VARCHAR(50) NOT NULL,
        last_name VARCHAR(50) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    
    $pdo->exec("CREATE TABLE IF NOT EXISTS teachers (
        id INT AUTO_INCREMENT PRIMARY KEY,
        first_name VARCHAR(50) NOT NULL,
        last_name VARCHAR(50) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    
    $pdo->exec("CREATE TABLE IF NOT EXISTS parents (
        id INT AUTO_INCREMENT PRIMARY KEY,
        first_name VARCHAR(50) NOT NULL,
        last_name VARCHAR(50) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    
    $pdo->exec("CREATE TABLE IF NOT EXISTS payments (
        id INT AUTO_INCREMENT PRIMARY KEY,
        amount DECIMAL(10,2),
        status ENUM('Paid', 'Pending', 'Partial') DEFAULT 'Pending',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    
    $pdo->exec("CREATE TABLE IF NOT EXISTS expenses (
        id INT AUTO_INCREMENT PRIMARY KEY,
        amount DECIMAL(10,2),
        description TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    
    // Get counts for dashboard
    $students_count = $pdo->query("SELECT COUNT(*) FROM tbl_students")->fetchColumn();
    $teachers_count = $pdo->query("SELECT COUNT(*) FROM tbl_staff")->fetchColumn();
    $parents_count = $pdo->query("SELECT COUNT(*) FROM parents")->fetchColumn();
    
    // Use prepared statements for security
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) FROM payments WHERE status = 'Paid'");
    $stmt->execute();
    $payments = $stmt->fetchColumn();
    
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) FROM expenses");
    $stmt->execute();
    $expenses = $stmt->fetchColumn();
    
    // Get recent activities
    $stmt = $pdo->prepare("
        (SELECT 'student' as type, CONCAT('New student: ', first_name, ' ', last_name) as description, created_at as date FROM students ORDER BY created_at DESC LIMIT 3)
        UNION 
        (SELECT 'payment' as type, CONCAT('Payment received: UGX ', FORMAT(amount, 0)) as description, created_at as date FROM payments WHERE status = 'Paid' ORDER BY created_at DESC LIMIT 3)
        ORDER BY date DESC LIMIT 5
    ");
    $stmt->execute();
    $recent_activities = $stmt->fetchAll();

    // Get monthly revenue data for chart
    $stmt = $pdo->prepare("
        SELECT MONTH(created_at) as month, COALESCE(SUM(amount), 0) as revenue 
        FROM payments 
        WHERE status = 'Paid' AND YEAR(created_at) = YEAR(CURDATE())
        GROUP BY MONTH(created_at)
        ORDER BY month
    ");
    $stmt->execute();
    $revenue_data = $stmt->fetchAll();

    // Prepare data for chart
    $months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
    $revenues = array_fill(0, 12, 0);
    
    foreach ($revenue_data as $data) {
        $monthIndex = intval($data['month']) - 1;
        if ($monthIndex >= 0 && $monthIndex < 12) {
            $revenues[$monthIndex] = $data['revenue'];
        }
    }
    
} catch(PDOException $e) {
    // Log error instead of displaying to user
    error_log("Database error: " . $e->getMessage());
    
    // If database connection fails, use default values
    $students_count = 0;
    $teachers_count = 0;
    $parents_count = 0;
    $payments = 0;
    $expenses = 0;
    $recent_activities = [];
    $months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
    $revenues = array_fill(0, 12, 0);
}

// Handle logout
if (isset($_GET['logout'])) {
    // Clear session data
    $_SESSION = array();
    
    // Delete session cookie
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    
    // Destroy session
    session_destroy();
    
    // Redirect to login
    header("Location: admin_login.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Mt Zion SS</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        :root {
            --primary: #0553eeff;
            --secondary: #6c757d;
            --success: #198754;
            --info: #0dcaf0;
            --warning: #ffc107;
            --danger: #dc3545;
            --light: #f8f9fa;
            --dark: #212529;
        }
        
        body {
            background-color: #f5f8fb;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .navbar {
            background-color: white;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }
        
        .sidebar {
            background-color: var(--primary);
            color: white;
            min-height: 100vh;
            position: fixed;
            padding-top: 60px;
            width: 250px;
            z-index: 1000;
            transition: all 0.3s;
        }
        
        .sidebar .nav-link {
            color: rgba(255, 255, 255, 0.8);
            padding: 12px 20px;
            margin: 4px 0;
            border-radius: 4px;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        
        .sidebar .nav-link:hover,
        .sidebar .nav-link.active {
            background-color: rgba(255, 255, 255, 0.1);
            color: white;
        }
        
        .sidebar .nav-link i {
            margin-right: 10px;
            width: 20px;
            text-align: center;
        }
        
        .sidebar .nav-link .badge {
            font-size: 0.7rem;
            padding: 4px 8px;
        }
        
        .main-content {
            margin-left: 250px;
            padding: 20px;
            padding-top: 80px;
            transition: margin-left 0.3s;
        }
        
        @media (max-width: 768px) {
            .sidebar {
                margin-left: -250px;
            }
            .main-content {
                margin-left: 0;
            }
            .sidebar.show {
                margin-left: 0;
            }
        }
        
        .card {
            border: none;
            border-radius: 0.5rem;
            box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15);
            margin-bottom: 1.5rem;
            transition: transform 0.3s;
        }
        
        .card:hover {
            transform: translateY(-5px);
        }
        
        .card .card-header {
            background-color: #f8f9fc;
            border-bottom: 1px solid #e3e6f0;
            font-weight: 600;
        }
        
        .border-left-primary {
            border-left: 0.25rem solid #4e73df !important;
        }
        
        .border-left-success {
            border-left: 0.25rem solid #1cc88a !important;
        }
        
        .border-left-info {
            border-left: 0.25rem solid #36b9cc !important;
        }
        
        .border-left-warning {
            border-left: 0.25rem solid #f6c23e !important;
        }
        
        .border-left-danger {
            border-left: 0.25rem solid #e74a3b !important;
        }
        
        .border-left-secondary {
            border-left: 0.25rem solid #858796 !important;
        }
        
        .text-xs {
            font-size: 0.7rem;
        }
        
        .text-gray-800 {
            color: #5a5c69 !important;
        }
        
        .text-gray-300 {
            color: #dddfeb !important;
        }
        
        .activity-icon {
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
        }
        
        .btn-icon-split {
            display: flex;
            align-items: stretch;
            flex-direction: column;
            padding: 0;
            overflow: hidden;
            text-align: center;
            height: 100%;
        }
        
        .btn-icon-split .icon {
            padding: 0.5rem;
        }
        
        .btn-icon-split .text {
            padding: 0.5rem;
        }
        
        .chart-area {
            position: relative;
            height: 20rem;
            width: 100%;
        }
        
        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background-color: var(--primary);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
        }
        
        .school-header {
            background: linear-gradient(135deg, var(--primary), #2c3e50);
            color: white;
            padding: 20px 0;
            margin: -20px -20px 20px -20px;
            border-radius: 0;
        }
        
        .stat-card {
            transition: transform 0.3s;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
        }
        
        .quick-action-btn {
            transition: all 0.3s;
        }
        
        .quick-action-btn:hover {
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
        }
        
        .dashboard-header {
            background: linear-gradient(135deg, var(--primary), #2c3e50);
            color: white;
            padding: 20px;
            border-radius: 0.5rem;
            margin-bottom: 20px;
        }
        
        .count-badge {
            background-color: rgba(255, 255, 255, 0.2);
            color: white;
            border-radius: 10px;
            padding: 2px 8px;
            font-size: 0.75rem;
        }
        .dashboard-header{
            top:0;
        }
        .user-avatar me-2{
            right: 5px;;
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar d-none d-md-block" id="sidebar">
        <div class="position-sticky">
            <div class="text-center mb-4">
                <h4>Mt Zion SS</h4>
                <p class="text-muted">School Management System</p>
            </div>
            <ul class="nav flex-column">
                <li class="nav-item">
                    <a class="nav-link active" href="dashboard.php">
                        <div>
                            <i class="fas fa-tachometer-alt"></i> Dashboard
                        </div>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="students.php">
                        <div>
                            <i class="fas fa-user-graduate"></i> Students
                        </div>
                        <span class="badge bg-light text-dark count-badge"><?php echo $students_count; ?></span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="teachers.php">
                        <div>
                            <i class="fas fa-chalkboard-teacher"></i> Teachers
                        </div>
                        <span class="badge bg-light text-dark count-badge"><?php echo $teachers_count; ?></span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="parents.php">
                        <div>
                            <i class="fas fa-users"></i> Parents
                        </div>
                        <span class="badge bg-light text-dark count-badge"><?php echo $parents_count; ?></span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="finances.php">
                        <div>
                            <i class="fas fa-money-bill-wave"></i> Finances
                        </div>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="expenses.php">
                        <div>
                            <i class="fas fa-receipt"></i> Expenses
                        </div>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="requirements.php">
                        <div>
                            <i class="fas fa-clipboard-list"></i> Requirements
                        </div>
                    </a>
                </li>
               
                <li class="nav-item mt-4">
                    <a class="nav-link" href="?logout=true">
                        <div>
                            <i class="fas fa-sign-out-alt"></i> Logout
                        </div>
                    </a>
                </li>
            </ul>
        </div>
    </div>

    <!-- Main Content -->
    <main class="main-content">
        <div class="container-fluid py-4">
            <!-- Dashboard Header -->
            <div class="dashboard-header">
                <div class="row align-items-center">
                    <div class="col-md-8">
                        <h1 class="h2 mb-1">Financial Management </h1>
                        <p class="mb-0">Welcome back, <?= htmlspecialchars($_SESSION['email']) ?>! Here's what's happening today.</p>
                    </div>
                    <div class="col-md-4 text-md-end">
                        <div class="btn-toolbar mb-2 mb-md-0">
                            <div class="btn-group me-2">
                                <button type="button" class="btn btn-sm btn-outline-light">Share</button>
                                <button type="button" class="btn btn-sm btn-outline-light">Export</button>
                            </div>
                            <button type="button" class="btn btn-sm btn-outline-light dropdown-toggle">
                                <i class="fas fa-calendar me-1"></i>
                                This week
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Stats Cards -->
            <div class="row mb-4">
                <div class="col-xl-2 col-md-4 col-sm-6 mb-4">
                    <div class="card border-left-primary h-100 py-2 stat-card">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Students</div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $students_count; ?></div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-user-graduate fa-2x text-gray-300"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-2 col-md-4 col-sm-6 mb-4">
                    <div class="card border-left-success h-100 py-2 stat-card">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Teachers</div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $teachers_count; ?></div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-chalkboard-teacher fa-2x text-gray-300"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-2 col-md-4 col-sm-6 mb-4">
                    <div class="card border-left-info h-100 py-2 stat-card">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-info text-uppercase mb-1">Parents</div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $parents_count; ?></div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-users fa-2x text-gray-300"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-2 col-md-4 col-sm-6 mb-4">
                    <div class="card border-left-warning h-100 py-2 stat-card">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">Total Payments</div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800">UGX <?php echo number_format($payments, 0); ?></div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-money-bill-wave fa-2x text-gray-300"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-2 col-md-4 col-sm-6 mb-4">
                    <div class="card border-left-danger h-100 py-2 stat-card">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-danger text-uppercase mb-1">Total Expenses</div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800">UGX <?php echo number_format($expenses, 0); ?></div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-receipt fa-2x text-gray-300"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-2 col-md-4 col-sm-6 mb-4">
                    <div class="card border-left-secondary h-100 py-2 stat-card">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-secondary text-uppercase mb-1">Balance</div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800">UGX <?php echo number_format($payments - $expenses, 0); ?></div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-balance-scale fa-2x text-gray-300"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Charts and Activities Row -->
            <div class="row">
                <!-- Revenue Chart -->
                <div class="col-xl-8 col-lg-7">
                    <div class="card mb-4">
                        <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                            <h6 class="m-0 font-weight-bold text-primary">Revenue Overview</h6>
                            <div class="dropdown">
                                <a class="btn btn-sm btn-light dropdown-toggle" href="#" role="button" id="dropdownMenuLink" data-bs-toggle="dropdown" aria-expanded="false">
                                    <i class="fas fa-ellipsis-v fa-sm fa-fw text-gray-400"></i>
                                </a>
                                <ul class="dropdown-menu dropdown-menu-end shadow animated--fade-in" aria-labelledby="dropdownMenuLink">
                                    <li><a class="dropdown-item" href="#">Export Data</a></li>
                                    <li><a class="dropdown-item" href="#">Print Chart</a></li>
                                    <li><hr class="dropdown-divider"></li>
                                    <li><a class="dropdown-item" href="#">Refresh</a></li>
                                </ul>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="chart-area">
                                <canvas id="revenueChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Recent Activities -->
                <div class="col-xl-4 col-lg-5">
                    <div class="card mb-4">
                        <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                            <h6 class="m-0 font-weight-bold text-primary">Recent Activities</h6>
                            <a href="activities.php" class="btn btn-sm btn-primary">View All</a>
                        </div>
                        <div class="card-body">
                            <div class="activity-feed">
                                <?php if (count($recent_activities) > 0): ?>
                                    <?php foreach ($recent_activities as $activity): ?>
                                        <div class="activity-item d-flex align-items-center mb-3">
                                            <div class="activity-icon bg-primary text-white rounded-circle me-3">
                                                <?php if ($activity['type'] == 'student'): ?>
                                                    <i class="fas fa-user-graduate fa-fw"></i>
                                                <?php else: ?>
                                                    <i class="fas fa-money-bill-wave fa-fw"></i>
                                                <?php endif; ?>
                                            </div>
                                            <div class="activity-content">
                                                <h6 class="mb-0"><?php echo htmlspecialchars($activity['description']); ?></h6>
                                                <small class="text-muted"><?php echo date('M j, Y', strtotime($activity['date'])); ?></small>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <p class="text-muted text-center py-3">No recent activities</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="row">
                <div class="col-12">
                    <div class="card mb-4">
                        <div class="card-header py-3">
                            <h6 class="m-0 font-weight-bold text-primary">Quick Actions</h6>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-xl-2 col-md-3 col-sm-4 col-6 mb-3 text-center">
                                    <a href="students.php" class="btn btn-light btn-icon-split p-3 quick-action-btn">
                                        <span class="icon text-white bg-primary">
                                            <i class="fas fa-user-graduate"></i>
                                        </span>
                                        <span class="text">Manage Students</span>
                                    </a>
                                </div>
                                <div class="col-xl-2 col-md-3 col-sm-4 col-6 mb-3 text-center">
                                    <a href="teachers.php" class="btn btn-light btn-icon-split p-3 quick-action-btn">
                                        <span class="icon text-white bg-success">
                                            <i class="fas fa-chalkboard-teacher"></i>
                                        </span>
                                        <span class="text">Manage Teachers</span>
                                    </a>
                                </div>
                                <div class="col-xl-2 col-md-3 col-sm-4 col-6 mb-3 text-center">
                                    <a href="parents.php" class="btn btn-light btn-icon-split p-3 quick-action-btn">
                                        <span class="icon text-white bg-info">
                                            <i class="fas fa-users"></i>
                                        </span>
                                        <span class="text">Manage Parents</span>
                                    </a>
                                </div>
                                <div class="col-xl-2 col-md-3 col-sm-4 col-6 mb-3 text-center">
                                    <a href="finances.php" class="btn btn-light btn-icon-split p-3 quick-action-btn">
                                        <span class="icon text-white bg-warning">
                                            <i class="fas fa-money-bill-wave"></i>
                                        </span>
                                        <span class="text">View Finances</span>
                                    </a>
                                </div>
                                <div class="col-xl-2 col-md-3 col-sm-4 col-6 mb-3 text-center">
                                    <a href="expenses.php" class="btn btn-light btn-icon-split p-3 quick-action-btn">
                                        <span class="icon text-white bg-danger">
                                            <i class="fas fa-receipt"></i>
                                        </span>
                                        <span class="text">Manage Expenses</span>
                                    </a>
                                </div>
                                <div class="col-xl-2 col-md-3 col-sm-4 col-6 mb-3 text-center">
                                    <a href="requirements.php" class="btn btn-light btn-icon-split p-3 quick-action-btn">
                                        <span class="icon text-white bg-secondary">
                                            <i class="fas fa-clipboard-list"></i>
                                        </span>
                                        <span class="text">Set Requirements</span>
                                    </a>
                                </div>
                                <div class="col-xl-2 col-md-3 col-sm-4 col-6 mb-3 text-center">
                                    <a href="attendance.php" class="btn btn-light btn-icon-split p-3 quick-action-btn">
                                        <span class="icon text-white bg-dark">
                                            <i class="fas fa-calendar-check"></i>
                                        </span>
                                        <span class="text">Attendance</span>
                                    </a>
                                </div>
                                <div class="col-xl-2 col-md-3 col-sm-4 col-6 mb-3 text-center">
                                    <a href="reports.php" class="btn btn-light btn-icon-split p-3 quick-action-btn">
                                        <span class="icon text-white bg-info">
                                            <i class="fas fa-chart-bar"></i>
                                        </span>
                                        <span class="text">Reports</span>
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>

    <script>
    // Toggle sidebar on mobile
    document.getElementById('sidebarToggle').addEventListener('click', function() {
        document.getElementById('sidebar').classList.toggle('show');
    });

    // Revenue Chart
    var ctx = document.getElementById('revenueChart').getContext('2d');
    var revenueChart = new Chart(ctx, {
        type: 'line',
        data: {
            labels: <?php echo json_encode($months); ?>,
            datasets: [{
                label: 'Revenue (UGX)',
                data: <?php echo json_encode($revenues); ?>,
                backgroundColor: 'rgba(78, 115, 223, 0.05)',
                borderColor: 'rgba(78, 115, 223, 1)',
                pointRadius: 3,
                pointBackgroundColor: 'rgba(78, 115, 223, 1)',
                pointBorderColor: 'rgba(78, 115, 223, 1)',
                pointHoverRadius: 3,
                pointHoverBackgroundColor: 'rgba(78, 115, 223, 1)',
                pointHoverBorderColor: 'rgba(78, 115, 223, 1)',
                pointHitRadius: 10,
                pointBorderWidth: 2,
                fill: true,
                tension: 0.3
            }]
        },
        options: {
            maintainAspectRatio: false,
            scales: {
                x: {
                    grid: {
                        display: false,
                        drawBorder: false
                    }
                },
                y: {
                    ticks: {
                        maxTicksLimit: 5,
                        padding: 10,
                        callback: function(value, index, values) {
                            return 'UGX ' + value.toLocaleString();
                        }
                    },
                    grid: {
                        color: "rgb(234, 236, 244)",
                        drawBorder: false,
                        borderDash: [2],
                        zeroLineBorderDash: [2]
                    }
                }
            },
            plugins: {
                legend: {
                    display: false
                },
                tooltip: {
                    backgroundColor: "rgb(255,255,255)",
                    bodyColor: "#858796",
                    titleMarginBottom: 10,
                    titleColor: '#6e707e',
                    titleFont: {
                        size: 14
                    },
                    borderColor: '#dddfeb',
                    borderWidth: 1,
                    displayColors: false,
                    caretPadding: 10,
                    callbacks: {
                        label: function(context) {
                            return 'UGX ' + context.parsed.y.toLocaleString();
                        }
                    }
                }
            }
        }
    });
    </script>
</body>
</html>