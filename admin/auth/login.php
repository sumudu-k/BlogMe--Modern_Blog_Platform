<?php

session_start();
require_once '../../config/database.php';
require_once '../../includes/functions.php';

if (isset($_SESSION['admin_id'])) {
    header("Location: ../dashboard.php");
    exit;
}

$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $password = $_POST['password'];

    if (empty($username) || empty($password)) {
        $message = "All fields are required";
        $messageType = "danger";
    } else {
        try {
            // Get admin data
            $stmt = $pdo->prepare("SELECT id, username, email, password, first_name, last_name, role, is_active 
                                  FROM admins WHERE username = ? OR email = ?");
            $stmt->execute([$username, $username]);

            if ($stmt->rowCount() > 0) {
                $admin = $stmt->fetch();

                if ($admin['is_active'] == 0) {
                    $message = "Your account is pending approval. Please contact an administrator.";
                    $messageType = "warning";
                } else if (password_verify($password, $admin['password'])) {
                    // Login successful
                    $_SESSION['admin_id'] = $admin['id'];
                    $_SESSION['admin_username'] = $admin['username'];
                    $_SESSION['admin_email'] = $admin['email'];
                    $_SESSION['admin_name'] = $admin['first_name'] . ' ' . $admin['last_name'];
                    $_SESSION['admin_role'] = $admin['role'];

                    $updateStmt = $pdo->prepare("UPDATE admins SET updated_at = NOW() WHERE id = ?");
                    $updateStmt->execute([$admin['id']]);

                    header("Location: ../dashboard.php");
                    exit;
                } else {
                    $message = "Invalid username or password";
                    $messageType = "danger";
                }
            } else {
                $message = "Invalid username or password";
                $messageType = "danger";
            }
        } catch (PDOException $e) {
            error_log("Login error: " . $e->getMessage());
            $message = "An error occurred during login. Please try again.";
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
    <title>Admin Login</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="../../assets/css/admin-style.css">
    <script src="https://kit.fontawesome.com/a076d05399.js" crossorigin="anonymous"></script>
</head>

<body class="bg-light">
    <div class="container mt-5">
        <div class="row justify-content-center">

            <div class=" container alert alert-warning  ">
                <h5>Demo Admin Account Credentials </h5>
                <p>Email: admin@gmail.com</p>
                <p>Password: 12345678</p>
            </div>
            <div class="col-md-6">
                <div class="card shadow">
                    <div class="card-header bg-primary text-white">
                        <h4 class="mb-0">Admin Login</h4>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($message)): ?>
                        <div class="alert alert-<?php echo $messageType; ?>" role="alert">
                            <?php echo $message; ?>
                        </div>
                        <?php endif; ?>

                        <form action="<?php echo $_SERVER['PHP_SELF']; ?>" method="post">
                            <div class="form-group">
                                <label for="username">Username or Email</label>
                                <input type="text" class="form-control" id="username" name="username" required>
                            </div>

                            <div class="form-group">
                                <label for="password">Password</label>
                                <input type="password" class="form-control" id="password" name="password" required>
                            </div>

                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <div class="form-check">
                                    <input type="checkbox" class="form-check-input" id="remember" name="remember">
                                    <label class="form-check-label" for="remember">Remember me</label>
                                </div>
                                <div>
                                    <a href="reset-password.php">Forgot password?</a>
                                </div>
                            </div>

                            <button type="submit" class="btn btn-primary btn-block">Login</button>
                        </form>

                        <div class="mt-3 text-center">
                            <p>Don't have an account? <a href="register.php">Register</a></p>
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