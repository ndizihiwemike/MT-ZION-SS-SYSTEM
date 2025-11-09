<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

include '../config/database.php';

// Initialize variables
$success_message = '';
$error_message = '';
$teachers = [];
$db_connected = false;

// Database connection parameters
$servername = "localhost";
$username = "root"; // Replace with your database username
$password = ""; // Replace with your database password
$dbname = "mubende_school_db"; // Replace with your database name

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    $error_message = "Database connection failed: " . $conn->connect_error;
    $db_connected = false;
} else {
    $db_connected = true;
    
    // Check if tbl_staff table exists
    $table_check = $conn->query("SHOW TABLES LIKE 'tbl_staff'");
    if ($table_check && $table_check->num_rows > 0) {
        // Handle add teacher form submission
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_teacher'])) {
            // Input validation
            $firstName = trim($_POST['teacherFirstName'] ?? '');
            $lastName = trim($_POST['teacherLastName'] ?? '');
            $email = trim($_POST['teacherEmail'] ?? '');
            $phone = trim($_POST['teacherPhone'] ?? '');
            $subject = trim($_POST['teacherSubject'] ?? '');
            $class = trim($_POST['teacherClass'] ?? '');
            $address = trim($_POST['teacherAddress'] ?? '');
            $gender = trim($_POST['teacherGender'] ?? '');
            $level = 2; // 2 = Teacher level
            $status = 1; // Active status
            $password = password_hash('teacher123', PASSWORD_DEFAULT); // Default password

            // Validate required fields
            if (empty($firstName) || empty($lastName) || empty($email)) {
                $error_message = "First name, last name, and email are required fields.";
            } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $error_message = "Invalid email format.";
            } else {
                // Check if email already exists
                $check_sql = "SELECT id FROM tbl_staff WHERE email = ?";
                $check_stmt = $conn->prepare($check_sql);
                if ($check_stmt) {
                    $check_stmt->bind_param("s", $email);
                    $check_stmt->execute();
                    $check_stmt->store_result();
                    
                    if ($check_stmt->num_rows > 0) {
                        $error_message = "A staff member with this email already exists.";
                    } else {
                        // Use prepared statement to prevent SQL injection
                        $sql = "INSERT INTO tbl_staff (fname, lname, email, password, gender, level, status, phone, subject, class, address) 
                                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                        
                        $stmt = $conn->prepare($sql);
                        if ($stmt) {
                            $stmt->bind_param("sssssisssss", $firstName, $lastName, $email, $password, $gender, $level, $status, $phone, $subject, $class, $address);
                            
                            if ($stmt->execute()) {
                                $success_message = "Teacher added successfully!";
                            } else {
                                $error_message = "Error adding teacher: " . $conn->error;
                            }
                            $stmt->close();
                        } else {
                            $error_message = "Error preparing statement: " . $conn->error;
                        }
                    }
                    $check_stmt->close();
                } else {
                    $error_message = "Error preparing check statement: " . $conn->error;
                }
            }
        }

        // Handle delete teacher
        if (isset($_GET['delete_id'])) {
            $delete_id = intval($_GET['delete_id']);
            
            $stmt = $conn->prepare("DELETE FROM tbl_staff WHERE id = ? AND level = 2");
            if ($stmt) {
                $stmt->bind_param("i", $delete_id);
                if ($stmt->execute()) {
                    $_SESSION['success_message'] = "Teacher deleted successfully!";
                } else {
                    $_SESSION['error_message'] = "Error deleting teacher: " . $conn->error;
                }
                $stmt->close();
            } else {
                $_SESSION['error_message'] = "Error preparing delete statement: " . $conn->error;
            }
            
            // Redirect to avoid resubmission
            header("Location: teachers.php");
            exit();
        }

        // Handle update teacher
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_teacher'])) {
            $teacher_id = intval($_POST['teacher_id']);
            $firstName = trim($_POST['teacherFirstName'] ?? '');
            $lastName = trim($_POST['teacherLastName'] ?? '');
            $email = trim($_POST['teacherEmail'] ?? '');
            $phone = trim($_POST['teacherPhone'] ?? '');
            $subject = trim($_POST['teacherSubject'] ?? '');
            $class = trim($_POST['teacherClass'] ?? '');
            $address = trim($_POST['teacherAddress'] ?? '');
            $gender = trim($_POST['teacherGender'] ?? '');

            // Validate required fields
            if (empty($firstName) || empty($lastName) || empty($email)) {
                $error_message = "First name, last name, and email are required fields.";
            } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $error_message = "Invalid email format.";
            } else {
                // Check if email already exists for other teachers
                $check_sql = "SELECT id FROM tbl_staff WHERE email = ? AND id != ?";
                $check_stmt = $conn->prepare($check_sql);
                if ($check_stmt) {
                    $check_stmt->bind_param("si", $email, $teacher_id);
                    $check_stmt->execute();
                    $check_stmt->store_result();
                    
                    if ($check_stmt->num_rows > 0) {
                        $error_message = "Another staff member with this email already exists.";
                    } else {
                        $sql = "UPDATE tbl_staff SET fname = ?, lname = ?, email = ?, phone = ?, subject = ?, class = ?, address = ?, gender = ? WHERE id = ? AND level = 2";
                        $stmt = $conn->prepare($sql);
                        if ($stmt) {
                            $stmt->bind_param("ssssssssi", $firstName, $lastName, $email, $phone, $subject, $class, $address, $gender, $teacher_id);
                            
                            if ($stmt->execute()) {
                                $_SESSION['success_message'] = "Teacher updated successfully!";
                                header("Location: teachers.php");
                                exit();
                            } else {
                                $error_message = "Error updating teacher: " . $conn->error;
                            }
                            $stmt->close();
                        } else {
                            $error_message = "Error preparing update statement: " . $conn->error;
                        }
                    }
                    $check_stmt->close();
                }
            }
        }

        // Check for session messages
        if (isset($_SESSION['success_message'])) {
            $success_message = $_SESSION['success_message'];
            unset($_SESSION['success_message']);
        }
        
        if (isset($_SESSION['error_message'])) {
            $error_message = $_SESSION['error_message'];
            unset($_SESSION['error_message']);
        }

        // Fetch teacher data for editing
        $edit_teacher = null;
        if (isset($_GET['edit_id'])) {
            $edit_id = intval($_GET['edit_id']);
            $stmt = $conn->prepare("SELECT * FROM tbl_staff WHERE id = ? AND level = 2");
            if ($stmt) {
                $stmt->bind_param("i", $edit_id);
                $stmt->execute();
                $result = $stmt->get_result();
                $edit_teacher = $result->fetch_assoc();
                $stmt->close();
            }
        }

        // Fetch teachers from tbl_staff where level is 2 (Teacher)
        $teachers = [];
        $sql = "SELECT * FROM tbl_staff WHERE level = 2 ORDER BY id DESC";
        $result = $conn->query($sql);
        
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $teachers[] = $row;
            }
            $result->free();
        } else {
            $error_message = "Error fetching teachers: " . $conn->error;
        }
    } else {
        $error_message = "The 'tbl_staff' table doesn't exist in the database.";
    }
}

