<?php
if (isset($_GET["id"]) && isset($_GET["confirm"]) && $_GET["confirm"] === "true") {
    $id = $_GET["id"];
    
    $servername = "localhost";  
    $username = "root";
    $password = "";
    $dbname = "brgy_db";

    $conn = new mysqli($servername, $username, $password, $dbname);
    
    // Check connection
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }
    
    // Prepare and bind
    $stmt = $conn->prepare("DELETE FROM emp_db WHERE id = ?");
    $stmt->bind_param("i", $id);
    
    if ($stmt->execute()) {
        $stmt->close();
        $conn->close();
        header("location: /jowawbackup/admin_page.php#employees");
        exit();
    } else {
        $error = "Error deleting record: " . $conn->error;
        $stmt->close();
        $conn->close();
        header("location: /jowaw/admin_page.php#employees");
        exit();
    }
} elseif (isset($_GET["id"])) {
    // Show confirmation page
    $id = $_GET["id"];
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Confirm Delete</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
    </head>
    <body>
        <div class="container mt-5">
            <div class="row justify-content-center">
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header bg-danger text-white">
                            <h4 class="mb-0">Confirm Deletion</h4>
                        </div>
                        <div class="card-body text-center">
                            <p class="lead">Are you sure you want to delete this record?</p>
                            <p class="text-muted">This action cannot be undone.</p>
                            
                            <div class="d-flex justify-content-center gap-3">
                <a href="/jowawbackup/delete.php?id=<?php echo htmlspecialchars($id); ?>&confirm=true" class="btn btn-danger">
                                    <i class="bi bi-trash"></i> Yes, Delete It
                                </a>
                <a href="/jowawbackup/admin_page.php#employees" class="btn btn-secondary">
                                    <i class="bi bi-x-circle"></i> Cancel
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    </body>
    </html>
    <?php
    exit();
} else {
    header("location: /jowawbackup/admin_page.php#employees");
    exit();
}
?>