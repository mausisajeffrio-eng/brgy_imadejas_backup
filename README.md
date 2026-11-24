# QR Attendance System (Barangay Imadejas)

A web-based attendance tracking system that uses QR codes for efficient employee timekeeping and management. This system allows for daily attendance, event tracking, and disaster response checking, featuring a comprehensive admin dashboard and user portals.

## Features

### Admin Dashboard
*   **Dashboard Overview**: View real-time statistics for present/absent employees, total employee count, and today's attendance logs.
*   **Attendance Management**:
    *   **Daily Time Record**: Scan QR codes for regular daily in/out.
    *   **Event Attendance**: Create ad-hoc events and scan attendees.
    *   **Disaster Response**: Track personnel during emergency operations.
    *   **Camera Support**: Integrated webcam scanning using Instascan.
*   **Employee Management**:
    *   Add, edit, and delete employee records.
    *   Generate standardized QR code formats.
*   **Reports**: Generate and export attendance reports (CSV/Print) filtered by date range, type, and status.
*   **Profile Management**: Update admin credentials and profile picture.

### User/Employee Dashboard
*   **Personal Dashboard**: View own attendance statistics.
*   **Profile Management**: Update personal information and profile picture.

## Tech Stack
*   **Backend**: PHP
*   **Database**: MySQL
*   **Frontend**: HTML5, CSS3, JavaScript
*   **Libraries/Frameworks**:
    *   **Bootstrap 5** (UI Components)
    *   **jQuery** & **DataTables** (Interactive Tables)
    *   **Instascan** (QR Code Scanning)
    *   **Line Awesome** & **Bootstrap Icons** (Icons)

## Installation & Setup

1.  **Server Requirements**:
    *   XAMPP (or any PHP/MySQL web server environment).
    *   PHP 7.4 or higher.

2.  **Installation Steps**:
    *   Clone or extract the project folder to your web server's root directory (e.g., `C:\xampp\htdocs\jowawbackup`).

3.  **Database Setup**:
    *   Open phpMyAdmin (usually `http://localhost/phpmyadmin`).
    *   Create a new database named **`brgy_db`**.
    *   Import the provided SQL files into `brgy_db`:
        *   `users.sql` (User accounts table)
        *   `emp_db.sql` (Employee records table)
        *   `attendance_db.sql` (Attendance logs table)

4.  **Configuration**:
    *   Verify the database credentials in `config.php`:
        ```php
        $servername = 'localhost';
        $username = 'root';
        $password = '';
        $dbname = 'brgy_db';
        ```

5.  **Accessing the System**:
    *   Open your browser and navigate to `http://localhost/jowawbackup/`.

## Account Registration & Login

### How to Register
1.  On the login page, click the **"Register"** link at the bottom of the form.
2.  Fill in the required information:
    *   **First Name & Last Name**
    *   **Email Address**
    *   **Password**
    *   **Role**: Select **Admin** (for system managers) or **User** (for employees/viewers).
3.  Click the **Register** button. You will be redirected to the login page upon success.

### How to Log In
1.  Enter your registered **Email** and **Password**.
2.  Select your **Role** (Must match the role you registered with).
3.  Click **Login** to access your dashboard.

## Usage Guide

### Generating QR Codes
For the system to recognize employees, QR codes must follow this specific format:

`EMP-ID|FirstName|LastName|Position`

**Example:**
`EMP-001|John|Doe|Tanod`

*   **EMP-ID**: Must start with `EMP-` followed by numbers.
*   **Separator**: Use the pipe symbol `|`.

### Scanning Attendance
1.  Navigate to the **Attendance** tab in the Admin Dashboard.
2.  Select the type: **Daily**, **Event**, or **Disaster**.
3.  For Events/Disaster, enter the specific details first.
4.  Click **Start Camera** and show the employee's QR code to the camera.
