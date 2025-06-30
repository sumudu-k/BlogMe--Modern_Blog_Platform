<?php
session_start();
require_once '../../config/database.php';
require_once '../../includes/functions.php';

if (isset($_SESSION['admin_id'])) {
    header("Location: index.php");
    exit;
}

$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $first_name = trim($_POST['first_name']);
    $last_name = trim($_POST['last_name']);

    if (empty($username) || empty($email) || empty($password) || empty($first_name) || empty($last_name)) {
        $message = "All fields are required";
        $messageType = "danger";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = "Please enter a valid email address";
        $messageType = "danger";
    } elseif (strlen($password) < 8) {
        $message = "Password must be at least 8 characters long";
        $messageType = "danger";
    } elseif ($password !== $confirm_password) {
        $message = "Passwords do not match";
        $messageType = "danger";
    } else {
        try {
            // Check if username or email already exists
            $stmt = $pdo->prepare("SELECT id FROM admin WHERE username = ? OR email = ?");
            $stmt->execute([$username, $email]);

            if ($stmt->rowCount() > 0) {
                $message = "Username or email already exists";
                $messageType = "danger";
            } else {
                $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

                $role = 'admin';
                $isActive = 0;

                // Insert new admin
                $stmt = $pdo->prepare("INSERT INTO admin (username, email, password, first_name, last_name, role, is_active, created_at, updated_at) 
                                       VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW())");

                $stmt->execute([$username, $email, $hashedPassword, $first_name, $last_name, $role, $isActive]);

                // Notify existing admins about new admin request
                try {
                    $notifyStmt = $pdo->prepare("SELECT email FROM admin WHERE is_active = 1 AND role IN ('admin', 'super_admin')");
                    $notifyStmt->execute();
                    $admins = $notifyStmt->fetchAll(PDO::FETCH_COLUMN);

                    error_log("New admin registration: $username ($email) requires approval");
                } catch (PDOException $e) {
                    error_log("Error notifying admins: " . $e->getMessage());
                }

                $message = "Registration successful. Your account is pending approval from an existing administrator.";
                $messageType = "success";
            }
        } catch (PDOException $e) {
            error_log("Registration error: " . $e->getMessage());
            $message = "An error occurred during registration. Please try again.";
            $messageType = "danger";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Registration</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="../../assets/css/admin-style.css">
    <script src="https://kit.fontawesome.com/a076d05399.js" crossorigin="anonymous"></script>
</head>

<body class="bg-light">
    <div class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-md-6">
                <div class="card shadow">
                    <div class="card-header bg-primary text-white">
                        <h4 class="mb-0">Admin Registration</h4>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($message)): ?>
                        <div class="alert alert-<?php echo $messageType; ?>" role="alert">
                            <?php echo $message; ?>
                        </div>
                        <?php endif; ?>

                        <form action="<?php echo $_SERVER['PHP_SELF']; ?>" method="post">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="first_name">First Name</label>
                                        <input type="text" class="form-control" id="first_name" name="first_name"
                                            required>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="last_name">Last Name</label>
                                        <input type="text" class="form-control" id="last_name" name="last_name"
                                            required>
                                    </div>
                                </div>
                            </div>

                            <div class="form-group">
                                <label for="username">Username</label>
                                <input type="text" class="form-control" id="username" name="username" required>
                            </div>

                            <div class="form-group">
                                <label for="email">Email</label>
                                <input type="email" class="form-control" id="email" name="email" required>
                            </div>

                            <div class="form-group">
                                <label for="password">Password</label>
                                <input type="password" class="form-control" id="password" name="password" required
                                    minlength="8">
                                <small class="form-text text-muted">Password must be at least 8 characters long</small>
                            </div>

                            <div class="form-group">
                                <label for="confirm_password">Confirm Password</label>
                                <input type="password" class="form-control" id="confirm_password"
                                    name="confirm_password" required minlength="8">
                            </div>

                            <button type="submit" class="btn btn-primary btn-block">Register</button>
                        </form>

                        <div class="mt-3 text-center">
                            <p>Already have an account? <a href="login.php">Login</a></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
</body>

</html>