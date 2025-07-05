<?php

session_start();
require_once '../../config/database.php';
require_once '../../includes/functions.php';

require '../../vendor/autoload.php';

$message = '';
$messageType = '';
$email = '';
$showResetForm = true;
$showNewPasswordForm = false;
$showCodeForm = false;
$token = '';
$resetCode = '';

// Check if token is in URL 
if (isset($_GET['token']) && !empty($_GET['token']) && isset($_GET['email'])) {
    $token = $_GET['token'];
    $email = $_GET['email'];

    // Validate token using AdminAuth class from functions.php
    $validation = $adminAuth->validateResetToken($token, $email);

    if ($validation['valid']) {
        $showResetForm = false;
        $showCodeForm = true;
    } else {
        $message = 'Invalid or expired reset link. Please request a new password reset.';
        $messageType = 'danger';
    }
}

// Handle form submission for requesting password reset
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['request_reset'])) {
    $email = trim($_POST['email']);

    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = 'Please enter a valid email address.';
        $messageType = 'danger';
    } else {
        $result = $adminAuth->requestPasswordReset($email);

        if ($result['success']) {
            if (isset($result['token'])) {
                // Send reset email
                $sent = $adminAuth->sendResetEmail($result['user']['email'], $result['user']['username'], $result['token']);
                if ($sent) {
                    $message = 'A password reset link and code have been sent to your admin email address.';
                    $messageType = 'success';
                    $showResetForm = false;
                    $token = $result['token'];
                    $showCodeForm = true;
                } else {
                    $message = 'Could not send reset email. Please try again later.';
                    $messageType = 'danger';
                }
            } else {
                $message = 'If your email exists in our database, you will receive a password reset link and code shortly.';
                $messageType = 'info';
                $showResetForm = false;
            }
        } else {
            $message = $result['message'];
            $messageType = 'danger';
        }
    }
}

