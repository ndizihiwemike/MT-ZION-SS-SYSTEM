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
$students = [];

try {
    $conn = new PDO('mysql:host='.DBHost.';dbname='.DBName.';charset='.DBCharset.';collation='.DBCollation.';prefix='.DBPrefix.'', DBUser, DBPass);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Handle form submission for adding a new student
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_student'])) {
        // Input validation
        $fname = trim($_POST['fname'] ?? '');
        $mname = trim($_POST['mname'] ?? '');
        $lname = trim($_POST['lname'] ?? '');
        $gender = trim($_POST['gender'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $class_id = trim($_POST['class_id'] ?? '');
        $password = trim($_POST['password'] ?? '');
        $display_image = 'Blank'; // Default value as per your table structure

        // Validate required fields
        if (empty($fname) || empty($lname) || empty($gender) || empty($email) || empty($class_id) || empty($password)) {
            $error_message = "First name, last name, gender, email, class, and password are required.";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error_message = "Invalid email format.";
        } else {
            // Generate student ID (you can modify this logic as needed)
            $student_id = 'STU' . date('Y') . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
            
            // Check if email already exists
            $stmt = $conn->prepare("SELECT * FROM tbl_students WHERE email = ?");
            $stmt->execute([$email]);
            $result = $stmt->fetchAll();

            if (count($result) > 0) {
                $error_message = "Email already exists. Please use a different email.";
            } else {
                // Insert into database
                $stmt = $conn->prepare("INSERT INTO tbl_students (id, fname, mname, lname, gender, email, class, password, level, display_image, status) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, '3', ?, 'Active')");
                $stmt->execute([$student_id, $fname, $mname, $lname, $gender, $email, $class_id, password_hash($password, PASSWORD_DEFAULT), $display_image]);
                
                $_SESSION['reply'] = "010";
                header("location:admin/manage_students");
                exit();
            }
        }
    }

    // Fetch students from database
    $stmt = $conn->prepare("SELECT * FROM tbl_students ORDER BY fname, lname");
    $stmt->execute();
    $students = $stmt->fetchAll();

    // Fetch classes for dropdown
    $stmt = $conn->prepare("SELECT * FROM tbl_classes ORDER BY name");
    $stmt->execute();
    $classes = $stmt->fetchAll();

} catch(PDOException $e) {
    $error_message = "Database error: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<meta http-equiv="content-type" content="text/html;charset=utf-8" />
<head>
<title>MTZSS - Manage Students</title>
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
<li><a class="app-menu__item" href="admin/finances"><i class="app-menu__icon feather icon-user"></i><span class="app-menu__label">Finances</span></a></li>

<li class="treeview"><a class="app-menu__item" href="javascript:void(0);" data-toggle="treeview"><i class="app-menu__icon feather icon-users"></i><span class="app-menu__label">Students</span><i class="treeview-indicator bi bi-chevron-right"></i></a>
<ul class="treeview-menu">
<li><a class="treeview-item" href="admin/register_students"><i class="icon bi bi-circle-fill"></i> Register Students</a></li>
<li><a class="treeview-item" href="admin/import_students"><i class="icon bi bi-circle-fill"></i> Import Students</a></li>
<li><a class="treeview-item active" href="admin/manage_students"><i class="icon bi bi-circle-fill"></i> Manage Students</a></li>
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
<h1>Manage Students</h1>
</div>
<ul class="app-breadcrumb breadcrumb">
<li class="breadcrumb-item"><button class="btn btn-primary btn-sm" type="button" data-bs-toggle="modal" data-bs-target="#addStudentModal">Add Student</button></li>
</ul>
</div>

<?php if ($error_message): ?>
<div class="alert alert-danger alert-dismissible fade show" role="alert">
    <?= $error_message ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
</div>
<?php endif; ?>

<div class="row">
<div class="col-md-12">
<div class="tile">
<div class="tile-body">
<div class="table-responsive">
<table class="table table-hover table-bordered" id="studentsTable">
<thead>
<tr>
<th>Student ID</th>
<th>First Name</th>
<th>Middle Name</th>
<th>Last Name</th>
<th>Gender</th>
<th>Email</th>
<th>Class</th>
<th>Status</th>
<th width="120">Actions</th>
</tr>
</thead>
<tbody>

<?php
if (count($students) > 0) {
    foreach($students as $student) {
        $status_badge = ($student['status'] == 'Active') ? 
            '<span class="badge bg-success">Active</span>' : 
            '<span class="badge bg-danger">Inactive</span>';
?>
<tr>
<td><?php echo htmlspecialchars($student['student_id']); ?></td>
<td><?php echo htmlspecialchars($student['fname']); ?></td>
<td><?php echo htmlspecialchars($student['mname']); ?></td>
<td><?php echo htmlspecialchars($student['lname']); ?></td>
<td><?php echo htmlspecialchars($student['gender']); ?></td>
<td><?php echo htmlspecialchars($student['email']); ?></td>
<td><?php echo htmlspecialchars($student['class']); ?></td>
<td><?php echo $status_badge; ?></td>
<td>
    <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#editStudentModal" 
            onclick="editStudent('<?php echo $student['student_id']; ?>')">Edit</button>
    <button class="btn btn-danger btn-sm" onclick="deleteStudent('<?php echo $student['student_id']; ?>')">Delete</button>
</td>
</tr>
<?php
    }
} else {
    echo '<tr><td colspan="9" class="text-center">No students found</td></tr>';
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

<!-- Add Student Modal -->
<div class="modal fade" id="addStudentModal" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1" aria-labelledby="addStudentModalLabel" aria-hidden="true">
<div class="modal-dialog">
<div class="modal-content">
<div class="modal-header">
<h5 class="modal-title" id="addStudentModalLabel">Add New Student</h5>
<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
</div>
<form method="POST" action="">
<div class="modal-body">
<div class="mb-2">
<label class="form-label">First Name *</label>
<input type="text" class="form-control" name="fname" required>
</div>
<div class="mb-2">
<label class="form-label">Middle Name</label>
<input type="text" class="form-control" name="mname">
</div>
<div class="mb-2">
<label class="form-label">Last Name *</label>
<input type="text" class="form-control" name="lname" required>
</div>
<div class="mb-2">
<label class="form-label">Gender *</label>
<select class="form-control" name="gender" required>
<option value="">Select Gender</option>
<option value="Male">Male</option>
<option value="Female">Female</option>
</select>
</div>
<div class="mb-2">
<label class="form-label">Email Address *</label>
<input type="email" class="form-control" name="email" required>
</div>
<div class="mb-2">
<label class="form-label">Class *</label>
<select class="form-control" name="class_id" required>
<option value="">Select Class</option>
<?php foreach($classes as $class): ?>
<option value="<?php echo $class['class_id']; ?>"><?php echo htmlspecialchars($class['class_name']); ?></option>
<?php endforeach; ?>
</select>
</div>
<div class="mb-2">
<label class="form-label">Password *</label>
<input type="password" class="form-control" name="password" required>
</div>
</div>
<div class="modal-footer">
<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
<button type="submit" name="add_student" class="btn btn-primary">Add Student</button>
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
function editStudent(studentId) {
    // Implement edit functionality
    Swal.fire({
        title: 'Edit Student',
        text: 'Edit functionality for student ID: ' + studentId,
        icon: 'info'
    });
}

function deleteStudent(studentId) {
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
            window.location.href = 'admin/core/delete_student?id=' + studentId;
        }
    });
}

// Initialize DataTable if needed
$(document).ready(function() {
    $('#studentsTable').DataTable();
});
</script>

<?php require_once('const/check-reply.php'); ?>
</body>
</html>