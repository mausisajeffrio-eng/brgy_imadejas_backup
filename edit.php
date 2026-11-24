<?php
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "brgy_db";

$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$id = "";
$employee_id = "";
$first_name = "";
$last_name = "";
$position = "";
$age = "";
$phone = "";
$status = "Active";
$errorMessage = "";
$successMessage = "";

if ($_SERVER["REQUEST_METHOD"] == "GET") {
    if (!isset($_GET["id"])) {
        header("location: /jowawbackup/admin_page.php#employees");
        exit;
    }
    
    $id = $_GET["id"];
    $sql = "SELECT * FROM emp_db WHERE id = $id"; 
    $result = $conn->query($sql);
    
    if (!$result || $result->num_rows == 0) {
        header("location: /jowawbackup/crud.php");
        exit;
    }
    
    $row = $result->fetch_assoc();
    $employee_id = $row["employee_id"];
    $first_name = $row["first_name"];
    $last_name = $row["last_name"];
    $position = $row["position"];
    $age = $row["age"];
    $phone = $row["phone"];
    $status = $row["status"];
    
} elseif ($_SERVER["REQUEST_METHOD"] == "POST") {
    $id = $_POST["id"];
    $employee_id = $_POST["employee_id"];
    $first_name = $_POST["first_name"];
    $last_name = $_POST["last_name"];
    $position = $_POST["position"];
    $age = $_POST["age"];
    $phone = $_POST["phone"];
    $status = isset($_POST["status"]) ? $_POST["status"] : "Active";

    if (empty($employee_id) || empty($first_name) || empty($last_name) || empty($position) || empty($age) || empty($phone)) {
        $errorMessage = "All fields are required";
    } else {
        // Escape variables for security
        $employee_id = $conn->real_escape_string($employee_id);
        $first_name = $conn->real_escape_string($first_name);
        $last_name = $conn->real_escape_string($last_name);
        $position = $conn->real_escape_string($position);
        $age = $conn->real_escape_string($age);
        $phone = $conn->real_escape_string($phone);
        $status = $conn->real_escape_string($status);
        
        $sql = "UPDATE emp_db SET 
                employee_id = '$employee_id', 
                first_name = '$first_name', 
                last_name = '$last_name', 
                position = '$position', 
                age = '$age', 
                phone = '$phone', 
                status = '$status' 
                WHERE id = $id";
                
        if ($conn->query($sql) === TRUE) {
            $conn->close();
            header("location: /jowawbackup/admin_page.php#employees");
            exit();
        } else {
            $errorMessage = "Error updating record: " . $conn->error;
        }
    }
}
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add New Employee</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
</head>
<body>
<div class="container my-5">
    <h2>Edit Employee</h2>
    
    <?php if(!empty($errorMessage)): ?>
        <div class='alert alert-warning alert-dismissible fade show' role='alert'>
            <strong><?php echo $errorMessage; ?></strong>
            <button type='button' class='btn-close' data-bs-dismiss='alert' aria-label='Close'></button>
        </div>
    <?php endif; ?>
    
    <?php if(!empty($successMessage)): ?>
        <div class='alert alert-success alert-dismissible fade show' role='alert'>
            <strong><?php echo $successMessage; ?></strong>
            <button type='button' class='btn-close' data-bs-dismiss='alert' aria-label='Close'></button>
        </div>
    <?php endif; ?>

    <form method="post" class="mt-4">
        <input type="hidden" name="id" value="<?php echo htmlspecialchars($id); ?>">
        <div class="row mb-3">
            <label class="col-sm-3 col-form-label">Employee ID</label>
            <div class="col-sm-6">
                <input type="text" class="form-control" name="employee_id" value="<?php echo htmlspecialchars($employee_id); ?>">
            </div>
        </div>
        <div class="row mb-3">
            <label class="col-sm-3 col-form-label">First Name</label> 
            <div class="col-sm-6">
                <input type="text" class="form-control" name="first_name" value="<?php echo htmlspecialchars($first_name); ?>">
            </div>
        </div>
        <div class="row mb-3">
            <label class="col-sm-3 col-form-label">Last Name</label>
            <div class="col-sm-6">
                <input type="text" class="form-control" name="last_name" value="<?php echo htmlspecialchars($last_name); ?>">
            </div>
        </div>
        <div class="row mb-3">
            <label class="col-sm-3 col-form-label">Position</label>
            <div class="col-sm-6">
                <input type="text" class="form-control" name="position" value="<?php echo htmlspecialchars($position); ?>">
            </div>
        </div>
        <div class="row mb-3">
            <label class="col-sm-3 col-form-label">Age</label>
            <div class="col-sm-6">
                <input type="number" class="form-control" name="age" value="<?php echo htmlspecialchars($age); ?>">
            </div>
        </div>
        <div class="row mb-3">
            <label class="col-sm-3 col-form-label">Phone</label>
            <div class="col-sm-6">
                <input type="text" class="form-control" name="phone" value="<?php echo htmlspecialchars($phone); ?>">
            </div>
        </div>

        <div class="row mb-3">
            <div class="offset-sm-3 col-sm-6">
                <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                    <a class="btn btn-secondary me-md-2" href="admin_page.php#employees">
                        <i class="bi bi-x-circle"></i> Cancel
                    </a>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-save"></i> Save Employee
                    </button>
                </div>
            </div>
        </div>
    </form>
</div>

</body>
</html> 