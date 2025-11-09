<?php
session_start();

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

// Database connection parameters
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "mubende_school_db";

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
$expenses = [];
$total_expenses = 0;
$this_month_expenses = 0;
$categories_count = [];
$balance = 0;

// Check if database connection is established
if ($conn) {
    // Handle form submission for adding expense
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_expense'])) {
        // Input validation
        $date = trim($_POST['date'] ?? '');
        $category = trim($_POST['category'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $amount = trim($_POST['amount'] ?? '');
        $recorded_by = $_SESSION['user_id'];

        // Validate required fields
        if (empty($date) || empty($category) || empty($description) || empty($amount)) {
            $error_message = "All fields (date, category, description, amount) are required.";
        } elseif (!is_numeric($amount) || $amount <= 0) {
            $error_message = "Amount must be a positive number.";
        } elseif (!preg_match("/^\d{4}-\d{2}-\d{2}$/", $date)) {
            $error_message = "Invalid date format. Use YYYY-MM-DD.";
        } else {
            // Insert into database if no errors
            if (empty($error_message)) {
                $stmt = $conn->prepare("INSERT INTO expenses (date, category, description, amount, recorded_by) VALUES (?, ?, ?, ?, ?)");
                if ($stmt) {
                    $stmt->bind_param("sssds", $date, $category, $description, $amount, $recorded_by);

                    if ($stmt->execute()) {
                        $success_message = "Expense added successfully!";
                    } else {
                        $error_message = "Error adding expense: " . $conn->error;
                    }
                    $stmt->close();
                } else {
                    $error_message = "Error preparing statement: " . $conn->error;
                }
            }
        }
    }

    // Handle expense deletion
    if (isset($_GET['delete_id'])) {
        $delete_id = intval($_GET['delete_id']);
        
        $stmt = $conn->prepare("DELETE FROM expenses WHERE expense_id = ?");
        if ($stmt) {
            $stmt->bind_param("i", $delete_id);
            if ($stmt->execute()) {
                $success_message = "Expense deleted successfully!";
            } else {
                $error_message = "Error deleting expense: " . $conn->error;
            }
            $stmt->close();
        } else {
            $error_message = "Error preparing delete statement: " . $conn->error;
        }
        
        // Redirect to avoid resubmission
        header("Location: expenses.php");
        exit();
    }

    // Handle expense update
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_expense'])) {
        $expense_id = intval($_POST['expense_id']);
        $date = trim($_POST['date'] ?? '');
        $category = trim($_POST['category'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $amount = trim($_POST['amount'] ?? '');

        // Validate required fields
        if (empty($date) || empty($category) || empty($description) || empty($amount)) {
            $error_message = "All fields (date, category, description, amount) are required.";
        } elseif (!is_numeric($amount) || $amount <= 0) {
            $error_message = "Amount must be a positive number.";
        } elseif (!preg_match("/^\d{4}-\d{2}-\d{2}$/", $date)) {
            $error_message = "Invalid date format. Use YYYY-MM-DD.";
        } else {
            $stmt = $conn->prepare("UPDATE expenses SET date = ?, category = ?, description = ?, amount = ? WHERE id = ?");
            if ($stmt) {
                $stmt->bind_param("sssdi", $date, $category, $description, $amount, $expense_id);
                
                if ($stmt->execute()) {
                    $success_message = "Expense updated successfully!";
                } else {
                    $error_message = "Error updating expense: " . $conn->error;
                }
                $stmt->close();
            } else {
                $error_message = "Error preparing update statement: " . $conn->error;
            }
        }
    }

    // Fetch expense data for editing
    $edit_expense = null;
    if (isset($_GET['edit_id'])) {
        $edit_id = intval($_GET['edit_id']);
        $stmt = $conn->prepare("SELECT * FROM expenses WHERE id = ?");
        if ($stmt) {
            $stmt->bind_param("i", $edit_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $edit_expense = $result->fetch_assoc();
            $stmt->close();
        }
    }

    // Fetch expenses from database
    $result = $conn->query("SELECT e.*, u.email as recorded_email FROM expenses e LEFT JOIN users u ON e.recorded_by = u.id ORDER BY e.date DESC");
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $expenses[] = $row;

            // Calculate statistics
            $total_expenses += (float)$row['amount'];

            // This month's expenses
            if (date('Y-m', strtotime($row['date'])) === date('Y-m')) {
                $this_month_expenses += (float)$row['amount'];
            }

            // Count categories
            $categories_count[$row['category']] = ($categories_count[$row['category']] ?? 0) + 1;
        }
        $result->close();
    } else {
        $error_message = "Error fetching expenses: " . $conn->error;
    }

    // Get balance
    $income_result = $conn->query("SELECT SUM(amount) as total_income FROM income");
    if ($income_result) {
        $income_row = $income_result->fetch_assoc();
        $total_income = (float)($income_row['total_income'] ?? 0);
        $balance = $total_income - $total_expenses;
        $income_result->close();
    } else {
        $error_message = "Error fetching income: " . $conn->error;
    }

    // Close the database connection
    $conn->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Expense Management - Mt Zion SS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        /* Your existing CSS styles remain the same */
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
        
        .stat-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
            margin-bottom: 20px;
        }
        
        .alert {
            margin: 20px 0;
        }
        
        .delete-btn {
            cursor: pointer;
        }
        .school-header{
            top:0;
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
                    <a class="nav-link active" href="expenses.php">
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
                <h1 class="display-4">Expense Management</h1>
                <p class="lead">Manage school expenses and financial records</p>
            </div>
        </div>
        
        <div class="container">
            <!-- Display success/error messages -->
            <?php if (!empty($success_message)): ?>
                <div class="alert alert-success"><?= $success_message ?></div>
            <?php endif; ?>
            
            <?php if (!empty($error_message)): ?>
                <div class="alert alert-danger"><?= $error_message ?></div>
            <?php endif; ?>
            
            <div class="row mb-4">
                <div class="col-md-3">
                    <div class="stat-card">
                        <div class="d-flex align-items-center">
                            <div class="bg-primary p-3 rounded me-3">
                                <i class="fas fa-money-bill-wave fa-2x text-white"></i>
                            </div>
                            <div>
                                <h6 class="text-muted">Total Expenses</h6>
                                <h3>UGX <?= number_format($total_expenses, 0) ?></h3>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-card">
                        <div class="d-flex align-items-center">
                            <div class="bg-success p-3 rounded me-3">
                                <i class="fas fa-check-circle fa-2x text-white"></i>
                            </div>
                            <div>
                                <h6 class="text-muted">This Month</h6>
                                <h3>UGX <?= number_format($this_month_expenses, 0) ?></h3>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-card">
                        <div class="d-flex align-items-center">
                            <div class="bg-info p-3 rounded me-3">
                                <i class="fas fa-chart-pie fa-2x text-white"></i>
                            </div>
                            <div>
                                <h6 class="text-muted">By Category</h6>
                                <h3><?= count($categories_count) ?> Categories</h3>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-card">
                        <div class="d-flex align-items-center">
                            <div class="bg-warning p-3 rounded me-3">
                                <i class="fas fa-balance-scale fa-2x text-white"></i>
                            </div>
                            <div>
                                <h6 class="text-muted">Balance</h6>
                                <h3>UGX <?= number_format($balance, 0) ?></h3>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pb-2 mb-3 border-bottom">
                <h2>Expense Records</h2>
                <div>
                    <button class="btn btn-outline-primary me-2">
                        <i class="fas fa-download"></i> Export
                    </button>
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addExpenseModal">
                        <i class="fas fa-plus"></i> Add Expense
                    </button>
                </div>
            </div>

            <div class="card">
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped table-hover">
                            <thead>
                                <tr>
                                    <th>Expense ID</th>
                                    <th>Date</th>
                                    <th>Category</th>
                                    <th>Description</th>
                                    <th>Amount (UGX)</th>
                                    <th>Recorded By</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (count($expenses) > 0): ?>
                                    <?php foreach ($expenses as $expense): ?>
                                        <tr>
                                            <td>EXP-<?= str_pad($expense['expense_id'], 3, '0', STR_PAD_LEFT) ?></td>
                                            <td><?= $expense['date'] ?></td>
                                            <td><?= $expense['category'] ?></td>
                                            <td><?= $expense['description'] ?></td>
                                            <td>UGX <?= number_format($expense['amount'], 0) ?></td>
                                            <td><?= $expense['recorded_email'] ?></td>
                                            <td>
                                                <!-- Edit Button -->
                                                <a href="?edit_id=<?= $expense['expense_id'] ?>" class="btn btn-sm btn-warning action-btn" data-bs-toggle="modal" data-bs-target="#editExpenseModal">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                
                                                <!-- Delete Button with Confirmation -->
                                                <button class="btn btn-sm btn-danger action-btn delete-btn" 
                                                        onclick="confirmDelete(<?= $expense['expense_id'] ?>)">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="7" class="text-center">No expenses recorded yet.</td>
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
        
        <!-- Add Expense Modal -->
        <div class="modal fade" id="addExpenseModal" tabindex="-1" aria-labelledby="addExpenseModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="addExpenseModalLabel">Add New Expense</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <form method="POST" enctype="multipart/form-data">
                        <div class="modal-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="expenseDate" class="form-label">Date</label>
                                        <input type="date" class="form-control" id="expenseDate" name="date" required value="<?= date('Y-m-d') ?>">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="expenseCategory" class="form-label">Category</label>
                                        <select class="form-select" id="expenseCategory" name="category" required>
                                            <option value="">Select Category</option>
                                            <option value="Salaries">Salaries</option>
                                            <option value="Utilities">Utilities</option>
                                            <option value="Maintenance">Maintenance</option>
                                            <option value="Supplies">Supplies</option>
                                            <option value="Sports">Sports</option>
                                            <option value="Other">Other</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="expenseDescription" class="form-label">Description</label>
                                <textarea class="form-control" id="expenseDescription" name="description" rows="2" required></textarea>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="expenseAmount" class="form-label">Amount (UGX)</label>
                                        <input type="number" class="form-control" id="expenseAmount" name="amount" required min="0" step="0.01">
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" name="submit_expense" class="btn btn-primary">Add Expense</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Edit Expense Modal -->
        <div class="modal fade" id="editExpenseModal" tabindex="-1" aria-labelledby="editExpenseModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="editExpenseModalLabel">Edit Expense</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <form method="POST">
                        <div class="modal-body">
                            <input type="hidden" name="expense_id" value="<?= $edit_expense['id'] ?? '' ?>">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="editExpenseDate" class="form-label">Date</label>
                                        <input type="date" class="form-control" id="editExpenseDate" name="date" required 
                                               value="<?= $edit_expense['date'] ?? '' ?>">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="editExpenseCategory" class="form-label">Category</label>
                                        <select class="form-select" id="editExpenseCategory" name="category" required>
                                            <option value="">Select Category</option>
                                            <option value="Salaries" <?= ($edit_expense['category'] ?? '') === 'Salaries' ? 'selected' : '' ?>>Salaries</option>
                                            <option value="Utilities" <?= ($edit_expense['category'] ?? '') === 'Utilities' ? 'selected' : '' ?>>Utilities</option>
                                            <option value="Maintenance" <?= ($edit_expense['category'] ?? '') === 'Maintenance' ? 'selected' : '' ?>>Maintenance</option>
                                            <option value="Supplies" <?= ($edit_expense['category'] ?? '') === 'Supplies' ? 'selected' : '' ?>>Supplies</option>
                                            <option value="Sports" <?= ($edit_expense['category'] ?? '') === 'Sports' ? 'selected' : '' ?>>Sports</option>
                                            <option value="Other" <?= ($edit_expense['category'] ?? '') === 'Other' ? 'selected' : '' ?>>Other</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="editExpenseDescription" class="form-label">Description</label>
                                <textarea class="form-control" id="editExpenseDescription" name="description" rows="2" required><?= $edit_expense['description'] ?? '' ?></textarea>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="editExpenseAmount" class="form-label">Amount (UGX)</label>
                                        <input type="number" class="form-control" id="editExpenseAmount" name="amount" required 
                                               min="0" step="0.01" value="<?= $edit_expense['amount'] ?? '' ?>">
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" name="update_expense" class="btn btn-primary">Update Expense</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </main>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Function to confirm expense deletion
        function confirmDelete(expenseId) {
            if (confirm('Are you sure you want to delete this expense? This action cannot be undone.')) {
                window.location.href = '?delete_id=' + expenseId;
            }
        }

        // Show edit modal if edit_id parameter is present
        <?php if (isset($_GET['edit_id']) && $edit_expense): ?>
            document.addEventListener('DOMContentLoaded', function() {
                var editModal = new bootstrap.Modal(document.getElementById('editExpenseModal'));
                editModal.show();
            });
        <?php endif; ?>

        // Close edit modal and remove URL parameter when modal is hidden
        document.addEventListener('DOMContentLoaded', function() {
            var editModal = document.getElementById('editExpenseModal');
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
    </script>
</body>
</html>