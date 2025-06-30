<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/config.php';
require_once dirname(__DIR__) . '/vendor/autoload.php';

// User authentication functions
class UserAuth
{
    private $pdo;

    public function __construct($pdo)
    {
        $this->pdo = $pdo;
    }

    public function register($username, $email, $password, $firstName, $lastName, $userType = 'user')
    {
        try {
            $table = ($userType === 'admin') ? 'admins' : 'users';

            // Check if username or email already exists
            $stmt = $this->pdo->prepare("SELECT id FROM $table WHERE username = ? OR email = ?");
            $stmt->execute([$username, $email]);

            if ($stmt->rowCount() > 0) {
                return ['success' => false, 'message' => 'Username or email already exists'];
            }

            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

            $stmt = $this->pdo->prepare("
                INSERT INTO $table (username, email, password, first_name, last_name) 
                VALUES (?, ?, ?, ?, ?)
            ");

            $stmt->execute([$username, $email, $hashedPassword, $firstName, $lastName]);

            return ['success' => true, 'message' => 'Registration successful'];
        } catch (PDOException $e) {
            error_log($e->getMessage());
            return ['success' => false, 'message' => 'Registration failed'];
        }
    }

    public function login($email, $password)
    {
        try {
            $stmt = $this->pdo->prepare("SELECT * FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password'])) {
                if ($user['is_blocked']) {
                    return ['success' => false, 'message' => 'Your account has been blocked'];
                }

                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['email'] = $user['email'];

                return ['success' => true, 'message' => 'Login successful'];
            }

            return ['success' => false, 'message' => 'Invalid credentials'];
        } catch (PDOException $e) {
            error_log($e->getMessage());
            return ['success' => false, 'message' => 'Login failed'];
        }
    }

    public function logout($userType = 'user')
    {
        $sessionKey = ($userType === 'admin') ? 'admin_id' : 'user_id';
        unset($_SESSION[$sessionKey]);
        unset($_SESSION[$userType . '_username']);
        unset($_SESSION[$userType . '_email']);
        session_destroy();
    }

    public function requestPasswordReset($email)
    {
        try {
            // Check if user exists
            $stmt = $this->pdo->prepare("SELECT id, email, username FROM users WHERE email = ?");
            $stmt->execute([$email]);

            if ($stmt->rowCount() === 1) {
                $user = $stmt->fetch();

                // Generate token and reset code
                $token = bin2hex(random_bytes(32));
                $resetCode = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
                // Extend expiration to 24 hours instead of 1
                $expires = date('Y-m-d H:i:s', strtotime('+24 hours'));

                error_log("Generated new token and code. Token: $token, Code: $resetCode, Expires: $expires");

                // Save token and code to database
                $updateStmt = $this->pdo->prepare("UPDATE users SET reset_token = ?, reset_token_expires = ?, reset_code = ? WHERE email = ?");
                $updateStmt->execute([$token, $expires, $resetCode, $email]);

                if ($updateStmt->rowCount() > 0) {
                    return [
                        'success' => true,
                        'message' => 'Reset token generated',
                        'user' => $user,
                        'token' => $token
                    ];
                }
            }

            return ['success' => true, 'message' => 'If your email exists, you will receive reset instructions'];
        } catch (PDOException $e) {
            error_log($e->getMessage());
            return ['success' => false, 'message' => 'An error occurred processing your request'];
        }
    }


    public function validateResetToken($token, $email, $code = null)
    {
        try {
            error_log("Validating token: " . $token . " for email: " . $email . " with code: " . ($code ?? 'null'));

            if ($code !== null) {
                $checkStmt = $this->pdo->prepare("SELECT reset_code FROM users WHERE email = ?");
                $checkStmt->execute([$email]);
                $storedCode = $checkStmt->fetchColumn();
                error_log("Code comparison - Input: '$code', Stored: '$storedCode'");

                $stmt = $this->pdo->prepare("SELECT id FROM users WHERE email = ? AND reset_token = ? AND reset_code = ? AND reset_token_expires > NOW()");
                $stmt->execute([$email, $token, $code]);

                error_log("Code validation query returned " . $stmt->rowCount() . " rows");
            } else {
                $stmt = $this->pdo->prepare("SELECT id FROM users WHERE email = ? AND reset_token = ? AND reset_token_expires > NOW()");
                $stmt->execute([$email, $token]);

                error_log("Token validation query returned " . $stmt->rowCount() . " rows");
            }

            $valid = $stmt->rowCount() > 0;
            return ['success' => true, 'valid' => $valid];
        } catch (PDOException $e) {
            error_log("Database error in validateResetToken: " . $e->getMessage());
            return ['success' => false, 'valid' => false, 'message' => 'An error occurred validating your token'];
        }
    }

    public function resetPassword($email, $token, $password, $code = null)
    {
        try {
            $validationResult = $this->validateResetToken($token, $email, $code);

            if (!$validationResult['valid']) {
                return ['success' => false, 'message' => 'Invalid or expired reset token or code'];
            }

            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

            $stmt = $this->pdo->prepare("UPDATE users SET 
            password = ?, 
            reset_token = NULL, 
            reset_token_expires = NULL,
            reset_code = NULL 
            WHERE email = ?");

            $stmt->execute([$hashedPassword, $email]);

            if ($stmt->rowCount() > 0) {
                return ['success' => true, 'message' => 'Password updated successfully'];
            } else {
                return ['success' => false, 'message' => 'Failed to update password'];
            }
        } catch (PDOException $e) {
            error_log($e->getMessage());
            return ['success' => false, 'message' => 'An error occurred resetting your password'];
        }
    }


    public function sendResetEmail($email, $username, $token)
    {
        $mail = new \PHPMailer\PHPMailer\PHPMailer(true);

        try {
            $stmt = $this->pdo->prepare("SELECT reset_code FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $resetCode = $stmt->fetchColumn();

            if (!$resetCode) {
                error_log("Error: No reset code found for email: $email");
                return false;
            }

            error_log("Using reset code from database: " . $resetCode . " for email: " . $email);

            // Server settings
            $mail->isSMTP();
            $mail->Host = 'smtp.gmail.com';
            $mail->SMTPAuth = true;
            $mail->Username = $_ENV['RESET_USERNAME'];
            $mail->Password = $_ENV['RESET_PASSWORD'];
            $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port = 587;

            $mail->Timeout = 60;

            // Recipients
            $mail->setFrom('sumuduytube@gmail.com', 'BlogMe');
            $mail->addAddress($email, $username);

            // Content
            $mail->isHTML(true);
            $mail->Subject = 'Password Reset Request';

            $resetUrl = 'http://' . $_SERVER['HTTP_HOST'] . '/blog2/blog-website/auth/reset-password.php?token=' . $token . '&email=' . urlencode($email);

            $mail->Body = '
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .btn { display: inline-block; padding: 10px 20px; background-color: #007bff; color: white; text-decoration: none; border-radius: 4px; }
                .code { display: inline-block; padding: 10px 15px; background-color: #f8f9fa; font-family: monospace; font-size: 20px; font-weight: bold; letter-spacing: 2px; border: 1px solid #ddd; border-radius: 4px; margin: 15px 0; }
            </style>
        </head>
        <body>
            <div class="container">
                <h2>Password Reset Request</h2>
                <p>Hello ' . htmlspecialchars($username) . ',</p>
                <p>We received a request to reset your password. If you didn\'t make this request, you can ignore this email.</p>
                <p>Your password reset code is:</p>
                <div class="code">' . $resetCode . '</div>
                <p>Alternatively, you can click the button below to reset your password:</p>
                <p><a href="' . $resetUrl . '" class="btn">Reset Password</a></p>
                <p>Or copy and paste the following link into your browser:</p>
                <p>' . $resetUrl . '</p>
                <p>This code and link will expire in 1 hour.</p>
                <p>Thank you,<br>BlogMe Team</p>
            </div>
        </body>
        </html>
    ';

            $mail->AltBody = 'Hello ' . $username . ",\n\n"
                . "We received a request to reset your password. If you didn't make this request, you can ignore this email.\n\n"
                . "Your password reset code is: " . $resetCode . "\n\n"
                . "Alternatively, to reset your password, go to this link:\n" . $resetUrl . "\n\n"
                . "This code and link will expire in 1 hour.\n\n"
                . "Thank you,\n"
                . "BlogMe Team";

            // Return success
            return $mail->send();
        } catch (\Exception $e) {
            error_log('Mailer Error: ' . ($mail->ErrorInfo ?? 'Unknown'));
            error_log('Exception: ' . $e->getMessage());
            return false;
        }
    }
}



// Admin authentication functions
class AdminAuth
{
    private $pdo;

    public function __construct($pdo)
    {
        $this->pdo = $pdo;
    }
    public function requestPasswordReset($email)
    {
        try {
            // Check if admin exists
            $stmt = $this->pdo->prepare("SELECT id, email, username FROM admins WHERE email = ?");
            $stmt->execute([$email]);

            if ($stmt->rowCount() === 1) {
                $admin = $stmt->fetch();

                // Generate token and reset code
                $token = bin2hex(random_bytes(32));
                $resetCode = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
                // Extend expiration to 24 hours
                $expires = date('Y-m-d H:i:s', strtotime('+24 hours'));

                error_log("Generated new token and code for admin. Token: $token, Code: $resetCode, Expires: $expires");

                // Save token and code to admin database
                $updateStmt = $this->pdo->prepare("UPDATE admins SET reset_token = ?, reset_token_expires = ?, reset_code = ? WHERE email = ?");
                $updateStmt->execute([$token, $expires, $resetCode, $email]);

                if ($updateStmt->rowCount() > 0) {
                    return [
                        'success' => true,
                        'message' => 'Reset token generated',
                        'user' => $admin,
                        'token' => $token
                    ];
                }
            }

            return ['success' => true, 'message' => 'If your email exists, you will receive reset instructions'];
        } catch (PDOException $e) {
            error_log($e->getMessage());
            return ['success' => false, 'message' => 'An error occurred processing your request'];
        }
    }

    public function validateResetToken($token, $email, $code = null)
    {
        try {
            // Debug logging
            error_log("Validating admin token: " . $token . " for email: " . $email . " with code: " . ($code ?? 'null'));

            if ($code !== null) {
                // Get the stored code from the database for comparison
                $checkStmt = $this->pdo->prepare("SELECT reset_code FROM admins WHERE email = ?");
                $checkStmt->execute([$email]);
                $storedCode = $checkStmt->fetchColumn();
                error_log("Admin code comparison - Input: '$code', Stored: '$storedCode'");

                // If code is provided, validate both token and code
                $stmt = $this->pdo->prepare("SELECT id FROM admins WHERE email = ? AND reset_token = ? AND reset_code = ? AND reset_token_expires > NOW()");
                $stmt->execute([$email, $token, $code]);

                // Debug logging
                error_log("Admin code validation query returned " . $stmt->rowCount() . " rows");
            } else {
                // If only token provided (from URL click)
                $stmt = $this->pdo->prepare("SELECT id FROM admins WHERE email = ? AND reset_token = ? AND reset_token_expires > NOW()");
                $stmt->execute([$email, $token]);

                // Debug logging
                error_log("Admin token validation query returned " . $stmt->rowCount() . " rows");
            }

            $valid = $stmt->rowCount() > 0;
            return ['success' => true, 'valid' => $valid];
        } catch (PDOException $e) {
            error_log("Database error in admin validateResetToken: " . $e->getMessage());
            return ['success' => false, 'valid' => false, 'message' => 'An error occurred validating your token'];
        }
    }

    public function resetPassword($email, $token, $password, $code = null)
    {
        try {
            // First validate token and code if provided
            $validationResult = $this->validateResetToken($token, $email, $code);

            if (!$validationResult['valid']) {
                return ['success' => false, 'message' => 'Invalid or expired reset token or code'];
            }

            // Hash the new password
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

            // Update password and clear token and code
            $stmt = $this->pdo->prepare("UPDATE admins SET 
                password = ?, 
                reset_token = NULL, 
                reset_token_expires = NULL,
                reset_code = NULL 
                WHERE email = ?");

            $stmt->execute([$hashedPassword, $email]);

            if ($stmt->rowCount() > 0) {
                return ['success' => true, 'message' => 'Password updated successfully'];
            } else {
                return ['success' => false, 'message' => 'Failed to update password'];
            }
        } catch (PDOException $e) {
            error_log($e->getMessage());
            return ['success' => false, 'message' => 'An error occurred resetting your password'];
        }
    }

    public function sendResetEmail($email, $username, $token)
    {
        // Use PHPMailer
        $mail = new \PHPMailer\PHPMailer\PHPMailer(true);

        try {
            // Get the reset code from the database
            $stmt = $this->pdo->prepare("SELECT reset_code FROM admins WHERE email = ?");
            $stmt->execute([$email]);
            $resetCode = $stmt->fetchColumn();

            // If there's no reset code (shouldn't happen), log an error
            if (!$resetCode) {
                error_log("Error: No reset code found for admin email: $email");
                return false;
            }

            error_log("Using reset code from database for admin: " . $resetCode . " for email: " . $email);

            // Server settings
            $mail->isSMTP();
            $mail->Host = 'smtp.gmail.com';
            $mail->SMTPAuth = true;
            $mail->Username = $_ENV['RESET_USERNAME'];
            $mail->Password = $_ENV['RESET_PASSWORD'];
            $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port = 587;

            // Set timeout higher for slower connections
            $mail->Timeout = 60;

            // Recipients
            $mail->setFrom('sumuduytube@gmail.com', 'BlogMe Admin');
            $mail->addAddress($email, $username);

            // Content
            $mail->isHTML(true);
            $mail->Subject = 'Admin Password Reset Request';

            $resetUrl = 'http://localhost/blog2/blog-website/admin/auth/reset-password.php?token=' . $token . '&email=' . urlencode($email);

            $mail->Body = '
            <html>
            <head>
                <style>
                    body { font-family: Arial, sans-serif; line-height: 1.6; }
                    .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                    .btn { display: inline-block; padding: 10px 20px; background-color: #343a40; color: white; text-decoration: none; border-radius: 4px; }
                    .code { display: inline-block; padding: 10px 15px; background-color: #f8f9fa; font-family: monospace; font-size: 20px; font-weight: bold; letter-spacing: 2px; border: 1px solid #ddd; border-radius: 4px; margin: 15px 0; }
                </style>
            </head>
            <body>
                <div class="container">
                    <h2>Admin Password Reset Request</h2>
                    <p>Hello ' . htmlspecialchars($username) . ',</p>
                    <p>We received a request to reset your admin account password. If you didn\'t make this request, please ignore this email or contact support.</p>
                    <p>Your admin password reset code is:</p>
                    <div class="code">' . $resetCode . '</div>
                    <p>Alternatively, you can click the button below to reset your password:</p>
                    <p><a href="' . $resetUrl . '" class="btn">Reset Admin Password</a></p>
                    <p>Or copy and paste the following link into your browser:</p>
                    <p>' . $resetUrl . '</p>
                    <p>This code and link will expire in 24 hours.</p>
                    <p>Thank you,<br>BlogMe Admin System</p>
                </div>
            </body>
            </html>
            ';

            $mail->AltBody = 'Hello ' . $username . ",\n\n"
                . "We received a request to reset your admin account password. If you didn't make this request, please ignore this email or contact support.\n\n"
                . "Your admin password reset code is: " . $resetCode . "\n\n"
                . "Alternatively, to reset your password, go to this link:\n" . $resetUrl . "\n\n"
                . "This code and link will expire in 24 hours.\n\n"
                . "Thank you,\n"
                . "BlogMe Admin System";

            // Return success
            return $mail->send();
        } catch (\Exception $e) {
            error_log('Admin Mailer Error: ' . ($mail->ErrorInfo ?? 'Unknown'));
            error_log('Admin Exception: ' . $e->getMessage());
            return false;
        }
    }
}


// Blog management functions
class BlogManager
{
    private $pdo;

    public function __construct($pdo)
    {
        $this->pdo = $pdo;
    }

    public function createBlog($title, $content, $categoryId, $authorId, $featuredImage = null)
    {
        try {
            $slug = $this->generateUniqueSlug($title);

            $stmt = $this->pdo->prepare("
                INSERT INTO blogs (title, slug, content, category_id, author_id, featured_image, status, published_at) 
                VALUES (?, ?, ?, ?, ?, ?, 'published', NOW())
            ");

            $stmt->execute([$title, $slug, $content, $categoryId, $authorId, $featuredImage]);

            return ['success' => true, 'message' => 'Blog created successfully', 'id' => $this->pdo->lastInsertId()];
        } catch (PDOException $e) {
            error_log($e->getMessage());
            return ['success' => false, 'message' => 'Failed to create blog'];
        }
    }

    public function updateBlog($id, $title, $content, $categoryId, $featuredImage = null)
    {
        try {
            $slug = $this->generateUniqueSlug($title, $id);

            if ($featuredImage) {
                $stmt = $this->pdo->prepare("
                    UPDATE blogs 
                    SET title = ?, slug = ?, content = ?, category_id = ?, featured_image = ?, updated_at = NOW()
                    WHERE id = ?
                ");
                $stmt->execute([$title, $slug, $content, $categoryId, $featuredImage, $id]);
            } else {
                $stmt = $this->pdo->prepare("
                    UPDATE blogs 
                    SET title = ?, slug = ?, content = ?, category_id = ?, updated_at = NOW()
                    WHERE id = ?
                ");
                $stmt->execute([$title, $slug, $content, $categoryId, $id]);
            }

            return ['success' => true, 'message' => 'Blog updated successfully'];
        } catch (PDOException $e) {
            error_log($e->getMessage());
            return ['success' => false, 'message' => 'Failed to update blog'];
        }
    }

    public function deleteBlog($id, $userId = null)
    {
        try {
            $sql = "DELETE FROM blogs WHERE id = ?";
            $params = [$id];

            if ($userId) {
                $sql .= " AND author_id = ?";
                $params[] = $userId;
            }

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);

            if ($stmt->rowCount() > 0) {
                return ['success' => true, 'message' => 'Blog deleted successfully'];
            } else {
                return ['success' => false, 'message' => 'Blog not found or permission denied'];
            }
        } catch (PDOException $e) {
            error_log($e->getMessage());
            return ['success' => false, 'message' => 'Failed to delete blog'];
        }
    }

    public function getBlog($id)
    {
        try {
            $stmt = $this->pdo->prepare("
                SELECT b.*, c.name as category_name,c.color, u.username, u.first_name, u.last_name,u.avatar
                FROM blogs b
                LEFT JOIN categories c ON b.category_id = c.id
                LEFT JOIN users u ON b.author_id = u.id
                WHERE b.id = ? AND b.status = 'published'
            ");
            $stmt->execute([$id]);
            return $stmt->fetch();
        } catch (PDOException $e) {
            error_log($e->getMessage());
            return false;
        }
    }

    public function getBlogs($limit = POSTS_PER_PAGE, $offset = 0, $categoryId = null, $search = null)
    {
        try {
            $sql = "
                SELECT b.*, c.name as category_name,c.color, u.username, u.first_name, u.last_name,u.avatar
                FROM blogs b
                LEFT JOIN categories c ON b.category_id = c.id
                LEFT JOIN users u ON b.author_id = u.id
                WHERE b.status = 'published'
            ";

            $params = [];

            if ($categoryId) {
                $sql .= " AND b.category_id = ?";
                $params[] = $categoryId;
            }

            if ($search) {
                $sql .= " AND (b.title LIKE ? OR b.content LIKE ? OR b.excerpt LIKE ?)";
                $searchTerm = "%$search%";
                $params[] = $searchTerm;
                $params[] = $searchTerm;
                $params[] = $searchTerm;
            }

            $sql .= " ORDER BY b.published_at DESC LIMIT ? OFFSET ?";
            $params[] = $limit;
            $params[] = $offset;

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);

            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log($e->getMessage());
            return [];
        }
    }

    public function getUserBlogs($userId)
    {
        try {
            $stmt = $this->pdo->prepare("
                SELECT b.*, c.name as category_name
                FROM blogs b
                LEFT JOIN categories c ON b.category_id = c.id
                WHERE b.author_id = ?
                ORDER BY b.created_at DESC
            ");
            $stmt->execute([$userId]);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log($e->getMessage());
            return [];
        }
    }



    private function generateUniqueSlug($title, $excludeId = null)
    {
        $baseSlug = generateSlug($title);
        $slug = $baseSlug;
        $counter = 1;

        while (true) {
            $sql = "SELECT id FROM blogs WHERE slug = ?";
            $params = [$slug];

            if ($excludeId) {
                $sql .= " AND id != ?";
                $params[] = $excludeId;
            }

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);

            if ($stmt->rowCount() === 0) {
                break;
            }

            $slug = $baseSlug . '-' . $counter;
            $counter++;
        }

        return $slug;
    }
}

// Category management functions
class CategoryManager
{
    private $pdo;

    public function __construct($pdo)
    {
        $this->pdo = $pdo;
    }

    public function getCategories($activeOnly = true)
    {
        try {
            $sql = "SELECT * FROM categories";
            if ($activeOnly) {
                $sql .= " WHERE is_active = 1";
            }
            $sql .= " ORDER BY name";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute();
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log($e->getMessage());
            return [];
        }
    }

    public function createCategory($name, $description, $color, $createdBy)
    {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO categories (name, description, color, created_by) 
                VALUES (?, ?, ?, ?)
            ");
            $stmt->execute([$name, $description, $color, $createdBy]);

            return ['success' => true, 'message' => 'Category created successfully'];
        } catch (PDOException $e) {
            error_log($e->getMessage());
            return ['success' => false, 'message' => 'Failed to create category'];
        }
    }

    public function updateCategory($id, $name, $description, $color)
    {
        try {
            $stmt = $this->pdo->prepare("
                UPDATE categories 
                SET name = ?, description = ?, color = ?, updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$name, $description, $color, $id]);

            return ['success' => true, 'message' => 'Category updated successfully'];
        } catch (PDOException $e) {
            error_log($e->getMessage());
            return ['success' => false, 'message' => 'Failed to update category'];
        }
    }

    public function deleteCategory($id)
    {
        try {
            $stmt = $this->pdo->prepare("DELETE FROM categories WHERE id = ?");
            $stmt->execute([$id]);

            return ['success' => true, 'message' => 'Category deleted successfully'];
        } catch (PDOException $e) {
            error_log($e->getMessage());
            return ['success' => false, 'message' => 'Failed to delete category'];
        }
    }
}

// User management functions
class UserManager
{
    private $pdo;

    public function __construct($pdo)
    {
        $this->pdo = $pdo;
    }

    public function getUsers($limit = 20, $offset = 0)
    {
        try {
            $stmt = $this->pdo->prepare("
                SELECT id, username, email, first_name, last_name, is_blocked, created_at,avatar,
                       (SELECT COUNT(*) FROM blogs WHERE author_id = users.id) as blog_count
                FROM users 
                ORDER BY created_at DESC 
                LIMIT ? OFFSET ?
            ");
            $stmt->execute([$limit, $offset]);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log($e->getMessage());
            return [];
        }
    }

    public function toggleUserBlock($userId)
    {
        try {
            $stmt = $this->pdo->prepare("UPDATE users SET is_blocked = NOT is_blocked WHERE id = ?");
            $stmt->execute([$userId]);

            return ['success' => true, 'message' => 'User status updated successfully'];
        } catch (PDOException $e) {
            error_log($e->getMessage());
            return ['success' => false, 'message' => 'Failed to update user status'];
        }
    }

    public function deleteUser($userId)
    {
        try {
            $stmt = $this->pdo->prepare("DELETE FROM users WHERE id = ?");
            $stmt->execute([$userId]);

            return ['success' => true, 'message' => 'User deleted successfully'];
        } catch (PDOException $e) {
            error_log($e->getMessage());
            return ['success' => false, 'message' => 'Failed to delete user'];
        }
    }
}

// Notification management functions
class NotificationManager
{
    private $pdo;

    public function __construct($pdo)
    {
        $this->pdo = $pdo;
    }

    public function sendNotification($title, $message, $type, $targetType, $targetUserId, $sentBy)
    {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO notifications (title, message, type, target_type, target_user_id, sent_by) 
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$title, $message, $type, $targetType, $targetUserId, $sentBy]);
            $notificationId = $this->pdo->lastInsertId();

            // Create user notifications based on target type
            if ($targetType === 'all_users') {
                $stmt = $this->pdo->prepare("
                    INSERT INTO user_notifications (notification_id, user_id)
                    SELECT ?, id FROM users WHERE is_blocked = 0
                ");
                $stmt->execute([$notificationId]);
            } elseif ($targetType === 'specific_user' && $targetUserId) {
                $stmt = $this->pdo->prepare("
                    INSERT INTO user_notifications (notification_id, user_id) VALUES (?, ?)
                ");
                $stmt->execute([$notificationId, $targetUserId]);
            }

            return ['success' => true, 'message' => 'Notification sent successfully'];
        } catch (PDOException $e) {
            error_log($e->getMessage());
            return ['success' => false, 'message' => 'Failed to send notification'];
        }
    }

    public function getUserNotifications($userId, $limit = 10)
    {
        try {
            $stmt = $this->pdo->prepare("
                SELECT n.*, un.is_read, un.read_at
                FROM notifications n
                JOIN user_notifications un ON n.id = un.notification_id
                WHERE un.user_id = ?
                ORDER BY n.created_at DESC
                LIMIT ?
            ");
            $stmt->execute([$userId, $limit]);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log($e->getMessage());
            return [];
        }
    }

    public function markNotificationAsRead($notificationId, $userId)
    {
        try {
            $stmt = $this->pdo->prepare("
                UPDATE user_notifications 
                SET is_read = 1, read_at = NOW() 
                WHERE notification_id = ? AND user_id = ?
            ");
            $stmt->execute([$notificationId, $userId]);

            return ['success' => true];
        } catch (PDOException $e) {
            error_log($e->getMessage());
            return ['success' => false];
        }
    }

    public function timeAgo($datetime)
    {
        $timestamp = strtotime($datetime);
        $now = time();
        $diff = $now - $timestamp;

        if ($diff < 60) {
            return 'just now';
        } elseif ($diff < 3600) {
            $minutes = round($diff / 60);
            return $minutes . ' minute' . ($minutes > 1 ? 's' : '') . ' ago';
        } elseif ($diff < 86400) {
            $hours = round($diff / 3600);
            return $hours . ' hour' . ($hours > 1 ? 's' : '') . ' ago';
        } elseif ($diff < 604800) {
            $days = round($diff / 86400);
            return $days . ' day' . ($days > 1 ? 's' : '') . ' ago';
        } elseif ($diff < 2592000) {
            $weeks = round($diff / 604800);
            return $weeks . ' week' . ($weeks > 1 ? 's' : '') . ' ago';
        } elseif ($diff < 31536000) {
            $months = round($diff / 2592000);
            return $months . ' month' . ($months > 1 ? 's' : '') . ' ago';
        } else {
            $years = round($diff / 31536000);
            return $years . ' year' . ($years > 1 ? 's' : '') . ' ago';
        }
    }
}

// Initialize global instances
$userAuth = new UserAuth($pdo);
$blogManager = new BlogManager($pdo);
$categoryManager = new CategoryManager($pdo);
$userManager = new UserManager($pdo);
$notificationManager = new NotificationManager($pdo);
$adminAuth = new AdminAuth($pdo);