<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Attendance Dashboard</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">

  <div class="container mt-4">
    <!-- Welcome -->
    <h2>Welcome, riojames</h2>

    <!-- Top Summary Cards -->
    <div class="row mt-4">
      <div class="col-md-4">
        <div class="card text-white bg-success mb-3 shadow">
          <div class="card-body d-flex align-items-center">
            <div class="me-3">
              <i class="bi bi-person-check fs-1"></i>
            </div>
            <div>
              <h3 class="mb-0">0</h3>
              <p class="mb-0">Present Today</p>
            </div>
          </div>
        </div>
      </div>
      
      <div class="col-md-4">
        <div class="card text-white bg-danger mb-3 shadow">
          <div class="card-body d-flex align-items-center">
            <div class="me-3">
              <i class="bi bi-person-x fs-1"></i>
            </div>
            <div>
              <h3 class="mb-0">17</h3>
              <p class="mb-0">Absent</p>
            </div>
          </div>
        </div>
      </div>
      
      <div class="col-md-4">
        <div class="card text-white bg-primary mb-3 shadow">
          <div class="card-body d-flex align-items-center">
            <div class="me-3">
              <i class="bi bi-people fs-1"></i>
            </div>
            <div>
              <h3 class="mb-0">17</h3>
              <p class="mb-0">Total Employees</p>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Attendance Table -->
    <div class="card shadow mt-4">
      <div class="card-body">
        <h5 class="card-title">Today's Attendance – September 24, 2025</h5>

        <!-- Navigation Tabs -->
        <ul class="nav nav-pills mb-3">
          <li class="nav-item">
            <a class="nav-link active" href="#">All</a>
          </li>
          <li class="nav-item">
            <a class="nav-link" href="#">Daily</a>
          </li>
          <li class="nav-item">
            <a class="nav-link" href="#">Events</a>
          </li>
          <li class="nav-item">
            <a class="nav-link" href="#">Disaster Response</a>
          </li>
        </ul>

        <!-- Attendance Table -->
        <div class="table-responsive">
          <table class="table table-striped">
            <thead class="table-light">
              <tr>
                <th>ID</th>
                <th>Employee ID</th>
                <th>Name</th>
                <th>Position</th>
                <th>Clock In</th>
                <th>Clock Out</th>
                <th>Type</th>
                <th>Details</th>
                <th>Status</th>
              </tr>
            </thead>
            <tbody>
              <tr>
                <td colspan="9" class="text-center">No attendance records for today</td>
              </tr>
            </tbody>
          </table>
        </div>

      </div>
    </div>

  </div>

  <!-- Bootstrap Icons -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">

</body>
</html>
