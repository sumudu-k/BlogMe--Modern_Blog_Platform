<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

require '../vendor/autoload.php';

$message = '';
$messageType = '';
$email = '';
$showResetForm = true;
$showNewPasswordForm = false;
$showCodeForm = false;
$token = '';
$resetCode = '';

if (isset($_GET['token']) && !empty($_GET['token']) && isset($_GET['email'])) {
    $token = $_GET['token'];
    $email = $_GET['email'];

    $validation = $userAuth->validateResetToken($token, $email);

    if ($validation['valid']) {
        $showResetForm = false;
        $showCodeForm = true;
    } else {
        $message = 'Invalid or expired reset link. Please request a new password reset.';
        $messageType = 'danger';
    }
}


if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['request_reset'])) {
    $email = trim($_POST['email']);

    // Validate email
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = 'Please enter a valid email address.';
        $messageType = 'danger';
    } else {
        $result = $userAuth->requestPasswordReset($email);

        if ($result['success']) {
            if (isset($result['token'])) {
                $sent = $userAuth->sendResetEmail($result['user']['email'], $result['user']['username'], $result['token']);
                if ($sent) {
                    $message = 'A password reset link and code have been sent to your email address.';
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
    error_log("Verifying code: '$code' for email: $email with token: $token");

    // Validate code
    if (empty($code) || strlen($code) !== 6 || !is_numeric($code)) {
        $message = 'Please enter a valid 6-digit code.';
        $messageType = 'danger';
        $showCodeForm = true;
        $showResetForm = false;
        error_log("Invalid code format: length=" . strlen($code) . ", is_numeric=" . (is_numeric($code) ? 'yes' : 'no'));
    } else {
        // Validate token and code
        $validation = $userAuth->validateResetToken($token, $email, $code);
        error_log("Code validation result: " . json_encode($validation));

        if ($validation['valid']) {
            $showCodeForm = false;
            $showNewPasswordForm = true;
            $resetCode = $code;
            error_log("Code validated successfully, showing password form");
        } else {
            $message = 'Invalid or expired code. Please check the code and try again.';
            $messageType = 'danger';
            $showCodeForm = true;
            $showResetForm = false;
            error_log("Code validation failed");
        }
    }
}

// Handle form submission for setting new password
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
        $result = $userAuth->resetPassword($email, $token, $password, $code);

        if ($result['success']) {
            $message = 'Your password has been updated successfully. You can now login with your new password.';
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
    <title>Reset Password - Blog Website</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
</head>

<body>
    <?php include_once '../includes/header.php'; ?>

    <div class="container my-5">
        <div class="row justify-content-center">
            <div class="col-md-6">
                <div class="card shadow">
                    <div class="card-header bg-primary text-white">
                        <h4 class="mb-0">Reset Password</h4>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($message)): ?>
                        <div class="alert alert-<?php echo $messageType; ?>" role="alert">
                            <?php echo $message; ?>
                        </div>
                        <?php endif; ?>

                        <?php if ($showResetForm): ?>
                        <p>Enter your email address and we'll send you a link and code to reset your password.</p>
                        <form action="<?php echo $_SERVER['PHP_SELF']; ?>" method="post">
                            <div class="form-group">
                                <label for="email">Email address</label>
                                <input type="email" class="form-control" id="email" name="email" required
                                    value="<?php echo htmlspecialchars($email); ?>">
                            </div>
                            <button type="submit" name="request_reset" class="btn btn-primary btn-block">Send Reset
                                Link</button>
                        </form>
                        <div class="mt-3 text-center">
                            <p>Remember your password? <a href="login.php">Login</a></p>
                        </div>
                        <?php endif; ?>

                        <?php if ($showCodeForm): ?>
                        <p>Enter the 6-digit code sent to your email.</p>
                        <form action="<?php echo $_SERVER['PHP_SELF']; ?>" method="post">
                            <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">
                            <input type="hidden" name="email" value="<?php echo htmlspecialchars($email); ?>">

                            <div class="form-group">
                                <label for="reset_code">Verification Code</label>
                                <input type="text" class="form-control" id="reset_code" name="reset_code" required
                                    minlength="6" maxlength="6" pattern="[0-9]+" placeholder="Enter 6-digit code">
                                <small class="form-text text-muted">Enter the 6-digit code we sent to your
                                    email.</small>
                            </div>
                            <button type="submit" name="verify_code" class="btn btn-primary btn-block">Verify
                                Code</button>
                        </form>
                        <div class="mt-3 text-center">
                            <p>Didn't receive the code? <a href="reset-password.php">Request again</a></p>
                        </div>
                        <?php endif; ?>

                        <?php if ($showNewPasswordForm): ?>
                        <p>Enter your new password below.</p>
                        <form action="<?php echo $_SERVER['PHP_SELF']; ?>" method="post">
                            <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">
                            <input type="hidden" name="email" value="<?php echo htmlspecialchars($email); ?>">
                            <input type="hidden" name="reset_code" value="<?php echo htmlspecialchars($resetCode); ?>">

                            <div class="form-group">
                                <label for="password">New Password</label>
                                <input type="password" class="form-control" id="password" name="password" required
                                    minlength="8">
                                <small class="form-text text-muted">Password must be at least 8 characters long.</small>
                            </div>
                            <div class="form-group">
                                <label for="confirm_password">Confirm Password</label>
                                <input type="password" class="form-control" id="confirm_password"
                                    name="confirm_password" required minlength="8">
                            </div>
                            <button type="submit" name="reset_password" class="btn btn-success btn-block">Reset
                                Password</button>
                        </form>
                        <?php endif; ?>

                        <?php if (!$showResetForm && !$showNewPasswordForm && !$showCodeForm && $messageType === 'success'): ?>
                        <div class="text-center mt-3">
                            <a href="login.php" class="btn btn-primary">Go to Login</a>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php include_once '../includes/footer.php'; ?>
    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
</body>

</html>