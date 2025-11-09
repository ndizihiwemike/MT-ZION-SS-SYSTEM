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
$requirements = [];

// Check if database connection is established
if ($conn) {
    // Handle form submissions
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (isset($_POST['add_requirement'])) {
            // Input validation
            $term = trim($_POST['term'] ?? '');
            $year = trim($_POST['year'] ?? '');
            $class = trim($_POST['class'] ?? '');
            $items = trim($_POST['items'] ?? '');

            // Validate required fields
            if (empty($term) || empty($year) || empty($class) || empty($items)) {
                $error_message = "All fields (term, year, class, items) are required.";
            } elseif (!is_numeric($year) || $year < 1900 || $year > date('Y') + 1) {
                $error_message = "Invalid year. Must be between 1900 and " . (date('Y') + 1) . ".";
            } else {
                $stmt = $conn->prepare("INSERT INTO requirements (term, year, class, items) VALUES (?, ?, ?, ?)");
                if ($stmt) {
                    $stmt->bind_param("siss", $term, $year, $class, $items);
                    if ($stmt->execute()) {
                        $success_message = "Requirement added successfully!";
                    } else {
                        $error_message = "Error adding requirement: " . $conn->error;
                    }
                    $stmt->close();
                } else {
                    $error_message = "Error preparing statement: " . $conn->error;
                }
            }
        } elseif (isset($_POST['update_requirement'])) {
            // Input validation
            $id = trim($_POST['id'] ?? '');
            $term = trim($_POST['term'] ?? '');
            $year = trim($_POST['year'] ?? '');
            $class = trim($_POST['class'] ?? '');
            $items = trim($_POST['items'] ?? '');

            // Validate required fields
            if (empty($id) || empty($term) || empty($year) || empty($class) || empty($items)) {
                $error_message = "All fields (ID, term, year, class, items) are required.";
            } elseif (!is_numeric($id) || $id <= 0) {
                $error_message = "Invalid requirement ID.";
            } elseif (!is_numeric($year) || $year < 1900 || $year > date('Y') + 1) {
                $error_message = "Invalid year. Must be between 1900 and " . (date('Y') + 1) . ".";
            } else {
                $stmt = $conn->prepare("UPDATE requirements SET term=?, year=?, class=?, items=? WHERE id=?");
                if ($stmt) {
                    $stmt->bind_param("sissi", $term, $year, $class, $items, $id);
                    if ($stmt->execute()) {
                        $success_message = "Requirement updated successfully!";
                    } else {
                        $error_message = "Error updating requirement: " . $conn->error;
                    }
                    $stmt->close();
                } else {
                    $error_message = "Error preparing statement: " . $conn->error;
                }
            }
        } elseif (isset($_POST['delete_requirement'])) {
            // Input validation
            $id = trim($_POST['id'] ?? '');

            // Validate required field
            if (empty($id) || !is_numeric($id) || $id <= 0) {
                $error_message = "Invalid requirement ID.";
            } else {
                $stmt = $conn->prepare("DELETE FROM requirements WHERE id=?");
                if ($stmt) {
                    $stmt->bind_param("i", $id);
                    if ($stmt->execute()) {
                        $success_message = "Requirement deleted successfully!";
                    } else {
                        $error_message = "Error deleting requirement: " . $conn->error;
                    }
                    $stmt->close();
                } else {
                    $error_message = "Error preparing statement: " . $conn->error;
                }
            }
        }

        // Refresh page to show changes only if no errors
        if (empty($error_message)) {
            header("Location: re][quirements.php");
            exit();
        }
    }

    // Fetch all requirements from database
    $result = $conn->query("SELECT * FROM requirements ORDER BY year DESC, term, class");
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $requirements[] = $row;
        }
        $result->free();
    } else {
        $error_message = "Error fetching requirements: " . $conn->error;
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
    <title>School Requirements - MT ZION SS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        /* Same styles as students.php */
        :root {
            --primary: rgba(40, 9, 236, 1);
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
            padding-top: 50px;
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
        .text-muted {
            color: var(--light) !important;
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
        
        .requirement-card {
            border-left: 4px solid var(--primary);
            transition: transform 0.2s;
        }
        
        .requirement-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        
        .empty-state {
            text-align: center;
            padding: 40px;
            color: #6c757d;
        }
        
        .empty-state i {
            font-size: 3rem;
            margin-bottom: 15px;
        }
        .sidebar col-md-3 col-lg-2 d-md-block{
            top:0;
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar col-md-3 col-lg-2 d-md-block">
        <div class="position-sticky">
            <div class="text-center mb-4">
                <h4>MT ZION SS</h4>
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
    <main class="main-content container mt-4">
        <div class="school-header mb-4">
            <h1 class="display-4">School Requirements</h1>
            <p class="lead text-muted">Manage school requirements for each term</p>
        </div>

        <!-- Display Success/Error Messages -->
        <?php if (!empty($success_message)): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?php echo htmlspecialchars($success_message); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>
        <?php if (!empty($error_message)): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?php echo htmlspecialchars($error_message); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pb-2 mb-3 border-bottom">
            <h2>Term Requirements</h2>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addRequirementModal">
                <i class="fas fa-plus me-2"></i> Add Requirements
            </button>
        </div>

        <div class="row">
            <?php if (count($requirements) > 0): ?>
                <?php foreach ($requirements as $requirement): 
                    $items = explode("\n", $requirement['items']);
                ?>
                    <div class="col-md-6 col-lg-4 mb-4">
                        <div class="card requirement-card h-100">
                            <div class="card-header bg-light d-flex justify-content-between align-items-center">
                                <h5 class="mb-0"><?php echo htmlspecialchars($requirement['term'] . ' - ' . $requirement['year']); ?></h5>
                                <div>
                                    <button class="btn btn-sm btn-warning action-btn edit-btn me-1" 
                                            data-id="<?php echo htmlspecialchars($requirement['id']); ?>"
                                            data-term="<?php echo htmlspecialchars($requirement['term']); ?>"
                                            data-year="<?php echo htmlspecialchars($requirement['year']); ?>"
                                            data-class="<?php echo htmlspecialchars($requirement['class']); ?>"
                                            data-items="<?php echo htmlspecialchars($requirement['items']); ?>">
                                        <i class="fas fa-edit"></i> Edit
                                    </button>
                                    <form method="POST" style="display:inline;">
                                        <button type="submit" name="delete_requirement" class="btn btn-sm btn-danger action-btn" 
                                                onclick="return confirm('Are you sure you want to delete these requirements?')">
                                            <i class="fas fa-trash"></i> Delete
                                        </button>
                                    </form>
                                </div>
                            </div>
                            <div class="card-body">
                                <h6 class="text-muted mb-3">Requirements for <?php echo htmlspecialchars($requirement['class']); ?></h6>
                                <ul class="list-group list-group-flush">
                                    <?php foreach ($items as $item): 
                                        if (!empty(trim($item))): ?>
                                            <li class="list-group-item"><?php echo htmlspecialchars($item); ?></li>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="col-12">
                    <div class="empty-state text-center py-5">
                        <i class="fas fa-clipboard-list fa-3x text-muted mb-3"></i>
                        <h4>No Requirements Added Yet</h4>
                        <p class="text-muted">Click the "Add Requirements" button to get started.</p>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <!-- Add Requirement Modal -->
        <div class="modal fade" id="addRequirementModal" tabindex="-1" aria-labelledby="addRequirementModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <form method="POST">
                        <div class="modal-header">
                            <h5 class="modal-title" id="addRequirementModalLabel">Add New Requirements</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="requirementTerm" class="form-label">Term</label>
                                        <select class="form-select" id="requirementTerm" name="term" required>
                                            <option value="" disabled selected>Select Term</option>
                                            <option value="Term 1">Term 1</option>
                                            <option value="Term 2">Term 2</option>
                                            <option value="Term 3">Term 3</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="requirementYear" class="form-label">Year</label>
                                        <input type="number" class="form-control" id="requirementYear" name="year" min="2020" max="<?php echo date('Y') + 1; ?>" value="<?php echo date('Y'); ?>" required>
                                    </div>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label for="requirementClass" class="form-label">Class</label>
                                <select class="form-select" id="requirementClass" name="class" required>
                                    <option value="" disabled selected>Select Class</option>
                                    <option value="All Classes">All Classes</option>
                                    <option value="S1">S1</option>
                                    <option value="S2">S2</option>
                                    <option value="S3">S3</option>
                                    <option value="S4">S4</option>
                                    <option value="S5">S5</option>
                                    <option value="S6">S6</option>
                                    <option value="S1-S3">S1-S3</option>
                                    <option value="S4-S6">S4-S6</option>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label for="requirementItems" class="form-label">Requirements</label>
                                <textarea class="form-control" id="requirementItems" name="items" rows="5" placeholder="Enter each requirement on a new line" required></textarea>
                                <div class="form-text">Enter each requirement on a separate line</div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" name="add_requirement" class="btn btn-primary">Add Requirements</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Edit Requirement Modal -->
        <div class="modal fade" id="editRequirementModal" tabindex="-1" aria-labelledby="editRequirementModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <form method="POST">
                        <input type="hidden" id="editId" name="id">
                        <div class="modal-header">
                            <h5 class="modal-title" id="editRequirementModalLabel">Edit Requirements</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="editTerm" class="form-label">Term</label>
                                        <select class="form-select" id="editTerm" name="term" required>
                                            <option value="" disabled>Select Term</option>
                                            <option value="Term 1">Term 1</option>
                                            <option value="Term 2">Term 2</option>
                                            <option value="Term 3">Term 3</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="editYear" class="form-label">Year</label>
                                        <input type="number" class="form-control" id="editYear" name="year" min="2020" max="<?php echo date('Y') + 1; ?>" required>
                                    </div>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label for="editClass" class="form-label">Class</label>
                                <select class="form-select" id="editClass" name="class" required>
                                    <option value="" disabled>Select Class</option>
                                    <option value="All Classes">All Classes</option>
                                    <option value="S1">S1</option>
                                    <option value="S2">S2</option>
                                    <option value="S3">S3</option>
                                    <option value="S4">S4</option>
                                    <option value="S5">S5</option>
                                    <option value="S6">S6</option>
                                    <option value="S1-S3">S1-S3</option>
                                    <option value="S4-S6">S4-S6</option>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label for="editItems" class="form-label">Requirements</label>
                                <textarea class="form-control" id="editItems" name="items" rows="5" required></textarea>
                                <div class="form-text">Enter each requirement on a separate line</div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" name="update_requirement" class="btn btn-primary">Update Requirements</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </main>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Handle edit button clicks
        document.querySelectorAll('.edit-btn').forEach(button => {
            button.addEventListener('click', function() {
                const id = this.getAttribute('data-id');
                const term = this.getAttribute('data-term');
                const year = this.getAttribute('data-year');
                const cls = this.getAttribute('data-class');
                const items = this.getAttribute('data-items');
                
                document.getElementById('editId').value = id;
                document.getElementById('editTerm').value = term || '';
                document.getElementById('editYear').value = year || '';
                document.getElementById('editClass').value = cls || '';
                document.getElementById('editItems').value = items || '';
                
                // Show the modal
                const editModal = new bootstrap.Modal(document.getElementById('editRequirementModal'));
                editModal.show();
            });
        });
    </script>
</body>
</html>