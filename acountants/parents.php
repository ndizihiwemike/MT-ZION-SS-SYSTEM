<?php
session_start();

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

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
    $conn = null;
}

// Initialize variables
$success_message = '';
$error_message = '';
$parents = [];
$students = [];

// Check if database connection is established
if ($conn) {
    // Handle form submission for adding a new parent
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_parent'])) {
        // Input validation
      $firstName = trim($_POST['parentFirstName'] ?? '');
      $lastName = trim($_POST['parentLastName'] ?? '');
      $email = trim($_POST['parentEmail'] ?? '');
      $phone = trim($_POST['parentPhone'] ?? '');
      $address = trim($_POST['parentAddress'] ?? '');
      $parentStudents = $_POST['parentStudents'] ?? [];

        // Validate required fields
        if (empty($firstName) || empty($lastName) || empty($email)) {
            $error_message = "First name, last name, and email are required.";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error_message = "Invalid email format.";
        } else {
            // Check if parent email already exists
            $check_stmt = $conn->prepare("SELECT email FROM parents WHERE email = ?");
            $check_stmt->bind_param("s", $email);
            $check_stmt->execute();
            $check_result = $check_stmt->get_result();

            if ($check_result->num_rows > 0) {
                $error_message = "Parent with this email already exists. Please use a different email.";
            } else {
                // Insert into database using prepared statement
                $sql = "INSERT INTO parents (first_name, last_name, email, phone, address) VALUES (?, ?, ?, ?, ?)";
                $stmt = $conn->prepare($sql);
                if ($stmt) {
                    $stmt->bind_param("sssss", $firstName, $lastName, $email, $phone, $address);

                    if ($stmt->execute()) {
                        $parent_id = $conn->insert_id;

                        // Handle student assignments if any
                        if (!empty($parentStudents) && is_array($parentStudents)) {
                            $update_stmt = $conn->prepare("UPDATE students SET parent_id = ? WHERE id = ?");
                            if ($update_stmt) {
                                foreach ($parentStudents as $student_id) {
                                    // Validate student_id is numeric
                                    if (is_numeric($student_id)) {
                                        $update_stmt->bind_param("ii", $parent_id, $student_id);
                                        $update_stmt->execute();
                                    }
                                }
                                $update_stmt->close();
                            } else {
                                $error_message = "Error preparing student update statement: " . $conn->error;
                            }
                        }

                        $success_message = "Parent added successfully!";
                    } else {
                        $error_message = "Error adding parent: " . $conn->error;
                    }
                    $stmt->close();
                } else {
                    $error_message = "Error preparing statement: " . $conn->error;
                }
            }
            $check_stmt->close();
        }
    }

    // Fetch parents from database
    $parents_query = "SELECT p.*, 
                             GROUP_CONCAT(CONCAT(s.first_name, ' ', s.last_name) SEPARATOR ', ') as student_names
                      FROM parents p 
                      LEFT JOIN students s ON p.id = s.parent_id 
                      GROUP BY p.id 
                      ORDER BY p.id DESC";
    $parents_result = $conn->query($parents_query);
    
    if ($parents_result) {
        while ($row = $parents_result->fetch_assoc()) {
            $parents[] = $row;
        }
        $parents_result->free();
    } else {
        $error_message = "Error fetching parents: " . $conn->error;
    }
    
    // Fetch students for dropdown (those without parents)
    $students_query = "SELECT id, first_name, last_name, class, section 
                       FROM students 
                       WHERE parent_id IS NULL OR parent_id = 0 
                       ORDER BY first_name, last_name";
    $students_result = $conn->query($students_query);
    
    if ($students_result) {
        while ($row = $students_result->fetch_assoc()) {
            $students[] = $row;
        }
        $students_result->free();
    } else {
        $error_message = "Error fetching students: " . $conn->error;
    }

    // Close the database connection
    $conn->close();
} else {
    $error_message = "Database connection failed. Please check your configuration.";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Parent Management - Mt Zion SS</title>
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
            top: 2px;
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
        
        .alert {
            margin-bottom: 20px;
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
                    <a class="nav-link" href="teachers.php">
                        <i class="fas fa-chalkboard-teacher"></i> Teachers
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link active" href="parents.php">
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

    <!-- Main Content -->
    <main class="main-content">
        <div class="school-header">
            <div class="container">
                <h1 class="display-4">Parent Management</h1>
                <p class="lead">Manage parent records and information</p>
            </div>
        </div>
        
        <div class="container">
            <?php if ($success_message): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <?= $success_message ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>
            
            <?php if ($error_message): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <?= $error_message ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>
            
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pb-2 mb-3 border-bottom">
                <h2>Parent List</h2>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addParentModal">
                    <i class="fas fa-plus"></i> Add New Parent
                </button>
            </div>

            <div class="card">
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped table-hover">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Name</th>
                                    <th>Email</th>
                                    <th>Phone</th>
                                    <th>Students</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($parents)): ?>
                                    <?php foreach ($parents as $parent): ?>
                                        <tr>
                                            <td>P<?= str_pad($parent['id'], 3, '0', STR_PAD_LEFT) ?></td>
                                            <td><?= htmlspecialchars($parent['first_name'] . ' ' . $parent['last_name']) ?></td>
                                            <td><?= htmlspecialchars($parent['email']) ?></td>
                                            <td><?= htmlspecialchars($parent['phone']) ?></td>
                                            <td>
                                                <?php if (!empty($parent['student_names'])): ?>
                                                    <?= htmlspecialchars($parent['student_names']) ?>
                                                <?php else: ?>
                                                    No students assigned
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <button class="btn btn-sm btn-info action-btn"><i class="fas fa-eye"></i></button>
                                                <button class="btn btn-sm btn-warning action-btn"><i class="fas fa-edit"></i></button>
                                                <button class="btn btn-sm btn-danger action-btn"><i class="fas fa-trash"></i></button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="6" class="text-center">No parents found in the database.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <nav aria-label="Page navigation">
                        <ul class="pagination justify-content-center">
                            <li class="page-item disabled">
                                <a class="page-link" href="#" tabindex="-1">Previous</a>
                            </li>
                            <li class="page-item active"><a class="page-link" href="#">1</a></li>
                            <li class="page-item"><a class="page-link" href="#">2</a></li>
                            <li class="page-item"><a class="page-link" href="#">3</a></li>
                            <li class="page-item">
                                <a class="page-link" href="#">Next</a>
                            </li>
                        </ul>
                    </nav>
                </div>
            </div>
        </div>
        
        <!-- Add Parent Modal -->
        <div class="modal fade" id="addParentModal" tabindex="-1" aria-labelledby="addParentModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="addParentModalLabel">Add New Parent</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <form method="POST" action="">
                        <div class="modal-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="parentFirstName" class="form-label">First Name *</label>
                                        <input type="text" class="form-control" id="parentFirstName" name="parentFirstName" required>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="parentLastName" class="form-label">Last Name *</label>
                                        <input type="text" class="form-control" id="parentLastName" name="parentLastName" required>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="parentEmail" class="form-label">Email *</label>
                                        <input type="email" class="form-control" id="parentEmail" name="parentEmail" required>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="parentPhone" class="form-label">Phone Number *</label>
                                        <input type="tel" class="form-control" id="parentPhone" name="parentPhone" required>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="parentStudents" class="form-label">Assign Students</label>
                                <select class="form-select" id="parentStudents" name="parentStudents[]" multiple>
                                    <?php if (!empty($students)): ?>
                                        <?php foreach ($students as $student): ?>
                                            <option value="<?= $student['id'] ?>">
                                                <?= htmlspecialchars($student['first_name'] . ' ' . $student['last_name'] . ' (' . $student['class'] . '-' . $student['section'] . ')') ?>
                                            </option>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <option value="">No unassigned students available</option>
                                    <?php endif; ?>
                                </select>
                                <div class="form-text">Hold Ctrl/Cmd to select multiple students</div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="parentAddress" class="form-label">Address</label>
                                <textarea class="form-control" id="parentAddress" name="parentAddress" rows="2"></textarea>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" name="add_parent" class="btn btn-primary">Add Parent</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </main>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>