// Handle form submission for code verification
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['verify_code'])) {
    $token = $_POST['token'];
    $email = $_POST['email'];
    $code = $_POST['reset_code'];

    error_log("Verifying admin code: '$code' for email: $email with token: $token");

    if (empty($code) || strlen($code) !== 6 || !is_numeric($code)) {
        $message = 'Please enter a valid 6-digit code.';
        $messageType = 'danger';
        $showCodeForm = true;
        $showResetForm = false;
        error_log("Invalid admin code format: length=" . strlen($code) . ", is_numeric=" . (is_numeric($code) ? 'yes' : 'no'));
    } else {
        $validation = $adminAuth->validateResetToken($token, $email, $code);
        error_log("Admin code validation result: " . json_encode($validation));

        if ($validation['valid']) {
            $showCodeForm = false;
            $showNewPasswordForm = true;
            $resetCode = $code;
            error_log("Admin code validated successfully, showing password form");
        } else {
            $message = 'Invalid or expired code. Please check the code and try again.';
            $messageType = 'danger';
            $showCodeForm = true;
            $showResetForm = false;
            error_log("Admin code validation failed");
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reset_password'])) {
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $token = $_POST['token'];
    $email = $_POST['email'];
    $code = isset($_POST['reset_code']) ? $_POST['reset_code'] : null;

    // Validate passwords
    if (empty($password) || strlen($password) < 8) {
        $message = 'Password must be at least 8 characters.';
        $messageType = 'danger';
        $showNewPasswordForm = true;
        $showResetForm = false;
    } elseif ($password !== $confirm_password) {
        $message = 'Passwords do not match.';
        $messageType = 'danger';
        $showNewPasswordForm = true;
        $showResetForm = false;
    } else {
        $result = $adminAuth->resetPassword($email, $token, $password, $code);

        if ($result['success']) {
            $message = 'Your admin password has been updated successfully. You can now login with your new password.';
            $messageType = 'success';
            $showResetForm = false;
            $showNewPasswordForm = false;
            $showCodeForm = false;
        } else {
            $message = $result['message'];
            $messageType = 'danger';
            $showNewPasswordForm = false;
            $showResetForm = true;
            $showCodeForm = false;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Admin Password</title>
    <link rel="stylesheet" href="../../bootstrap/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
    body {
        background-color: #f8f9fa;
    }

    .admin-reset-card {
        max-width: 500px;
        margin: 80px auto;
        border-radius: 10px;
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
        border: none;
    }

    .card-header {
        background-color: #343a40;
        color: white;
        border-radius: 10px 10px 0 0 !important;
        padding: 1.25rem;
    }

    .code-input {
        letter-spacing: 8px;
        font-size: 1.5rem;
        font-weight: bold;
        text-align: center;
    }

    .btn-primary {
        background-color: #343a40;
        border-color: #343a40;
    }

    .btn-primary:hover {
        background-color: #23272b;
        border-color: #23272b;
    }

    .toggle-password {
        cursor: pointer;
    }

    .admin-logo {
        height: 50px;
        margin-bottom: 15px;
    }

    .login-link {
        color: #343a40;
        text-decoration: none;
    }

    .login-link:hover {
        color: #0d6efd;
    }
    </style>
</head>

<body>
    <div class="container">
        <div class="card admin-reset-card">
            <div class="card-header text-center bg-primary text-white">
                <h4 class="mb-0">Reset Admin Password</h4>
            </div>
            <div class="card-body p-4">
                <?php if (!empty($message)): ?>
                <div class="alert alert-<?php echo $messageType; ?>" role="alert">
                    <?php echo $message; ?>
                </div>
                <?php endif; ?>

                <?php if ($showResetForm): ?>
                <p class="text-muted mb-4">Enter your admin email address and we'll send you a link and code to reset
                    your
                    password.</p>
                <form action="<?php echo $_SERVER['PHP_SELF']; ?>" method="post">
                    <div class="form-group mb-4">
                        <label for="email" class="form-label">Admin Email Address</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-envelope"></i></span>
                            <input type="email" class="form-control" id="email" name="email" required
                                value="<?php echo htmlspecialchars($email); ?>" placeholder="Enter your admin email">
                        </div>
                    </div>
                    <div class="d-grid gap-2 ">
                        <?php
                            if ($_ENV['ALLOW_PASSWORD_RESET'] === 'true'): ?>
                        <button type="submit" name="request_reset" class="btn  bg-primary text-white">
                            <i class="fas fa-paper-plane me-2  "></i>Send Reset Link
                        </button>
                        <?php else: ?>
                        <button type="button" class="btn  bg-secondary text-white">
                            <i class="fas fa-paper-plane me-2  "></i>Send Reset Link
                        </button>
                        <div class=" mt-3 container  alert alert-danger" role='alert'>"You cannot reset password in Live
                            hosted website. Please
                            setup your own local environment to access full features. Visit
                            [https://github.com/sumudu-k/BlogMe] for more
                            details."</div>
                        <?php endif ?>
                    </div>
                </form>
                <div class="mt-4 text-center">
                    <p class="mb-0">Remember your password? <a href="./login.php" class="login-link">Login to Admin</a>
                    </p>
                </div>
                <?php endif; ?>

                <?php if ($showCodeForm): ?>
                <p class="text-muted mb-4">Enter the 6-digit code sent to your admin email address.</p>
                <form action="<?php echo $_SERVER['PHP_SELF']; ?>" method="post">
                    <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">
                    <input type="hidden" name="email" value="<?php echo htmlspecialchars($email); ?>">

                    <div class="form-group mb-4">
                        <label for="reset_code" class="form-label">Verification Code</label>
                        <input type="text" class="form-control code-input" id="reset_code" name="reset_code" required
                            minlength="6" maxlength="6" pattern="[0-9]+" placeholder="•••••••" autocomplete="off">
                        <small class="form-text text-muted">Enter the 6-digit code we sent to your admin email.</small>
                    </div>
                    <div class="d-grid gap-2">
                        <button type="submit" name="verify_code" class="btn btn-primary">
                            <i class="fas fa-check-circle me-2"></i>Verify Code
                        </button>
                    </div>
                </form>
                <div class="mt-4 text-center">
                    <p class="mb-0">Didn't receive the code? <a href="reset-password.php" class="login-link">Request
                            again</a>
                    </p>
                </div>
                <?php endif; ?>

                <?php if ($showNewPasswordForm): ?>
                <p class="text-muted mb-4">Create a new secure password for your admin account.</p>
                <form action="<?php echo $_SERVER['PHP_SELF']; ?>" method="post">
                    <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">
                    <input type="hidden" name="email" value="<?php echo htmlspecialchars($email); ?>">
                    <input type="hidden" name="reset_code" value="<?php echo htmlspecialchars($resetCode); ?>">

                    <div class="form-group mb-4">
                        <label for="password" class="form-label">New Password</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-lock"></i></span>
                            <input type="password" class="form-control" id="password" name="password" required
                                minlength="8">
                            <span class="input-group-text toggle-password"
                                onclick="togglePasswordVisibility('password')">
                                <i class="fas fa-eye" id="password-icon"></i>
                            </span>
                        </div>
                        <small class="form-text text-muted">Password must be at least 8 characters long.</small>
                    </div>
                    <div class="form-group mb-4">
                        <label for="confirm_password" class="form-label">Confirm Password</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-lock"></i></span>
                            <input type="password" class="form-control" id="confirm_password" name="confirm_password"
                                required minlength="8">
                            <span class="input-group-text toggle-password"
                                onclick="togglePasswordVisibility('confirm_password')">
                                <i class="fas fa-eye" id="confirm_password-icon"></i>
                            </span>
                        </div>
                    </div>
                    <div class="d-grid gap-2">
                        <button type="submit" name="reset_password" class="btn btn-primary">
                            <i class="fas fa-key me-2"></i>Reset Password
                        </button>
                    </div>
                </form>
                <?php endif; ?>

                <?php if (!$showResetForm && !$showNewPasswordForm && !$showCodeForm && $messageType === 'success'): ?>
                <div class="text-center mt-3">
                    <i class="fas fa-check-circle text-success" style="font-size: 4rem;"></i>
                    <p class="mt-3 mb-4">Your admin password has been updated successfully.</p>
                    <a href="./login.php" class="btn btn-primary">
                        <i class="fas fa-sign-in-alt me-2"></i>Go to Admin Login
                    </a>
                </div>
                <?php endif; ?>

                <?php if (!$showResetForm && !$showCodeForm && !$showNewPasswordForm && $messageType !== 'success'): ?>
                <div class="text-center mt-3">
                    <i class="fas fa-exclamation-circle text-danger" style="font-size: 4rem;"></i>
                    <p class="mt-3 mb-4">There was a problem with your reset request.</p>
                    <a href="reset-password.php" class="btn btn-primary">
                        <i class="fas fa-redo me-2"></i>Try Again
                    </a>
                </div>
                <?php endif; ?>
            </div>
            <div class="card-footer bg-light text-center py-3">
                <a href="../../index.php" class="text-muted text-decoration-none">
                    <i class="fas fa-arrow-left me-1"></i> Back to Website
                </a>
            </div>
        </div>
    </div>

    <script src="../../bootstrap/js/bootstrap.bundle.min.js"></script>
    <script>
    // Function to toggle password visibility
    function togglePasswordVisibility(fieldId) {
        const passwordField = document.getElementById(fieldId);
        const passwordIcon = document.getElementById(fieldId + '-icon');

        if (passwordField.type === 'password') {
            passwordField.type = 'text';
            passwordIcon.classList.remove('fa-eye');
            passwordIcon.classList.add('fa-eye-slash');
        } else {
            passwordField.type = 'password';
            passwordIcon.classList.remove('fa-eye-slash');
            passwordIcon.classList.add('fa-eye');
        }
    }

    // Format code input with spaces
    document.addEventListener('DOMContentLoaded', function() {
        const codeInput = document.getElementById('reset_code');
        if (codeInput) {
            codeInput.addEventListener('input', function(e) {
                this.value = this.value.replace(/[^\d]/g, '');

                if (this.value.length > 6) {
                    this.value = this.value.slice(0, 6);
                }
            });
        }
    });
    </script>
</body>

</html>