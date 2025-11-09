<?php
chdir('../');
session_start();
require_once('db/config.php');
require_once('const/school.php');
require_once('const/check_session.php');
if ($res == "1" && $level == "0") {}else{header("location:../");}

// Initialize variables
$success_message = '';
$error_message = '';
$payments = [];
$students = [];
$total_revenue = 0;
$paid_fees = 0;
$pending_payments = 0;
$overdue_payments = 0;
$edit_payment = null;

try {
    $conn = new PDO('mysql:host='.DBHost.';dbname='.DBName.';charset='.DBCharset.';collation='.DBCollation.';prefix='.DBPrefix.'', DBUser, DBPass);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Handle form submission for recording a payment
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['record_payment'])) {
        // Input validation
        $student_id = trim($_POST['student_id'] ?? '');
        $amount = trim($_POST['amount'] ?? '');
        $payment_date = trim($_POST['payment_date'] ?? '');
        $term = trim($_POST['term'] ?? '');
        $payment_for = trim($_POST['payment_for'] ?? '');
        $status = trim($_POST['status'] ?? '');

        // Validate required fields
        if (empty($student_id) || empty($amount) || empty($payment_date) || empty($term) || empty($payment_for) || empty($status)) {
            $error_message = "All fields (student, amount, payment date, term, payment for, status) are required.";
        } elseif (!is_numeric($amount) || $amount <= 0) {
            $error_message = "Amount must be a positive number.";
        } elseif (!preg_match("/^\d{4}-\d{2}-\d{2}$/", $payment_date)) {
            $error_message = "Invalid payment date format. Use YYYY-MM-DD.";
        } elseif (!in_array($status, ['Paid', 'Pending', 'Overdue', 'Partial'])) {
            $error_message = "Invalid status. Choose Paid, Pending, Overdue, or Partial.";
        } else {
            // Verify student exists and get the numeric ID
            $stmt = $conn->prepare("SELECT student_id FROM tbl_students WHERE student_id = ?");
            $stmt->execute([$student_id]);
            $result = $stmt->fetchAll();

            if (count($result) === 0) {
                $error_message = "Selected student does not exist.";
            } else {
                // Insert into database
                $stmt = $conn->prepare("INSERT INTO payments (student_id, amount, payment_date, term, payment_for, status) 
                        VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->execute([$student_id, $amount, $payment_date, $term, $payment_for, $status]);
                
                $_SESSION['reply'] = "010";
                header("location:admin/finances");
                exit();
            }
        }
    }

    // Handle payment update
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_payment'])) {
        $payment_id = trim($_POST['payment_id'] ?? '');
        $student_id = trim($_POST['student_id'] ?? '');
        $amount = trim($_POST['amount'] ?? '');
        $payment_date = trim($_POST['payment_date'] ?? '');
        $term = trim($_POST['term'] ?? '');
        $payment_for = trim($_POST['payment_for'] ?? '');
        $status = trim($_POST['status'] ?? '');

        // Validate required fields
        if (empty($payment_id) || empty($student_id) || empty($amount) || empty($payment_date) || empty($term) || empty($payment_for) || empty($status)) {
            $error_message = "All fields are required.";
        } elseif (!is_numeric($amount) || $amount <= 0) {
            $error_message = "Amount must be a positive number.";
        } elseif (!preg_match("/^\d{4}-\d{2}-\d{2}$/", $payment_date)) {
            $error_message = "Invalid payment date format. Use YYYY-MM-DD.";
        } elseif (!in_array($status, ['Paid', 'Pending', 'Overdue', 'Partial'])) {
            $error_message = "Invalid status. Choose Paid, Pending, Overdue, or Partial.";
        } else {
            // Update payment in database
            $stmt = $conn->prepare("UPDATE payments SET student_id = ?, amount = ?, payment_date = ?, term = ?, payment_for = ?, status = ? WHERE payment_id = ?");
            $stmt->execute([$student_id, $amount, $payment_date, $term, $payment_for, $status, $payment_id]);
            
            $_SESSION['reply'] = "011";
            header("location:admin/finances");
            exit();
        }
    }

    // Fetch payment for editing
    if (isset($_GET['edit_id'])) {
        $edit_id = $_GET['edit_id'];
        $stmt = $conn->prepare("SELECT p.*, s.fname, s.mname, s.lname, s.class as student_class 
                FROM payments p 
                LEFT JOIN tbl_students s ON p.student_id = s.student_id 
                WHERE p.payment_id = ?");
        $stmt->execute([$edit_id]);
        $edit_payment = $stmt->fetch();
    }

    // Fetch payment records from database
    $stmt = $conn->prepare("SELECT p.*, s.fname, s.mname, s.lname, s.class as student_class 
            FROM payments p 
            LEFT JOIN tbl_students s ON p.student_id = s.student_id 
            ORDER BY p.payment_date DESC");
    $stmt->execute();
    $payments = $stmt->fetchAll();

    // Fetch students for dropdown
    $stmt = $conn->prepare("SELECT student_id, fname, mname, lname, class FROM tbl_students WHERE status = 1 ORDER BY fname, lname");
    $stmt->execute();
    $students = $stmt->fetchAll();

    // Calculate financial statistics
    $stmt = $conn->prepare("SELECT status, SUM(amount) as total FROM payments GROUP BY status");
    $stmt->execute();
    $stats_result = $stmt->fetchAll();

    foreach ($stats_result as $row) {
        $total_revenue += (float)$row['total'];

        if ($row['status'] === 'Paid') {
            $paid_fees = (float)$row['total'];
        } elseif ($row['status'] === 'Pending') {
            $pending_payments = (float)$row['total'];
        } elseif ($row['status'] === 'Overdue') {
            $overdue_payments = (float)$row['total'];
        }
    }

    // Calculate student payment summaries
    $student_payments = [];
    $stmt = $conn->prepare("SELECT student_id, SUM(amount) as total_paid, COUNT(*) as payment_count 
                           FROM payments WHERE status = 'Paid' GROUP BY student_id");
    $stmt->execute();
    $payment_summaries = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($payment_summaries as $summary) {
        $student_payments[$summary['student_id']] = $summary;
    }

} catch(PDOException $e) {
    $error_message = "Database error: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<meta http-equiv="content-type" content="text/html;charset=utf-8" />
<head>
<title>MTZSS - Financial Management</title>
<meta charset="utf-8">
<meta http-equiv="X-UA-Compatible" content="IE=edge">
<meta name="viewport" content="width=device-width, initial-scale=1">
<base href="../">
<link rel="stylesheet" type="text/css" href="css/main.css">
<link rel="icon" href="images/icon.ico">
<link rel="stylesheet" type="text/css" href="cdn.jsdelivr.net/npm/bootstrap-icons%401.10.5/font/bootstrap-icons.css">
<link type="text/css" rel="stylesheet" href="loader/waitMe.css">
</head>
<body class="app sidebar-mini">

<header class="app-header"><a class="app-header__logo" href="javascript:void(0);">MTZSS</a>
<a class="app-sidebar__toggle" href="#" data-toggle="sidebar" aria-label="Hide Sidebar"></a>

<ul class="app-nav">

<li class="dropdown"><a class="app-nav__item" href="#" data-bs-toggle="dropdown" aria-label="Open Profile Menu"><i class="bi bi-person fs-4"></i></a>
<ul class="dropdown-menu settings-menu dropdown-menu-right">
<li><a class="dropdown-item" href="admin/profile"><i class="bi bi-person me-2 fs-5"></i> Profile</a></li>
<li><a class="dropdown-item" href="logout"><i class="bi bi-box-arrow-right me-2 fs-5"></i> Logout</a></li>
</ul>
</li>
</ul>
</header>

<div class="app-sidebar__overlay" data-toggle="sidebar"></div>
<aside class="app-sidebar">
<div class="app-sidebar__user">
<div>
<p class="app-sidebar__user-name"><?php echo $fname.' '.$lname; ?></p>
<p class="app-sidebar__user-designation">Administrator</p>
</div>
</div>
<ul class="app-menu">
<li><a class="app-menu__item" href="admin"><i class="app-menu__icon feather icon-monitor"></i><span class="app-menu__label">Dashboard</span></a></li>
<li><a class="app-menu__item" href="admin/academic"><i class="app-menu__icon feather icon-user"></i><span class="app-menu__label">Academic Account</span></a></li>
<li><a class="app-menu__item" href="admin/teachers"><i class="app-menu__icon feather icon-user"></i><span class="app-menu__label">Teachers</span></a></li>
<li><a class="app-menu__item active" href="admin/finances"><i class="app-menu__icon feather icon-dollar-sign"></i><span class="app-menu__label">Finances</span></a></li>

<li class="treeview"><a class="app-menu__item" href="javascript:void(0);" data-toggle="treeview"><i class="app-menu__icon feather icon-users"></i><span class="app-menu__label">Students</span><i class="treeview-indicator bi bi-chevron-right"></i></a>
<ul class="treeview-menu">
<li><a class="treeview-item" href="admin/register_students"><i class="icon bi bi-circle-fill"></i> Register Students</a></li>
<li><a class="treeview-item" href="admin/import_students"><i class="icon bi bi-circle-fill"></i> Import Students</a></li>
<li><a class="treeview-item" href="admin/manage_students"><i class="icon bi bi-circle-fill"></i> Manage Students</a></li>
</ul>
</li>
<li><a class="app-menu__item" href="admin/report"><i class="app-menu__icon feather icon-bar-chart-2"></i><span class="app-menu__label">Report Tool</span></a></li>
<li><a class="app-menu__item" href="admin/smtp"><i class="app-menu__icon feather icon-mail"></i><span class="app-menu__label">SMTP Settings</span></a></li>
<li><a class="app-menu__item" href="admin/system"><i class="app-menu__icon feather icon-settings"></i><span class="app-menu__label">System Settings</span></a></li>
</ul>
</aside>

<main class="app-content">
<div class="app-title">
<div>
<h1>Financial Management</h1>
</div>
<ul class="app-breadcrumb breadcrumb">
<li class="breadcrumb-item"><button class="btn btn-primary btn-sm" type="button" data-bs-toggle="modal" data-bs-target="#recordPaymentModal">Record Payment</button></li>
</ul>
</div>

<?php if ($error_message): ?>
<div class="alert alert-danger alert-dismissible fade show" role="alert">
    <?= $error_message ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
</div>
<?php endif; ?>

<div class="row">
<div class="col-md-3">
<div class="widget-small primary coloured-icon"><i class="icon feather icon-dollar-sign fs-1"></i>
<div class="info">
<h4>Total Revenue</h4>
<p><b>UGX <?php echo number_format($total_revenue, 0); ?></b></p>
</div>
</div>
</div>
<div class="col-md-3">
<div class="widget-small success coloured-icon"><i class="icon feather icon-check-circle fs-1"></i>
<div class="info">
<h4>Paid Fees</h4>
<p><b>UGX <?php echo number_format($paid_fees, 0); ?></b></p>
</div>
</div>
</div>
<div class="col-md-3">
<div class="widget-small warning coloured-icon"><i class="icon feather icon-clock fs-1"></i>
<div class="info">
<h4>Pending</h4>
<p><b>UGX <?php echo number_format($pending_payments, 0); ?></b></p>
</div>
</div>
</div>
<div class="col-md-3">
<div class="widget-small danger coloured-icon"><i class="icon feather icon-alert-triangle fs-1"></i>
<div class="info">
<h4>Overdue</h4>
<p><b>UGX <?php echo number_format($overdue_payments, 0); ?></b></p>
</div>
</div>
</div>
</div>

<div class="row">
<div class="col-md-12">
<div class="tile">
<div class="tile-body">
<div class="table-responsive">
<table class="table table-hover table-bordered" id="paymentsTable">
<thead>
<tr>
<th>Payment ID</th>
<th>Student</th>
<th>Date</th>
<th>Amount</th>
<th>Term</th>
<th>Payment For</th>
<th>Status</th>
<th width="150">Actions</th>
</tr>
</thead>
<tbody>

<?php
if (count($payments) > 0) {
    foreach($payments as $payment) {
        $badge_class = 'bg-secondary';
        if ($payment['status'] === 'Paid') $badge_class = 'bg-success';
        if ($payment['status'] === 'Pending') $badge_class = 'bg-warning';
        if ($payment['status'] === 'Overdue') $badge_class = 'bg-danger';
        if ($payment['status'] === 'Partial') $badge_class = 'bg-info';
        
        // Build student name with middle name if available
        $student_name = htmlspecialchars($payment['fname']);
        if (!empty($payment['mname'])) {
            $student_name .= ' ' . htmlspecialchars($payment['mname']);
        }
        $student_name .= ' ' . htmlspecialchars($payment['lname']);
        
        // Show payment summary for student
        $payment_summary = '';
        if (isset($student_payments[$payment['student_id']]) && $student_payments[$payment['student_id']]['payment_count'] > 1) {
            $summary = $student_payments[$payment['student_id']];
            $payment_summary = '<br><small class="text-muted">Total: UGX ' . number_format($summary['total_paid'], 0) . ' (' . $summary['payment_count'] . ' payments)</small>';
        }
?>
<tr>
<td><?php echo htmlspecialchars($payment['payment_id']); ?></td>
<td><?php echo $student_name . ' (' . htmlspecialchars($payment['student_class']) . ')' . $payment_summary; ?></td>
<td><?php echo date('d M Y', strtotime($payment['payment_date'])); ?></td>
<td>UGX <?php echo number_format($payment['amount'], 0); ?></td>
<td><?php echo htmlspecialchars($payment['term']); ?></td>
<td><?php echo htmlspecialchars($payment['payment_for']); ?></td>
<td><span class="badge <?php echo $badge_class; ?>"><?php echo htmlspecialchars($payment['status']); ?></span></td>
<td>
    <button class="btn btn-primary btn-sm" onclick="editPayment('<?php echo $payment['payment_id']; ?>')">Edit</button>
    <button class="btn btn-danger btn-sm" onclick="deletePayment('<?php echo $payment['payment_id']; ?>')">Delete</button>
</td>
</tr>
<?php
    }
} else {
    echo '<tr><td colspan="9" class="text-center">No payment records found</td></tr>';
}
?>

</tbody>
</table>
</div>
</div>
</div>
</div>
</div>

</main>

<!-- Record Payment Modal -->
<div class="modal fade" id="recordPaymentModal" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1" aria-labelledby="recordPaymentModalLabel" aria-hidden="true">
<div class="modal-dialog">
<div class="modal-content">
<div class="modal-header">
<h5 class="modal-title" id="recordPaymentModalLabel">Record New Payment</h5>
<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
</div>
<form method="POST" action="">
<div class="modal-body">
<div class="mb-2">
<label class="form-label">Student *</label>
<select class="form-control" name="student_id" required>
<option value="">Select Student</option>
<?php foreach($students as $student): 
    $student_name = htmlspecialchars($student['fname']);
    if (!empty($student['mname'])) {
        $student_name .= ' ' . htmlspecialchars($student['mname']);
    }
    $student_name .= ' ' . htmlspecialchars($student['lname']);
?>
<option value="<?php echo $student['student_id']; ?>"><?php echo $student_name . ' (' . $student['class'] . ')'; ?></option>
<?php endforeach; ?>
</select>
</div>
<div class="mb-2">
<label class="form-label">Amount (UGX) *</label>
<input type="number" class="form-control" name="amount" step="1000" min="0" required>
</div>
<div class="mb-2">
<label class="form-label">Payment Date *</label>
<input type="date" class="form-control" name="payment_date" required>
</div>
<div class="mb-2">
<label class="form-label">Term *</label>
<select class="form-control" name="term" required>
<option value="">Select Term</option>
<option value="Term 1">Term 1</option>
<option value="Term 2">Term 2</option>
<option value="Term 3">Term 3</option>
</select>
</div>
<div class="mb-2">
<label class="form-label">Payment For *</label>
<select class="form-control" name="payment_for" required>
<option value="">Select Category</option>
<option value="Tuition Fee">Tuition Fee</option>
<option value="Examination Fee">Examination Fee</option>
<option value="Development Fee">Development Fee</option>
<option value="Sports Fee">Sports Fee</option>
<option value="Other">Other</option>
</select>
</div>
<div class="mb-2">
<label class="form-label">Status *</label>
<select class="form-control" name="status" required>
<option value="Paid">Paid</option>
<option value="Pending">Pending</option>
<option value="Partial">Partial</option>
<option value="Overdue">Overdue</option>
</select>
</div>

</div>
<div class="modal-footer">
<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
<button type="submit" name="record_payment" class="btn btn-primary">Record Payment</button>
</div>
</form>
</div>
</div>
</div>

<!-- Edit Payment Modal -->
<div class="modal fade" id="editPaymentModal" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1" aria-labelledby="editPaymentModalLabel" aria-hidden="true">
<div class="modal-dialog">
<div class="modal-content">
<div class="modal-header">
<h5 class="modal-title" id="editPaymentModalLabel">Edit Payment</h5>
<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
</div>
<form method="POST" action="">
<input type="hidden" name="payment_id" id="edit_payment_id">
<div class="modal-body">
<div class="mb-2">
<label class="form-label">Student *</label>
<select class="form-control" name="student_id" id="edit_student_id" required>
<option value="">Select Student</option>
<?php foreach($students as $student): 
    $student_name = htmlspecialchars($student['fname']);
    if (!empty($student['mname'])) {
        $student_name .= ' ' . htmlspecialchars($student['mname']);
    }
    $student_name .= ' ' . htmlspecialchars($student['lname']);
?>
<option value="<?php echo $student['student_id']; ?>"><?php echo $student_name . ' (' . $student['class'] . ')'; ?></option>
<?php endforeach; ?>
</select>
</div>
<div class="mb-2">
<label class="form-label">Amount (UGX) *</label>
<input type="number" class="form-control" name="amount" id="edit_amount" step="1000" min="0" required>
</div>
<div class="mb-2">
<label class="form-label">Payment Date *</label>
<input type="date" class="form-control" name="payment_date" id="edit_payment_date" required>
</div>
<div class="mb-2">
<label class="form-label">Term *</label>
<select class="form-control" name="term" id="edit_term" required>
<option value="">Select Term</option>
<option value="Term 1">Term 1</option>
<option value="Term 2">Term 2</option>
<option value="Term 3">Term 3</option>
</select>
</div>
<div class="mb-2">
<label class="form-label">Payment For *</label>
<select class="form-control" name="payment_for" id="edit_payment_for" required>
<option value="">Select Category</option>
<option value="Tuition Fee">Tuition Fee</option>
<option value="Examination Fee">Examination Fee</option>
<option value="Development Fee">Development Fee</option>
<option value="Sports Fee">Sports Fee</option>
<option value="Other">Other</option>
</select>
</div>
<div class="mb-2">
<label class="form-label">Status *</label>
<select class="form-control" name="status" id="edit_status" required>
<option value="Paid">Paid</option>
<option value="Pending">Pending</option>
<option value="Partial">Partial</option>
<option value="Overdue">Overdue</option>
</select>
</div>
</div>
<div class="modal-footer">
<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
<button type="submit" name="update_payment" class="btn btn-primary">Update Payment</button>
</div>
</form>
</div>
</div>
</div>

<script src="js/jquery-3.7.0.min.js"></script>
<script src="js/bootstrap.min.js"></script>
<script src="js/main.js"></script>
<script src="loader/waitMe.js"></script>
<script src="js/sweetalert2@11.js"></script>
<script src="js/forms.js"></script>

<script>
function editPayment(paymentId) {
    // Fetch payment details via AJAX
    fetch('admin/core/get_payment_details?id=' + paymentId)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Populate the edit form
                document.getElementById('edit_payment_id').value = data.payment.payment_id;
                document.getElementById('edit_student_id').value = data.payment.student_id;
                document.getElementById('edit_amount').value = data.payment.amount;
                document.getElementById('edit_payment_date').value = data.payment.payment_date;
                document.getElementById('edit_term').value = data.payment.term;
                document.getElementById('edit_payment_for').value = data.payment.payment_for;
                document.getElementById('edit_status').value = data.payment.status;
                
                // Show the edit modal
                var editModal = new bootstrap.Modal(document.getElementById('editPaymentModal'));
                editModal.show();
            } else {
                Swal.fire('Error', 'Failed to load payment details', 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            Swal.fire('Error', 'Failed to load payment details', 'error');
        });
}

function deletePayment(paymentId) {
    Swal.fire({
        title: 'Are you sure?',
        text: "You won't be able to revert this!",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#3085d6',
        confirmButtonText: 'Yes, delete it!'
    }).then((result) => {
        if (result.isConfirmed) {
            window.location.href = 'admin/core/delete_payment?id=' + paymentId;
        }
    });
}

// Set today's date as default for payment date
document.addEventListener('DOMContentLoaded', function() {
    const today = new Date().toISOString().split('T')[0];
    document.querySelector('input[name="payment_date"]').value = today;
    
    // Show edit modal if edit_id is in URL
    <?php if (isset($_GET['edit_id']) && $edit_payment): ?>
        document.getElementById('edit_payment_id').value = '<?php echo $edit_payment['payment_id']; ?>';
        document.getElementById('edit_student_id').value = '<?php echo $edit_payment['student_id']; ?>';
        document.getElementById('edit_amount').value = '<?php echo $edit_payment['amount']; ?>';
        document.getElementById('edit_payment_date').value = '<?php echo $edit_payment['payment_date']; ?>';
        document.getElementById('edit_term').value = '<?php echo $edit_payment['term']; ?>';
        document.getElementById('edit_payment_for').value = '<?php echo $edit_payment['payment_for']; ?>';
        document.getElementById('edit_status').value = '<?php echo $edit_payment['status']; ?>';
        
        var editModal = new bootstrap.Modal(document.getElementById('editPaymentModal'));
        editModal.show();
    <?php endif; ?>
});

// Initialize DataTable
$(document).ready(function() {
    $('#paymentsTable').DataTable({
        "pageLength": 25,
        "order": [[2, 'desc']] // Order by payment date descending
    });
});
</script>

<?php require_once('const/check-reply.php'); ?>
</body>
</html>