// Close the connection
if (isset($conn) && $db_connected) {
    $conn->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teacher Management - Mt Zion SS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --primary: blue;
            --secondary: #6c757d;
            --success: #198754;
            --info: #0dcaf0;
            --warning: #ffc107;
            --danger: #dc3545;
            --light: #f8f9fa;
            --dark: #212529;
        }
        
        body {
            background-color: #f5f5f5;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .sidebar {
            background-color: var(--primary);
            color: white;
            height: 100vh;
            position: fixed;
            padding-top: 60px;
        }
        
        .sidebar .nav-link {
            color: rgba(255, 255, 255, 0.8);
            padding: 12px 20px;
            margin: 4px 0;
            border-radius: 4px;
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
        
        .main-content {
            margin-left: 250px;
            padding: 20px;
            padding-top: 80px;
        }
        
        .navbar {
            background-color: white;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            position: fixed;
            width: calc(100% - 250px);
            margin-left: 250px;
            z-index: 1000;
        }
        
        .school-header {
            background: linear-gradient(135deg, var(--primary), #2c3e50);
            color: white;
            padding: 20px 0;
            margin-bottom: 20px;
            border-radius: 0 0 10px 10px;
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
        
        .action-btn {
            margin-right: 5px;
        }
        
        .teacher-card {
            transition: transform 0.3s;
        }
        
        .teacher-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }
        
        .alert {
            margin: 20px 0;
        }
        
        .delete-btn {
            cursor: pointer;
        }
    </style>
</head>
<body>

    <!-- Sidebar -->
    <div class="sidebar col-md-3 col-lg-2 d-md-block">
        <div class="position-sticky">
            <div class="text-center mb-4">
                <h4>Mt Zion SS</h4>
                <p class="text-muted">School Management System</p>
            </div>
            <ul class="nav flex-column">
                <li class="nav-item">
                    <a class="nav-link" href="dashboard.php">
                        <i class="fas fa-tachometer-alt"></i> Dashboard
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="students.php">
                        <i class="fas fa-user-graduate"></i> Students
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link active" href="teachers.php">
                        <i class="fas fa-chalkboard-teacher"></i> Teachers
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="parents.php">
                        <i class="fas fa-users"></i> Parents
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="finances.php">
                        <i class="fas fa-money-bill-wave"></i> Finances
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="expenses.php">
                        <i class="fas fa-receipt"></i> Expenses
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="requirements.php">
                        <i class="fas fa-clipboard-list"></i> Requirements
                    </a>
                </li>
                <li class="nav-item mt-4">
                    <a class="nav-link" href="?logout=true">
                        <i class="fas fa-sign-out-alt"></i> Logout
                    </a>
                </li>
            </ul>
        </div>
    </div>

    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-light">
        <div class="container-fluid">
            <button class="navbar-toggler" type="button">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="d-flex align-items-center">
                <div class="user-avatar me-2"><?= strtoupper(substr($_SESSION['email'], 0, 1)) ?></div>
                <span><?= $_SESSION['email'] ?> (<?= ucfirst($_SESSION['role']) ?>)</span>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <main class="main-content">
        <div class="school-header">
            <div class="container">
                <h1 class="display-4">Teacher Management</h1>
                <p class="lead">Manage teacher records and information</p>
            </div>
        </div>
        
        <div class="container">
            <!-- Display success/error messages -->
            <?php if ($success_message): ?>
                <div class="alert alert-success alert-dismissible fade show">
                    <i class="fas fa-check-circle me-2"></i><?= $success_message ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>
            
            <?php if ($error_message): ?>
                <div class="alert alert-danger alert-dismissible fade show">
                    <i class="fas fa-exclamation-circle me-2"></i><?= $error_message ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>
            
            <div class="d-flex justify-content-between align-items-center pb-2 mb-3 border-bottom">
                <h2>Teacher List</h2>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addTeacherModal">
                    <i class="fas fa-plus me-1"></i> Add New Teacher
                </button>
            </div>

            <!-- Teachers Table View -->
            <div class="card mb-4">
                <div class="card-header bg-white">
                    <div class="row align-items-center">
                        <div class="col-md-6">
                            <h5 class="mb-0">All Teachers (<?= count($teachers) ?>)</h5>
                        </div>
                        <div class="col-md-6 text-md-end">
                            <div class="btn-group">
                                <button class="btn btn-outline-secondary btn-sm">
                                    <i class="fas fa-filter me-1"></i> Filter
                                </button>
                                <button class="btn btn-outline-secondary btn-sm">
                                    <i class="fas fa-download me-1"></i> Export
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    <?php if (count($teachers) > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Name</th>
                                        <th>Email</th>
                                        <th>Phone</th>
                                        <th>Gender</th>
                                        <th>Subject</th>
                                        <th>Class</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($teachers as $teacher): ?>
                                        <tr>
                                            <td><?= $teacher['id'] ?></td>
                                            <td><?= htmlspecialchars($teacher['fname'] . ' ' . $teacher['lname']) ?></td>
                                            <td><?= htmlspecialchars($teacher['email']) ?></td>
                                            <td><?= htmlspecialchars($teacher['phone'] ?? 'N/A') ?></td>
                                            <td><?= htmlspecialchars($teacher['gender'] ?? 'N/A') ?></td>
                                            <td><?= htmlspecialchars($teacher['subject'] ?? 'N/A') ?></td>
                                            <td><?= htmlspecialchars($teacher['class'] ?? 'N/A') ?></td>
                                            <td>
                                                <div class="btn-group btn-group-sm">
                                                    <a href="?edit_id=<?= $teacher['id'] ?>" class="btn btn-warning action-btn" title="Edit" data-bs-toggle="modal" data-bs-target="#editTeacherModal">
                                                        <i class="fas fa-edit"></i>
                                                    </a>
                                                    <button class="btn btn-danger action-btn delete-btn" title="Delete" onclick="confirmDelete(<?= $teacher['id'] ?>)">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-5">
                            <i class="fas fa-chalkboard-teacher fa-3x text-muted mb-3"></i>
                            <h4>No Teachers Found</h4>
                            <p class="text-muted">No teachers have been added yet. Click the "Add New Teacher" button to get started.</p>
                            <button class="btn btn-primary mt-2" data-bs-toggle="modal" data-bs-target="#addTeacherModal">
                                <i class="fas fa-plus me-1"></i> Add New Teacher
                            </button>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Card View (Alternative) -->
            <h4 class="mb-3">Card View</h4>
            <div class="row">
                <?php if (count($teachers) > 0): ?>
                    <?php foreach ($teachers as $teacher): ?>
                        <div class="col-md-4 mb-4">
                            <div class="card teacher-card h-100">
                                <div class="card-body text-center">
                                    <img src="https://ui-avatars.com/api/?name=<?= urlencode($teacher['fname'].' '.$teacher['lname']) ?>&background=3c5c9c&color=fff" 
                                         class="rounded-circle mb-3" width="80" height="80" alt="Teacher">
                                    <h5><?= htmlspecialchars($teacher['fname'] . ' ' . $teacher['lname']) ?></h5>
                                    <p class="text-muted"><?= htmlspecialchars($teacher['subject'] ?? 'Not assigned') ?></p>
                                    <p><strong>Class:</strong> <?= htmlspecialchars($teacher['class'] ?? 'Not assigned') ?></p>
                                    <p><strong>Gender:</strong> <?= htmlspecialchars($teacher['gender'] ?? 'N/A') ?></p>
                                    <p class="small text-muted"><?= htmlspecialchars($teacher['email']) ?></p>
                                    <p class="small"><?= htmlspecialchars($teacher['phone'] ?? 'No phone') ?></p>
                                    <div class="d-flex justify-content-center">
                                        <a href="?edit_id=<?= $teacher['id'] ?>" class="btn btn-sm btn-warning me-1" title="Edit" data-bs-toggle="modal" data-bs-target="#editTeacherModal">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <button class="btn btn-sm btn-danger delete-btn" title="Delete" onclick="confirmDelete(<?= $teacher['id'] ?>)">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="col-12">
                        <div class="alert alert-info text-center">
                            <h4>No Teachers Found</h4>
                            <p>No teachers have been added yet. Click the "Add New Teacher" button to get started.</p>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Add Teacher Modal -->
        <div class="modal fade" id="addTeacherModal" tabindex="-1" aria-labelledby="addTeacherModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="addTeacherModalLabel">Add New Teacher</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <form method="POST" action="">
                        <div class="modal-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="teacherFirstName" class="form-label">First Name *</label>
                                        <input type="text" class="form-control" id="teacherFirstName" name="teacherFirstName" required>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="teacherLastName" class="form-label">Last Name *</label>
                                        <input type="text" class="form-control" id="teacherLastName" name="teacherLastName" required>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="teacherEmail" class="form-label">Email *</label>
                                        <input type="email" class="form-control" id="teacherEmail" name="teacherEmail" required>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="teacherGender" class="form-label">Gender *</label>
                                        <select class="form-select" id="teacherGender" name="teacherGender" required>
                                            <option value="">Select Gender</option>
                                            <option value="Male">Male</option>
                                            <option value="Female">Female</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="teacherPhone" class="form-label">Phone Number</label>
                                        <input type="tel" class="form-control" id="teacherPhone" name="teacherPhone">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="teacherSubject" class="form-label">Subject</label>
                                        <select class="form-select" id="teacherSubject" name="teacherSubject">
                                            <option value="">Select Subject</option>
                                            <option value="Mathematics">Mathematics</option>
                                            <option value="Physics">Physics</option>
                                            <option value="Chemistry">Chemistry</option>
                                            <option value="Biology">Biology</option>
                                            <option value="English">English</option>
                                            <option value="History">History</option>
                                            <option value="Geography">Geography</option>
                                            <option value="Computer Studies">Computer Studies</option>
                                            <option value="Commerce">Commerce</option>
                                            <option value="Accounts">Accounts</option>
                                            <option value="Agriculture">Agriculture</option>
                                            <option value="Art">Art</option>
                                            <option value="Music">Music</option>
                                            <option value="Physical Education">Physical Education</option>
                                            <option value="Religious Studies">Religious Studies</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="teacherClass" class="form-label">Class</label>
                                        <select class="form-select" id="teacherClass" name="teacherClass">
                                            <option value="">Select Class</option>
                                            <option value="S1">S1</option>
                                            <option value="S2">S2</option>
                                            <option value="S3">S3</option>
                                            <option value="S4">S4</option>
                                            <option value="S5">S5</option>
                                            <option value="S6">S6</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="teacherAddress" class="form-label">Address</label>
                                <textarea class="form-control" id="teacherAddress" name="teacherAddress" rows="2"></textarea>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" name="add_teacher" class="btn btn-primary">Add Teacher</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Edit Teacher Modal -->
        <div class="modal fade" id="editTeacherModal" tabindex="-1" aria-labelledby="editTeacherModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="editTeacherModalLabel">Edit Teacher</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <form method="POST">
                        <div class="modal-body">
                            <input type="hidden" name="teacher_id" value="<?= $edit_teacher['id'] ?? '' ?>">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="editTeacherFirstName" class="form-label">First Name *</label>
                                        <input type="text" class="form-control" id="editTeacherFirstName" name="teacherFirstName" required 
                                               value="<?= $edit_teacher['fname'] ?? '' ?>">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="editTeacherLastName" class="form-label">Last Name *</label>
                                        <input type="text" class="form-control" id="editTeacherLastName" name="teacherLastName" required 
                                               value="<?= $edit_teacher['lname'] ?? '' ?>">
                                    </div>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="editTeacherEmail" class="form-label">Email *</label>
                                        <input type="email" class="form-control" id="editTeacherEmail" name="teacherEmail" required 
                                               value="<?= $edit_teacher['email'] ?? '' ?>">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="editTeacherGender" class="form-label">Gender *</label>
                                        <select class="form-select" id="editTeacherGender" name="teacherGender" required>
                                            <option value="">Select Gender</option>
                                            <option value="Male" <?= ($edit_teacher['gender'] ?? '') === 'Male' ? 'selected' : '' ?>>Male</option>
                                            <option value="Female" <?= ($edit_teacher['gender'] ?? '') === 'Female' ? 'selected' : '' ?>>Female</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="editTeacherPhone" class="form-label">Phone Number</label>
                                        <input type="tel" class="form-control" id="editTeacherPhone" name="teacherPhone" 
                                               value="<?= $edit_teacher['phone'] ?? '' ?>">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="editTeacherSubject" class="form-label">Subject</label>
                                        <select class="form-select" id="editTeacherSubject" name="teacherSubject">
                                            <option value="">Select Subject</option>
                                            <option value="Mathematics" <?= ($edit_teacher['subject'] ?? '') === 'Mathematics' ? 'selected' : '' ?>>Mathematics</option>
                                            <option value="Physics" <?= ($edit_teacher['subject'] ?? '') === 'Physics' ? 'selected' : '' ?>>Physics</option>
                                            <option value="Chemistry" <?= ($edit_teacher['subject'] ?? '') === 'Chemistry' ? 'selected' : '' ?>>Chemistry</option>
                                            <option value="Biology" <?= ($edit_teacher['subject'] ?? '') === 'Biology' ? 'selected' : '' ?>>Biology</option>
                                            <option value="English" <?= ($edit_teacher['subject'] ?? '') === 'English' ? 'selected' : '' ?>>English</option>
                                            <option value="History" <?= ($edit_teacher['subject'] ?? '') === 'History' ? 'selected' : '' ?>>History</option>
                                            <option value="Geography" <?= ($edit_teacher['subject'] ?? '') === 'Geography' ? 'selected' : '' ?>>Geography</option>
                                            <option value="Computer Studies" <?= ($edit_teacher['subject'] ?? '') === 'Computer Studies' ? 'selected' : '' ?>>Computer Studies</option>
                                            <option value="Commerce" <?= ($edit_teacher['subject'] ?? '') === 'Commerce' ? 'selected' : '' ?>>Commerce</option>
                                            <option value="Accounts" <?= ($edit_teacher['subject'] ?? '') === 'Accounts' ? 'selected' : '' ?>>Accounts</option>
                                            <option value="Agriculture" <?= ($edit_teacher['subject'] ?? '') === 'Agriculture' ? 'selected' : '' ?>>Agriculture</option>
                                            <option value="Art" <?= ($edit_teacher['subject'] ?? '') === 'Art' ? 'selected' : '' ?>>Art</option>
                                            <option value="Music" <?= ($edit_teacher['subject'] ?? '') === 'Music' ? 'selected' : '' ?>>Music</option>
                                            <option value="Physical Education" <?= ($edit_teacher['subject'] ?? '') === 'Physical Education' ? 'selected' : '' ?>>Physical Education</option>
                                            <option value="Religious Studies" <?= ($edit_teacher['subject'] ?? '') === 'Religious Studies' ? 'selected' : '' ?>>Religious Studies</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="editTeacherClass" class="form-label">Class</label>
                                        <select class="form-select" id="editTeacherClass" name="teacherClass">
                                            <option value="">Select Class</option>
                                            <option value="S1" <?= ($edit_teacher['class'] ?? '') === 'S1' ? 'selected' : '' ?>>S1</option>
                                            <option value="S2" <?= ($edit_teacher['class'] ?? '') === 'S2' ? 'selected' : '' ?>>S2</option>
                                            <option value="S3" <?= ($edit_teacher['class'] ?? '') === 'S3' ? 'selected' : '' ?>>S3</option>
                                            <option value="S4" <?= ($edit_teacher['class'] ?? '') === 'S4' ? 'selected' : '' ?>>S4</option>
                                            <option value="S5" <?= ($edit_teacher['class'] ?? '') === 'S5' ? 'selected' : '' ?>>S5</option>
                                            <option value="S6" <?= ($edit_teacher['class'] ?? '') === 'S6' ? 'selected' : '' ?>>S6</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="editTeacherAddress" class="form-label">Address</label>
                                <textarea class="form-control" id="editTeacherAddress" name="teacherAddress" rows="2"><?= $edit_teacher['address'] ?? '' ?></textarea>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" name="update_teacher" class="btn btn-primary">Update Teacher</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </main>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Function to confirm teacher deletion
        function confirmDelete(teacherId) {
            if (confirm('Are you sure you want to delete this teacher? This action cannot be undone.')) {
                window.location.href = '?delete_id=' + teacherId;
            }
        }

        // Show edit modal if edit_id parameter is present
        <?php if (isset($_GET['edit_id']) && $edit_teacher): ?>
            document.addEventListener('DOMContentLoaded', function() {
                var editModal = new bootstrap.Modal(document.getElementById('editTeacherModal'));
                editModal.show();
            });
        <?php endif; ?>

        // Close edit modal and remove URL parameter when modal is hidden
        document.addEventListener('DOMContentLoaded', function() {
            var editModal = document.getElementById('editTeacherModal');
            if (editModal) {
                editModal.addEventListener('hidden.bs.modal', function () {
                    // Remove edit_id from URL without page reload
                    if (window.history.replaceState && window.location.search.includes('edit_id')) {
                        var newUrl = window.location.pathname;
                        window.history.replaceState({}, document.title, newUrl);
                    }
                });
            }
        });

        // Simple form validation
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.querySelector('form');
            if (form) {
                form.addEventListener('submit', function(e) {
                    const requiredFields = form.querySelectorAll('[required]');
                    let valid = true;
                    
                    requiredFields.forEach(field => {
                        if (!field.value.trim()) {
                            valid = false;
                            field.classList.add('is-invalid');
                        } else {
                            field.classList.remove('is-invalid');
                        }
                    });
                    
                    if (!valid) {
                        e.preventDefault();
                        alert('Please fill in all required fields.');
                    }
                });
            }
        });
    </script>
</body>
</html>