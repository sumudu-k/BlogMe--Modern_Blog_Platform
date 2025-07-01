<?php
session_start();
ob_start();
if (isset($_SESSION['user_id'])) {
    header("Location: ../user/profile.php");
    exit;
}

require_once '../config/database.php';
require_once '../includes/functions.php';

$email = $password = '';
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (empty($_POST['email'])) {
        $errors['email'] = 'Email is required';
    } else {
        $email = filter_var($_POST['email'], FILTER_SANITIZE_EMAIL);
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Valid email is required';
        }
    }
    if (empty($_POST['password'])) {
        $errors['password'] = 'Password is required';
    } else {
        $password = $_POST['password'];
    }

    if (empty($errors)) {
        $result = $userAuth->login($email, $password);

        if ($result['success']) {
            try {
                $stmt = $pdo->prepare("SELECT id, username, email, first_name, last_name FROM users WHERE email = ? LIMIT 1");
                $stmt->execute([$email]);
                $user = $stmt->fetch();

                $_SESSION['user_fullname'] = $user['first_name'] . ' ' . $user['last_name'];
                $_SESSION['last_activity'] = time();

                $token = bin2hex(random_bytes(32));
                $expires = date('Y-m-d H:i:s', strtotime('+30 days'));
                $ip = $_SERVER['REMOTE_ADDR'];
                $userAgent = $_SERVER['HTTP_USER_AGENT'];

                $sessionStmt = $pdo->prepare("INSERT INTO user_sessions (user_id, session_token, user_type, ip_address, user_agent, expires_at) 
                                          VALUES (:user_id, :token, 'user', :ip, :agent, :expires)");
                $sessionStmt->bindParam(':user_id', $user['id'], PDO::PARAM_INT);
                $sessionStmt->bindParam(':token', $token, PDO::PARAM_STR);
                $sessionStmt->bindParam(':ip', $ip, PDO::PARAM_STR);
                $sessionStmt->bindParam(':agent', $userAgent, PDO::PARAM_STR);
                $sessionStmt->bindParam(':expires', $expires, PDO::PARAM_STR);
                $sessionStmt->execute();

                if (isset($_POST['remember_me'])) {
                    setcookie('remember_token', $token, strtotime('+30 days'), '/', '', false, true);
                }

                header("Location: ../user/profile.php");
                exit;
            } catch (PDOException $e) {
                error_log("Login error: " . $e->getMessage());
                $errors['login'] = 'An error occurred during login. Please try again.';
            }
        } else {
            $errors['login'] = $result['message'];
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login | Blog Website</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
</head>

<body>
    <?php
    include_once '../includes/header.php';
    ?>

    <div class="mt-3 container alert alert-warning  ">
        <h5>Demo Account Credentials </h5>
        <p>Email: sumudu20010521@gmail.com</p>
        <p>Password: 12345678</p>
    </div>
    <div class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h4 class="text-center">Login to Your Account</h4>
                    </div>
                    <div class="card-body">
                        <?php if (isset($errors['login'])): ?>
                        <div class="alert alert-danger"><?php echo $errors['login']; ?></div>
                        <?php endif; ?>

                        <form method="POST" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>">
                            <div class="form-group">
                                <label for="email">Email Address</label>
                                <input type="email"
                                    class="form-control <?php echo isset($errors['email']) ? 'is-invalid' : ''; ?>"
                                    id="email" name="email" value="<?php echo htmlspecialchars($email); ?>" required>
                                <?php if (isset($errors['email'])): ?>
                                <div class="invalid-feedback"><?php echo $errors['email']; ?></div>
                                <?php endif; ?>
                            </div>

                            <div class="form-group">
                                <label for="password">Password</label>
                                <input type="password"
                                    class="form-control <?php echo isset($errors['password']) ? 'is-invalid' : ''; ?>"
                                    id="password" name="password" required>
                                <?php if (isset($errors['password'])): ?>
                                <div class="invalid-feedback"><?php echo $errors['password']; ?></div>
                                <?php endif; ?>
                            </div>

                            <div class="form-group form-check">
                                <input type="checkbox" class="form-check-input" id="remember_me" name="remember_me">
                                <label class="form-check-label" for="remember_me">Remember me</label>
                            </div>

                            <button type="submit" class="btn btn-primary btn-block">Login</button>
                        </form>

                        <div class="mt-3 text-center">
                            <p>Forgot your password? <a href="reset-password.php">Reset it here</a></p>
                            <p>Don't have an account? <a href="register.php">Sign up</a></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php include_once '../includes/footer.php'; ?>

    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    <script src="../assets/js/main.js"></script>
</body>

</html>