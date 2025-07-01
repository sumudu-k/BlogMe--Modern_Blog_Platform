<?php
require_once '../config/database.php';
require_once '../includes/functions.php';
ob_start();
$pageTitle = 'Create an Account';
$pageDescription = 'Join our blogging community';

$username = '';
$email = '';
$firstName = '';
$lastName = '';
$errors = [];

include_once '../includes/header.php';


$allowReset = $_ENV['ALLOW_REGISTER'];
if ($allowReset === 'false'): ?>
<div class=" mt-3 container  alert alert-danger" role='alert'>"You cannot create new accounts in Live hosted website.
    Please
    setup your own local environment to access full features. Visit [https://github.com/sumudu-k/BlogMe] for more
    details."</div>
<?php else: ?>
<?php
    // Process form submission
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $username = trim($_POST['username']);
        $email = trim($_POST['email']);
        $firstName = trim($_POST['first_name']);
        $lastName = trim($_POST['last_name']);
        $password = $_POST['password'];
        $confirmPassword = $_POST['confirm_password'];

        if (empty($username)) {
            $errors['username'] = 'Username is required';
        } elseif (!preg_match('/^[a-zA-Z0-9_]{3,20}$/', $username)) {
            $errors['username'] = 'Username must be between 3-20 characters and can only contain letters, numbers and underscores';
        }

        if (empty($email)) {
            $errors['email'] = 'Email is required';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Please enter a valid email address';
        }

        if (empty($firstName)) {
            $errors['first_name'] = 'First name is required';
        }

        if (empty($lastName)) {
            $errors['last_name'] = 'Last name is required';
        }

        if (empty($password)) {
            $errors['password'] = 'Password is required';
        } elseif (strlen($password) < 8) {
            $errors['password'] = 'Password must be at least 8 characters';
        }

        if ($password !== $confirmPassword) {
            $errors['confirm_password'] = 'Passwords do not match';
        }

        if (empty($errors)) {
            $result = $userAuth->register($username, $email, $password, $firstName, $lastName);

            if ($result['success']) {
                $_SESSION['registration_success'] = true;
                $_SESSION['registered_username'] = $username;

                try {
                    $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? LIMIT 1");
                    $stmt->execute([$username]);
                    $user = $stmt->fetch();

                    if ($user) {
                        $_SESSION['user_id'] = $user['id'];
                        $_SESSION['username'] = $username;
                        $_SESSION['user_type'] = 'user';
                        $_SESSION['user_fullname'] = $firstName . ' ' . $lastName;
                        $_SESSION['email'] = $email;
                        $_SESSION['last_activity'] = time();

                        header('Location: ../index.php?welcome=1');
                        exit;
                    } else {
                        $errors['db'] = 'Error retrieving user information. Please try logging in.';
                    }
                } catch (PDOException $e) {
                    error_log($e->getMessage());
                    $errors['db'] = 'Database error: ' . $e->getMessage();
                }
            } else {
                if (strpos($result['message'], 'already exists') !== false) {
                    try {
                        $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
                        $stmt->execute([$username]);
                        if ($stmt->fetch()) {
                            $errors['username'] = 'This username is already taken';
                        } else {
                            $errors['email'] = 'This email is already registered';
                        }
                    } catch (PDOException $e) {
                        error_log($e->getMessage());
                        $errors['db'] = 'Database error: ' . $e->getMessage();
                    }
                } else {
                    $errors['db'] = $result['message'];
                }
            }
        }
    }

endif ?>


<section class="register-hero py-5 bg-primary text-white">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-lg-6">
                <h1 class="display-4 fw-bold mb-4">Join Our Community</h1>
                <p class="lead mb-4">Create an account to start sharing your stories and connect with other bloggers.
                </p>
                <div class="d-flex gap-3">
                    <a href="../auth/login.php" class="btn btn-outline-light btn-lg">
                        <i class="fas fa-sign-in-alt me-2"></i>Already have an account?
                    </a>
                </div>
            </div>
            <div class="col-lg-6 text-center">
                <i class="fas fa-user-plus display-1 opacity-50"></i>
            </div>
        </div>
    </div>
</section>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm">
                <div class="card-body p-4 p-md-5">
                    <h2 class="text-center mb-4">Create Your Account</h2>

                    <?php if (!empty($errors['db'])): ?>
                    <div class="alert alert-danger">
                        <?php echo $errors['db']; ?>
                    </div>
                    <?php endif; ?>

                    <form method="POST" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>"
                        class="needs-validation" novalidate>
                        <div class="row g-3">
                            <!-- Username -->
                            <div class="col-12 mb-3">
                                <label for="username" class="form-label">Username <span
                                        class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-user"></i></span>
                                    <input type="text"
                                        class="form-control <?php echo isset($errors['username']) ? 'is-invalid' : ''; ?>"
                                        id="username" name="username" value="<?php echo htmlspecialchars($username); ?>"
                                        required>
                                    <?php if (isset($errors['username'])): ?>
                                    <div class="invalid-feedback">
                                        <?php echo $errors['username']; ?>
                                    </div>
                                    <?php endif; ?>
                                </div>
                                <small class="text-muted">Choose a unique username between 3-20 characters (letters,
                                    numbers, and underscores only).</small>
                            </div>

                            <!-- Email -->
                            <div class="col-12 mb-3">
                                <label for="email" class="form-label">Email Address <span
                                        class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-envelope"></i></span>
                                    <input type="email"
                                        class="form-control <?php echo isset($errors['email']) ? 'is-invalid' : ''; ?>"
                                        id="email" name="email" value="<?php echo htmlspecialchars($email); ?>"
                                        required>
                                    <?php if (isset($errors['email'])): ?>
                                    <div class="invalid-feedback">
                                        <?php echo $errors['email']; ?>
                                    </div>
                                    <?php endif; ?>
                                </div>
                                <small class="text-muted">We'll never share your email with anyone else.</small>
                            </div>

                            <!-- First Name and Last Name -->
                            <div class="col-md-6 mb-3">
                                <label for="first_name" class="form-label">First Name <span
                                        class="text-danger">*</span></label>
                                <input type="text"
                                    class="form-control <?php echo isset($errors['first_name']) ? 'is-invalid' : ''; ?>"
                                    id="first_name" name="first_name"
                                    value="<?php echo htmlspecialchars($firstName); ?>" required>
                                <?php if (isset($errors['first_name'])): ?>
                                <div class="invalid-feedback">
                                    <?php echo $errors['first_name']; ?>
                                </div>
                                <?php endif; ?>
                            </div>

                            <div class="col-md-6 mb-3">
                                <label for="last_name" class="form-label">Last Name <span
                                        class="text-danger">*</span></label>
                                <input type="text"
                                    class="form-control <?php echo isset($errors['last_name']) ? 'is-invalid' : ''; ?>"
                                    id="last_name" name="last_name" value="<?php echo htmlspecialchars($lastName); ?>"
                                    required>
                                <?php if (isset($errors['last_name'])): ?>
                                <div class="invalid-feedback">
                                    <?php echo $errors['last_name']; ?>
                                </div>
                                <?php endif; ?>
                            </div>

                            <!-- Password and Confirm Password -->
                            <div class="col-md-6 mb-3">
                                <label for="password" class="form-label">Password <span
                                        class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-lock"></i></span>
                                    <input type="password"
                                        class="form-control <?php echo isset($errors['password']) ? 'is-invalid' : ''; ?>"
                                        id="password" name="password" required>
                                    <button class="btn btn-outline-secondary toggle-password" type="button"
                                        tabindex="-1">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                    <?php if (isset($errors['password'])): ?>
                                    <div class="invalid-feedback">
                                        <?php echo $errors['password']; ?>
                                    </div>
                                    <?php endif; ?>
                                </div>
                                <small class="text-muted">Minimum 8 characters.</small>
                            </div>

                            <div class="col-md-6 mb-3">
                                <label for="confirm_password" class="form-label">Confirm Password <span
                                        class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-lock"></i></span>
                                    <input type="password"
                                        class="form-control <?php echo isset($errors['confirm_password']) ? 'is-invalid' : ''; ?>"
                                        id="confirm_password" name="confirm_password" required>
                                    <button class="btn btn-outline-secondary toggle-password" type="button"
                                        tabindex="-1">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                    <?php if (isset($errors['confirm_password'])): ?>
                                    <div class="invalid-feedback">
                                        <?php echo $errors['confirm_password']; ?>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <!-- Terms and Conditions -->
                            <div class="col-12 mb-3">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="terms" name="terms" required>
                                    <label class="form-check-label" for="terms">
                                        I agree to the <a href="#">Terms of Service</a> and
                                        <a href="#">Privacy Policy</a>
                                    </label>
                                    <div class="invalid-feedback">
                                        You must agree before submitting.
                                    </div>
                                </div>
                            </div>

                            <!-- Submit Button -->
                            <div class="col-12">
                                <button type="submit" class="btn btn-primary btn-lg w-100">
                                    <i class="fas fa-user-plus me-2"></i>Create Account
                                </button>
                            </div>
                        </div>
                    </form>

                    <div class="mt-4 text-center">
                        <p>Already have an account? <a href="../auth/login.php" class="text-decoration-none">Log in
                                here</a></p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Toggle password visibility
    const toggleButtons = document.querySelectorAll('.toggle-password');
    toggleButtons.forEach(button => {
        button.addEventListener('click', function() {
            const input = this.previousElementSibling;
            const icon = this.querySelector('i');

            if (input.type === 'password') {
                input.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                input.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        });
    });

    //  form validation
    const forms = document.querySelectorAll('.needs-validation');
    Array.from(forms).forEach(form => {
        form.addEventListener('submit', event => {
            if (!form.checkValidity()) {
                event.preventDefault();
                event.stopPropagation();
            }
            form.classList.add('was-validated');
        }, false);
    });
});
</script>

<style>
.register-hero {
    background: linear-gradient(135deg, var(--bs-primary) 0%, #0056b3 100%);
}

.btn-primary {
    transition: all 0.3s ease;
}

.btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
}

.card {
    border-radius: 15px;
    overflow: hidden;
}

.form-control:focus {
    box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.25);
    border-color: #86b7fe;
}

.toggle-password {
    z-index: 10;
}
</style>

<?php include '../includes/footer.php'; ?>